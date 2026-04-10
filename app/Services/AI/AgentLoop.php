<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\SecretaryMessage;
use App\Models\Skill;
use App\Models\ToolExecution;
use App\Services\AI\DTOs\AIResponse;
use App\Services\Tools\Contracts\NeedsConversationContext;
use App\Services\Tools\DTOs\ToolResult;
use App\Services\Tools\ToolRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class AgentLoop
{
    public function __construct(
        private readonly AIManager $ai,
        private readonly ToolRegistry $tools,
    ) {}

    /**
     * Process a pre-stored user message and run the agent loop until a final response is produced.
     */
    public function run(Conversation $conversation, SecretaryMessage $userMessage): SecretaryMessage
    {
        $provider = $conversation->ai_provider
            ? $this->ai->connection($conversation->ai_provider)
            : $this->ai->driver();

        $maxIterations = config('ai.max_agent_iterations', 15);
        $iteration = 0;
        $apiMessages = $this->buildApiMessages($conversation);

        while ($iteration < $maxIterations) {
            $iteration++;

            try {
                $response = $provider->chat($apiMessages, $this->tools->toAIFormat());
            } catch (\Throwable $e) {
                Log::error('AgentLoop: AI request failed', [
                    'error' => $e->getMessage(),
                    'messages_sent' => array_map(fn ($m) => [
                        'role' => $m['role'],
                        'has_tool_calls' => isset($m['tool_calls']),
                        'tool_calls' => $m['tool_calls'] ?? null,
                        'content_length' => strlen((string) ($m['content'] ?? '')),
                    ], $apiMessages),
                ]);

                return $conversation->messages()->create([
                    'role' => 'assistant',
                    'content' => 'Sorry, I encountered an error contacting the AI provider: '.$e->getMessage(),
                ]);
            }

            $assistantMessage = $this->storeAssistantMessage($conversation, $response);

            if (! $response->hasToolCalls()) {
                return $assistantMessage;
            }

            // Execute each tool call and append results to the message history
            $toolResultMessages = $this->executeToolCalls($conversation, $response);

            // Refresh conversation so state changes (e.g. learn mode start/stop) are reflected
            $conversation->refresh();

            // Rebuild the full message history for the next iteration
            $apiMessages = $this->buildApiMessages($conversation);

            foreach ($toolResultMessages as $toolMsg) {
                $apiMessages[] = $toolMsg;
            }
        }

        // Safety fallback message if we hit max iterations
        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'I reached the maximum number of steps. Please try rephrasing your request.',
        ]);
    }

    /**
     * Build the full message history for an AI API call from the DB.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildApiMessages(Conversation $conversation): array
    {
        $messages = [];

        // System prompt
        $systemPrompt = config('ai.system_prompt');

        if ($conversation->is_learn_mode && $conversation->learn_mode_skill_name) {
            $learnSuffix = config('ai.learn_mode_system_prompt');
            $systemPrompt .= str_replace('{skill_name}', $conversation->learn_mode_skill_name, $learnSuffix);
        }

        if ($conversation->skill_id) {
            $systemPrompt .= "\n\nThis conversation is a skill refinement session. The skill being refined has ID: {$conversation->skill_id}. When the user approves changes, use the update_skill tool with skill_id={$conversation->skill_id} to save the updated skill.";
        }

        // Inject available skills so the agent can invoke them without a tool call
        $skills = Skill::orderBy('name')->get(['id', 'name', 'description', 'trigger_keywords']);

        if ($skills->isNotEmpty()) {
            $systemPrompt .= "\n\n## Learned Skills\n\nYou have the following learned skills available. When the user asks you to perform a task that matches one, use read_skill to fetch its full steps and then execute them:\n";
            foreach ($skills as $skill) {
                $triggers = $skill->trigger_keywords ? implode(', ', $skill->trigger_keywords) : '';
                $systemPrompt .= "\n- **{$skill->name}** (ID: {$skill->id})";
                if ($skill->description) {
                    $systemPrompt .= ": {$skill->description}";
                }
                if ($triggers) {
                    $systemPrompt .= " [triggers: {$triggers}]";
                }
            }
        }

        $messages[] = [
            'role' => 'system',
            'content' => $systemPrompt,
        ];

        foreach ($conversation->messages as $msg) {
            $apiMessage = [
                'role' => $msg->role,
                'content' => $msg->content,
            ];

            if (! empty($msg->tool_calls)) {
                // Normalise stored arguments: Ollama rejects "[]" — must be "{}"
                $toolCalls = array_map(function (array $tc) {
                    $args = $tc['function']['arguments'] ?? '{}';
                    if (! is_string($args)) {
                        $args = json_encode((object) $args) ?: '{}';
                    } elseif (trim($args) === '[]') {
                        $args = '{}';
                    }
                    $tc['function']['arguments'] = $args;

                    return $tc;
                }, (array) $msg->tool_calls);
                $apiMessage['tool_calls'] = $toolCalls;
            }

            if ($msg->tool_call_id) {
                $apiMessage['tool_call_id'] = $msg->tool_call_id;
            }

            if ($msg->tool_name) {
                $apiMessage['name'] = $msg->tool_name;
            }

            $messages[] = $apiMessage;
        }

        return $messages;
    }

    private function storeAssistantMessage(Conversation $conversation, AIResponse $response): SecretaryMessage
    {
        $toolCallsPayload = null;

        if ($response->hasToolCalls()) {
            $toolCallsPayload = array_map(fn ($tc) => [
                'id' => $tc->id,
                'type' => 'function',
                'function' => [
                    'name' => $tc->name,
                    'arguments' => json_encode((object) $tc->arguments),
                ],
            ], $response->toolCalls);

        }

        return $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response->content,
            'tool_calls' => $toolCallsPayload,
        ]);
    }

    /**
     * Execute all tool calls from an AI response and persist results.
     *
     * @return array<int, array<string, mixed>> API-formatted tool result messages
     */
    private function executeToolCalls(Conversation $conversation, AIResponse $response): array
    {
        $apiToolMessages = [];

        foreach ($response->toolCalls as $toolCall) {
            $execution = ToolExecution::create([
                'conversation_id' => $conversation->id,
                'tool_name' => $toolCall->name,
                'parameters' => $toolCall->arguments,
                'status' => 'running',
                'started_at' => CarbonImmutable::now(),
            ]);

            try {
                $tool = $this->tools->get($toolCall->name);

                if ($tool instanceof NeedsConversationContext) {
                    $tool->setConversation($conversation);
                }

                $result = $tool->execute($toolCall->arguments);
                $status = $result->success ? 'success' : 'error';
            } catch (\Throwable $e) {
                $result = ToolResult::error($e->getMessage());
                $status = 'error';
                Log::error('AgentLoop: Tool execution failed', [
                    'tool' => $toolCall->name,
                    'error' => $e->getMessage(),
                ]);
            }

            $execution->update([
                'result' => json_decode($result->toJson(), true),
                'status' => $status,
                'error_message' => $result->error,
                'completed_at' => CarbonImmutable::now(),
            ]);

            // Persist the tool result message to the DB
            $conversation->messages()->create([
                'role' => 'tool',
                'content' => $result->toJson(),
                'tool_call_id' => $toolCall->id,
                'tool_name' => $toolCall->name,
            ]);

            $apiToolMessages[] = [
                'role' => 'tool',
                'content' => $this->sanitizeToolContentForApi($result->toJson()),
                'tool_call_id' => $toolCall->id,
                'name' => $toolCall->name,
            ];
        }

        return $apiToolMessages;
    }

    /**
     * Strip large binary fields (e.g. screenshot_base64) before sending to the AI API.
     */
    private function sanitizeToolContentForApi(string $json): string
    {
        $data = json_decode($json, true);

        if (is_array($data) && isset($data['screenshot_base64'])) {
            $bytes = (int) (strlen($data['screenshot_base64']) * 3 / 4);
            $data['screenshot_base64'] = "[screenshot captured, {$bytes} bytes — displayed in UI]";
        }

        if (is_array($data) && isset($data['download_url'])) {
            $name = $data['download_filename'] ?? 'file';
            $data['download_url'] = "[file \"{$name}\" ready — download link shown in UI]";
        }

        return json_encode($data);
    }
}
