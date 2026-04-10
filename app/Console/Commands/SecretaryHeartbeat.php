<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\AI\AgentLoop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('secretary:heartbeat')]
#[Description('Run the secretary heartbeat — check for pending tasks and trigger scheduled actions.')]
class SecretaryHeartbeat extends Command
{
    public function handle(AgentLoop $loop): int
    {
        $this->info('Running secretary heartbeat...');

        // Find or create the system heartbeat conversation (user_id=1, adjust as needed)
        $conversation = Conversation::firstOrCreate(
            ['channel' => 'heartbeat'],
            [
                'user_id' => 1,
                'title' => 'Heartbeat',
            ]
        );

        $prompt = <<<'PROMPT'
        This is your scheduled heartbeat check. Review any pending tasks, upcoming calendar events,
        or actions you should take proactively. If there is nothing to do, respond briefly with "All clear.".
        PROMPT;

        $response = $loop->run($conversation, $prompt);

        $this->line("Secretary: {$response->content}");

        return self::SUCCESS;
    }
}
