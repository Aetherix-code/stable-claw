<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SendFileTool extends Tool
{
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
        $path = 'secretary/files/'.Str::uuid().'/'.$safeName;

        Storage::disk('public')->put($path, $bytes);

        return ToolResult::success([
            'download_url' => Storage::disk('public')->url($path),
            'download_filename' => $safeName,
            'size_bytes' => strlen($bytes),
        ]);
    }
}
