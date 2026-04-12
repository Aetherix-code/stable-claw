<?php

namespace App\Services\Tools;

use App\Models\Conversation;
use App\Services\Connections\ConnectionManager;
use App\Services\Connections\Contracts\MailConnector;
use App\Services\Tools\Contracts\NeedsConversationContext;
use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;
use Exception;

class MailTool extends Tool implements NeedsConversationContext
{
    private ?Conversation $conversation = null;

    public function __construct(
        private readonly ConnectionManager $connectionManager,
    ) {}

    public function setConversation(Conversation $conversation): void
    {
        $this->conversation = $conversation;
    }

    public function name(): string
    {
        return 'mail';
    }

    public function description(): string
    {
        return 'Read and manage email. Actions: list (recent emails), read (full email by UID), search (find emails by text query), draft (create a draft email). Requires the user to have an active mail connection configured.';
    }

    public function parameters(): array
    {
        return [
            'action' => [
                'type' => 'string',
                'enum' => ['list', 'read', 'search', 'draft'],
                'description' => 'The mail action to perform.',
            ],
            'uid' => [
                'type' => 'integer',
                'description' => 'Email UID to read (for action=read).',
            ],
            'query' => [
                'type' => 'string',
                'description' => 'Search query text (for action=search).',
            ],
            'to' => [
                'type' => 'string',
                'description' => 'Recipient email address (for action=draft).',
            ],
            'subject' => [
                'type' => 'string',
                'description' => 'Email subject (for action=draft).',
            ],
            'body' => [
                'type' => 'string',
                'description' => 'Email body text (for action=draft).',
            ],
            'limit' => [
                'type' => 'integer',
                'description' => 'Maximum number of emails to return (for action=list, search). Default 15.',
            ],
        ];
    }

    public function required(): array
    {
        return ['action'];
    }

    public function execute(array $parameters): ToolResult
    {
        try {
            $connector = $this->resolveConnector();

            return match ($parameters['action']) {
                'list' => ToolResult::success($connector->list((int) ($parameters['limit'] ?? 15))),
                'read' => $this->readEmail($connector, $parameters),
                'search' => $this->searchEmails($connector, $parameters),
                'draft' => $this->createDraft($connector, $parameters),
                default => ToolResult::error("Unknown action: {$parameters['action']}"),
            };
        } catch (Exception $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    private function readEmail(MailConnector $connector, array $parameters): ToolResult
    {
        if (! isset($parameters['uid'])) {
            return ToolResult::error('The "uid" parameter is required for action=read.');
        }

        return ToolResult::success($connector->read((int) $parameters['uid']));
    }

    private function searchEmails(MailConnector $connector, array $parameters): ToolResult
    {
        if (! isset($parameters['query'])) {
            return ToolResult::error('The "query" parameter is required for action=search.');
        }

        return ToolResult::success($connector->search(
            $parameters['query'],
            (int) ($parameters['limit'] ?? 15),
        ));
    }

    private function createDraft(MailConnector $connector, array $parameters): ToolResult
    {
        foreach (['to', 'subject', 'body'] as $field) {
            if (! isset($parameters[$field]) || $parameters[$field] === '') {
                return ToolResult::error("The \"{$field}\" parameter is required for action=draft.");
            }
        }

        return ToolResult::success($connector->draft(
            $parameters['to'],
            $parameters['subject'],
            $parameters['body'],
        ));
    }

    private function resolveConnector(): MailConnector
    {
        if ($this->conversation === null) {
            throw new Exception('No conversation context available.');
        }

        $connection = $this->conversation->user->connections()
            ->where('type', 'mail')
            ->where('is_active', true)
            ->first();

        if ($connection === null) {
            throw new Exception('No active mail connection found. Please configure a mail connection in your settings.');
        }

        return $this->connectionManager->resolveMailConnector($connection);
    }
}
