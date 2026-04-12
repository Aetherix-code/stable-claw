<?php

namespace App\Services\Connections\Mail;

use App\Models\Connection;
use App\Services\Connections\Contracts\MailConnector;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class GmailConnector implements MailConnector
{
    private ?Client $client = null;

    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function list(int $limit = 15): array
    {
        $folder = $this->getClient()->getFolder('INBOX');

        $messages = $folder->messages()
            ->all()
            ->limit($limit)
            ->setFetchOrder('desc')
            ->get();

        return $messages->map(fn (Message $message) => $this->formatListItem($message))->toArray();
    }

    public function read(int $uid): array
    {
        $folder = $this->getClient()->getFolder('INBOX');
        $message = $folder->query()->getMessageByUid($uid);

        $body = $message->hasTextBody()
            ? $message->getTextBody()
            : strip_tags($message->getHTMLBody());

        $attachments = $message->getAttachments()->map(fn ($a) => $a->getName())->toArray();

        return [
            'uid' => $message->get('uid'),
            'subject' => (string) $message->get('subject'),
            'from' => (string) $message->get('from'),
            'to' => (string) $message->get('to'),
            'date' => $message->get('date')?->first()?->format('Y-m-d H:i:s') ?? '',
            'body' => mb_substr($body, 0, 8000),
            'attachments' => $attachments,
        ];
    }

    public function search(string $query, int $limit = 15): array
    {
        $folder = $this->getClient()->getFolder('INBOX');

        $messages = $folder->messages()
            ->whereText($query)
            ->limit($limit)
            ->setFetchOrder('desc')
            ->get();

        return $messages->map(fn (Message $message) => $this->formatListItem($message))->toArray();
    }

    public function draft(string $to, string $subject, string $body): array
    {
        $credentials = $this->connection->credentials;
        $from = $credentials['email'];

        $mime = implode("\r\n", [
            "From: {$from}",
            "To: {$to}",
            "Subject: {$subject}",
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            $body,
        ]);

        $draftsFolder = $this->getClient()->getFolder('[Gmail]/Drafts');
        $draftsFolder->appendMessage($mime, ['\Draft', '\Seen']);

        return [
            'success' => true,
            'message' => "Draft created: \"{$subject}\" to {$to}",
        ];
    }

    private function formatListItem(Message $message): array
    {
        $text = $message->hasTextBody()
            ? $message->getTextBody()
            : strip_tags($message->getHTMLBody());

        return [
            'uid' => $message->get('uid'),
            'subject' => (string) $message->get('subject'),
            'from' => (string) $message->get('from'),
            'date' => $message->get('date')?->first()?->format('Y-m-d H:i:s') ?? '',
            'snippet' => mb_substr(trim(preg_replace('/\s+/', ' ', $text)), 0, 150),
            'is_read' => $message->getFlags()->has('Seen'),
        ];
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            $credentials = $this->connection->credentials;

            $cm = new ClientManager;
            $this->client = $cm->make([
                'host' => 'imap.gmail.com',
                'port' => 993,
                'encryption' => 'ssl',
                'validate_cert' => true,
                'username' => $credentials['email'],
                'password' => $credentials['password'],
                'protocol' => 'imap',
            ]);

            $this->client->connect();
        }

        return $this->client;
    }
}
