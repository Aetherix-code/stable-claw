<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HttpRequestTool extends Tool
{
    public function name(): string
    {
        return 'http_request';
    }

    public function description(): string
    {
        return 'Make HTTP requests (GET, POST, PUT, PATCH, DELETE) with custom headers, JSON/form body, query parameters, bearer token auth, and basic auth. Use this to call APIs, submit forms, or interact with web services. IMPORTANT: All parameters must be top-level keys — do NOT nest them inside an "auth" or "options" object. For basic auth use the "username" and "password" parameters directly.';
    }

    public function parameters(): array
    {
        return [
            'method' => [
                'type' => 'string',
                'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
                'description' => 'HTTP method.',
            ],
            'url' => [
                'type' => 'string',
                'description' => 'The full URL to request.',
            ],
            'headers' => [
                'type' => 'object',
                'description' => 'Optional key-value map of request headers.',
            ],
            'query' => [
                'type' => 'object',
                'description' => 'Optional key-value map of URL query parameters.',
            ],
            'body_json' => [
                'type' => 'object',
                'description' => 'Optional JSON body (sent as application/json). Mutually exclusive with body_form.',
            ],
            'body_form' => [
                'type' => 'object',
                'description' => 'Optional form body (sent as application/x-www-form-urlencoded). Mutually exclusive with body_json.',
            ],
            'bearer_token' => [
                'type' => 'string',
                'description' => 'Optional Bearer token for Authorization header.',
            ],
            'username' => [
                'type' => 'string',
                'description' => 'Username or email for HTTP Basic auth (the -u user in curl). Pass directly here, not inside a nested auth object.',
            ],
            'password' => [
                'type' => 'string',
                'description' => 'Password or API token for HTTP Basic auth (the -u password in curl). Pass directly here, not inside a nested auth object.',
            ],
            'timeout' => [
                'type' => 'integer',
                'description' => 'Request timeout in seconds. Default 30.',
            ],
            'follow_redirects' => [
                'type' => 'boolean',
                'description' => 'Whether to follow redirects. Default true.',
            ],
            'save_to_file' => [
                'type' => 'boolean',
                'description' => 'When true, saves the raw response body to a temporary file and returns the file path. Useful for large responses or binary content that you want to deliver to the user via the send_file tool. Default false.',
            ],
        ];
    }

    public function required(): array
    {
        return ['method', 'url'];
    }

    public function execute(array $parameters): ToolResult
    {
        $method = strtoupper($parameters['method'] ?? '');
        $timeout = (int) ($parameters['timeout'] ?? 30);

        $previousLimit = (int) ini_get('max_execution_time');
        set_time_limit(max(120, $timeout + 30));

        try {
            $url = $parameters['url'] ?? null;
            $followRedirects = $parameters['follow_redirects'] ?? true;

            if (! $url) {
                return ToolResult::error('url is required.');
            }

            if (! $method) {
                return ToolResult::error('method is required.');
            }
            $extraHeaders = $parameters['headers'] ?? [];
            if (! is_array($extraHeaders)) {
                $extraHeaders = [];
            }

            $http = Http::timeout($timeout)
                ->withHeaders(array_merge(
                    ['User-Agent' => 'Mozilla/5.0 (compatible; SecretaryBot/1.0)'],
                    $extraHeaders,
                ));

            if ($followRedirects === false) {
                $http = $http->withoutRedirecting();
            }

            if (isset($parameters['bearer_token'])) {
                $http = $http->withToken($parameters['bearer_token']);
            }

            $basicUser = $parameters['username']
                ?? $parameters['basic_user']
                ?? $parameters['auth']['username']
                ?? $parameters['auth']['email']
                ?? $parameters['auth']['user']
                ?? null;

            $basicPass = $parameters['password']
                ?? $parameters['basic_password']
                ?? $parameters['auth']['password']
                ?? $parameters['auth']['token']
                ?? null;

            if ($basicUser) {
                $http = $http->withBasicAuth($basicUser, $basicPass ?? '');
            }

            if (isset($parameters['query']) && is_array($parameters['query'])) {
                $url = $url.'?'.http_build_query($parameters['query']);
            }

            $response = match ($method) {
                'GET', 'DELETE' => $http->{strtolower($method)}($url),
                'POST', 'PUT', 'PATCH' => isset($parameters['body_form']) && is_array($parameters['body_form'])
                    ? $http->asForm()->{strtolower($method)}($url, $parameters['body_form'])
                    : $http->asJson()->{strtolower($method)}($url, is_array($parameters['body_json'] ?? []) ? ($parameters['body_json'] ?? []) : []),
                default => throw new \InvalidArgumentException("Unsupported method: {$method}"),
            };

            $saveToFile = $parameters['save_to_file'] ?? false;

            $disposition = $response->header('Content-Disposition') ?? '';
            if (! $saveToFile && preg_match('/attachment;\s*filename="?([^";\n]+)"?/i', $disposition, $matches)) {
                $saveToFile = true;
                $dispositionFilename = trim($matches[1]);
            }

            if ($saveToFile) {
                $tmpPath = sys_get_temp_dir().'/secretary_'.Str::uuid();
                file_put_contents($tmpPath, $response->body());

                $result = [
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'saved_to' => $tmpPath,
                    'size_bytes' => strlen($response->body()),
                    'hint' => 'Use the send_file tool with this file path to deliver the file to the user. Read the file content with file_get_contents() or pass it as base64.',
                ];

                if (isset($dispositionFilename)) {
                    $result['suggested_filename'] = $dispositionFilename;
                }

                return ToolResult::success($result);
            }

            $contentType = $response->header('Content-Type') ?? '';
            $isJson = str_contains($contentType, 'json');
            $isText = str_contains($contentType, 'text') || str_contains($contentType, 'xml') || str_contains($contentType, 'javascript');

            $body = match (true) {
                $isJson => $response->json(),
                $isText => mb_substr($response->body(), 0, 8000),
                default => '[binary content, '.strlen($response->body()).' bytes]',
            };

            return ToolResult::success([
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $body,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        } finally {
            set_time_limit($previousLimit);
        }
    }
}
