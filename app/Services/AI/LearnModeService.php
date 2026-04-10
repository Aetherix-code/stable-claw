<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Skill;
use Illuminate\Support\Facades\Log;

class LearnModeService
{
    public function __construct(
        private readonly AIManager $ai,
    ) {}

    /**
     * Compile the current learn-mode conversation into a persisted Skill.
     */
    public function compileSkill(Conversation $conversation): ?Skill
    {
        if (! $conversation->is_learn_mode || ! $conversation->learn_mode_skill_name) {
            return null;
        }

        $transcript = $this->buildTranscript($conversation);
        $skillName = $conversation->learn_mode_skill_name;

        // Ask the AI to extract a structured workflow from the transcript
        $structuredData = $this->extractStructuredSkill($skillName, $transcript);

        $skill = Skill::create([
            'name' => $structuredData['name'] ?? $skillName,
            'description' => $structuredData['description'] ?? '',
            'detailed_instructions' => $structuredData['detailed_instructions'] ?? null,
            'trigger_keywords' => $structuredData['trigger_keywords'] ?? [],
            'steps' => $structuredData['steps'] ?? [],
            'memory_keys' => $structuredData['memory_keys'] ?? [],
            'transcript' => $transcript,
            'learned_from_conversation_id' => $conversation->id,
        ]);

        $conversation->stopLearnMode();

        return $skill;
    }

    /**
     * Build a plain-text transcript from the conversation messages.
     */
    private function buildTranscript(Conversation $conversation): string
    {
        $lines = [];

        foreach ($conversation->messages as $msg) {
            $label = match ($msg->role) {
                'user' => 'USER',
                'assistant' => 'SECRETARY',
                'tool' => "TOOL ({$msg->tool_name})",
                default => strtoupper($msg->role),
            };

            if ($msg->content) {
                $lines[] = "[{$label}]: {$msg->content}";
            }
        }

        return implode("\n\n", $lines);
    }

    /**
     * Use the AI to extract a structured skill definition from the transcript.
     *
     * @return array{name: string, description: string, trigger_keywords: string[], steps: array, memory_keys: string[]}
     */
    private function extractStructuredSkill(string $skillName, string $transcript): array
    {
        $prompt = <<<EOT
        You are analyzing a teaching conversation to extract a reusable skill.
        The skill being taught is: "{$skillName}"

        CONVERSATION TRANSCRIPT:
        {$transcript}

        Extract a structured JSON object with these fields:
        - name: short, clear skill name
        - description: 1-2 sentence description of what it does
        - detailed_instructions: a comprehensive, human-readable reference document covering: all API endpoints used (with methods, paths, request/response shapes), credentials or memory keys referenced, pagination strategies, data mappings, quota considerations, and any other important technical details discovered during the session. Write it as a thorough reference that would let someone (or the AI) reproduce the entire workflow without access to the original conversation.
        - trigger_keywords: array of 3-6 keywords/phrases that would trigger this skill
        - steps: ordered array of step objects, each with: { "description": "...", "tool": "browser|memory_read|memory_write|none", "action": "...", "notes": "..." }
        - memory_keys: array of memory keys this skill depends on (e.g. ["toggl_api_key"])

        Return ONLY valid JSON, no markdown fences.
        EOT;

        try {
            $response = $this->ai->driver()->chat([
                ['role' => 'user', 'content' => $prompt],
            ]);

            $json = trim($response->content ?? '{}');
            // Strip possible markdown code fences
            $json = preg_replace('/^```(?:json)?\s*/m', '', $json);
            $json = preg_replace('/\s*```$/m', '', $json);

            return json_decode($json, true) ?? [];
        } catch (\Throwable $e) {
            Log::error('LearnModeService: Failed to extract structured skill', ['error' => $e->getMessage()]);

            return ['name' => $skillName, 'description' => 'Skill learned via conversation.'];
        }
    }
}
