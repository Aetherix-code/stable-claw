<?php

namespace App\Http\Controllers\Secretary;

use App\Http\Controllers\Controller;
use App\Http\Requests\Secretary\SendMessageRequest;
use App\Jobs\ProcessConversationMessage;
use App\Models\Conversation;
use App\Models\SecretaryMessage;
use App\Services\AI\LearnModeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    public function __construct(
        private readonly LearnModeService $learnModeService,
    ) {}

    /**
     * Show the main secretary chat page.
     */
    public function index(Request $request): Response
    {
        $conversations = $request->user()
            ->conversations()
            ->where('channel', 'web')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'title', 'is_learn_mode', 'updated_at']);

        return Inertia::render('secretary/Chat', [
            'conversations' => $conversations,
        ]);
    }

    /**
     * Load a specific conversation with its messages.
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        $conversations = $request->user()
            ->conversations()
            ->where('channel', 'web')
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'title', 'is_learn_mode', 'updated_at']);

        return Inertia::render('secretary/Chat', [
            'conversations' => $conversations,
            'activeConversation' => $conversation->only(['id', 'title', 'is_learn_mode', 'learn_mode_skill_name', 'ai_provider']),
            'messages' => $conversation->messages->map(fn ($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'tool_name' => $msg->tool_name,
                'created_at' => $msg->created_at,
            ]),
        ]);
    }

    /**
     * Create a new conversation.
     */
    public function store(Request $request): RedirectResponse
    {
        $conversation = $request->user()->conversations()->create([
            'channel' => 'web',
            'title' => 'New conversation',
        ]);

        return to_route('secretary.chat.show', $conversation);
    }

    /**
     * Dispatch a message to the agent queue and return immediately.
     */
    public function send(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        // Release the session lock so the user can navigate freely while processing.
        session()->save();

        $text = $request->validated('message');

        if ($conversation->messages()->count() === 0 && $conversation->title === 'New conversation') {
            $conversation->update(['title' => mb_substr($text, 0, 60)]);
        }

        // Store the user message synchronously so it appears in the UI immediately.
        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $text,
        ]);

        $conversation->update(['is_processing' => true]);

        ProcessConversationMessage::dispatch($conversation, $userMessage);

        return response()->json($this->sincePayload($conversation, 0));
    }

    /**
     * Poll for messages newer than a given ID and current processing state.
     */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->refresh();

        return response()->json($this->sincePayload($conversation, (int) $request->query('since', 0)));
    }

    /**
     * Build a payload containing only messages newer than $sinceId.
     * Pass 0 to get all messages.
     *
     * @return array<string, mixed>
     */
    private function sincePayload(Conversation $conversation, int $sinceId): array
    {
        $messages = $conversation->messages()
            ->when($sinceId > 0, fn ($q) => $q->where('id', '>', $sinceId))
            ->orderBy('id')
            ->get()
            ->map(fn (SecretaryMessage $msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'tool_name' => $msg->tool_name,
                'created_at' => $msg->created_at,
            ]);

        return [
            'messages' => $messages,
            'conversation' => [
                'is_learn_mode' => $conversation->is_learn_mode,
                'learn_mode_skill_name' => $conversation->learn_mode_skill_name,
                'is_processing' => $conversation->is_processing,
            ],
        ];
    }

    /**
     * Start recording a skill (button-initiated flow — bypasses the agent).
     */
    public function startLearnMode(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $request->validate([
            'skill_name' => ['required', 'string', 'max:255'],
        ]);

        $skillName = $request->input('skill_name');
        $conversation->startLearnMode($skillName);

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => "I'm ready to record **{$skillName}**. Please walk me through it step by step — show me everything I need to know.",
        ]);

        return response()->json($this->sincePayload($conversation, 0));
    }

    /**
     * End recording and compile the skill (button-initiated flow — bypasses the agent).
     */
    public function endLearnMode(Conversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        $skill = $this->learnModeService->compileSkill($conversation);

        if ($skill) {
            $conversation->messages()->create([
                'role' => 'assistant',
                'content' => "Done! I've saved **{$skill->name}** and can now perform it on my own whenever you need.",
            ]);
        }

        return response()->json($this->sincePayload($conversation, 0));
    }
}
