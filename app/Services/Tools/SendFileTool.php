<?php

namespace App\Services\Tools;

use App\Models\Conversation;
use App\Models\TelegramSetting;
use App\Services\Tools\Contracts\NeedsConversationContext;
use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SendFileTool extends Tool implements NeedsConversationContext
{
    private ?Conversation $conversation = null;

    public function setConversation(Conversation $conversation): void
    {
        $this->conversation = $conversation;
    }

    public function name(): string
    {
        return 'send_file';
    }

    public function description(): string
    {
        return 'Send a file to the user for download. Provide content directly (as UTF-8 or base64) or reference a source_path from the system temp directory. Returns a download URL the user can click to save the file.';
    }

    public function parameters(): array
    {
        return [
            'filename' => [
                'type' => 'string',
                'description' => 'The filename the user will download, including extension (e.g. "report.csv", "data.json", "output.txt").',
            ],
            'content' => [
                'type' => 'string',
                'description' => 'The file content. Plain UTF-8 string by default. If encoding is "base64", provide a base64-encoded string. Not required when source_path is provided.',
            ],
            'source_path' => [
                'type' => 'string',
                'description' => 'Absolute path to a file on disk to send (e.g. a temp file from http_request with save_to_file). When provided, content and encoding are ignored. Only files in the system temp directory are allowed.',
            ],
            'mime_type' => [
                'type' => 'string',
                'description' => 'MIME type of the file (e.g. "text/csv", "application/json", "text/plain", "application/pdf"). Defaults to "application/octet-stream".',
            ],
            'encoding' => [
                'type' => 'string',
                'enum' => ['utf8', 'base64'],
                'description' => 'How the content field is encoded. Default "utf8".',
            ],
        ];
    }

    public function required(): array
    {
        return ['filename'];
    }

    public function execute(array $parameters): ToolResult
    {
        $filename = $parameters['filename'];
        $sourcePath = $parameters['source_path'] ?? null;

        if ($sourcePath) {
            $realPath = realpath($sourcePath);
            $tmpDir = realpath(sys_get_temp_dir());

            if ($realPath === false || ! str_starts_with($realPath, $tmpDir)) {
                return ToolResult::error('source_path must be a valid file inside the system temp directory.');
            }

            $bytes = file_get_contents($realPath);

            if ($bytes === false) {
                return ToolResult::error('Could not read file at source_path.');
            }
        } else {
            $content = $parameters['content'] ?? null;

            if ($content === null) {
                return ToolResult::error('Either content or source_path is required.');
            }

            $encoding = $parameters['encoding'] ?? 'utf8';

            $bytes = $encoding === 'base64'
                ? base64_decode($content, strict: true)
                : $content;

            if ($bytes === false) {
                return ToolResult::error('Invalid base64 content.');
            }
        }

        $safeName = Str::slug(pathinfo($filename, PATHINFO_FILENAME)).'.'.pathinfo($filename, PATHINFO_EXTENSION);

        // For Telegram conversations, send the file directly via API
        if ($this->conversation?->channel === 'telegram' && $this->conversation->telegram_chat_id) {
            return $this->sendViaTelegram($this->conversation->telegram_chat_id, $safeName, $bytes, $parameters['mime_type'] ?? null);
        }

        $path = 'secretary/files/'.Str::uuid().'/'.$safeName;

        Storage::disk('public')->put($path, $bytes);

        return ToolResult::success([
            'download_url' => Storage::disk('public')->url($path),
            'download_filename' => $safeName,
            'size_bytes' => strlen($bytes),
        ]);
    }

    private function sendViaTelegram(string $chatId, string $filename, string $bytes, ?string $mimeType): ToolResult
    {
        $settings = TelegramSetting::instance();
        $token = $settings->bot_token;

        if (! $token) {
            return ToolResult::error('Telegram bot token is not configured.');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'tg_') ?: sys_get_temp_dir().'/tg_'.Str::random(8);
        file_put_contents($tmpFile, $bytes);

        try {
            $response = Http::attach(
                'document',
                file_get_contents($tmpFile),
                $filename,
                ['Content-Type' => $mimeType ?? 'application/octet-stream']
            )->post("https://api.telegram.org/bot{$token}/sendDocument", [
                'chat_id' => $chatId,
            ]);

            if (! $response->successful()) {
                return ToolResult::error('Failed to send file via Telegram: '.$response->body());
            }
        } finally {
            @unlink($tmpFile);
        }

        return ToolResult::success([
            'sent_via' => 'telegram',
            'filename' => $filename,
            'size_bytes' => strlen($bytes),
        ]);
    }
}
