<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;
use Illuminate\Support\Facades\Http;

class WebFetchTool extends Tool
{
    public function name(): string
    {
        return 'web_fetch';
    }

    public function description(): string
    {
        return 'Fetch the plain-text content of any public URL via HTTP GET. Returns the page title, final URL (after redirects), HTTP status code, and up to 8 000 characters of readable text content. Ideal for reading articles, documentation, or API responses without a browser.';
    }

    public function parameters(): array
    {
        return [
            'url' => [
                'type' => 'string',
                'description' => 'The URL to fetch.',
            ],
            'selector' => [
                'type' => 'string',
                'description' => 'Optional CSS selector to extract only a specific part of the page (e.g. "article", "main", "#content").',
            ],
        ];
    }

    public function required(): array
    {
        return ['url'];
    }

    public function execute(array $parameters): ToolResult
    {
        $url = $parameters['url'];
        $selector = $parameters['selector'] ?? null;

        try {
            $response = Http::timeout(20)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SecretaryBot/1.0)'])
                ->get($url);

            $html = $response->body();
            $status = $response->status();
            $finalUrl = $response->effectiveUri()?->__toString() ?? $url;

            // Parse title
            preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $titleMatch);
            $title = isset($titleMatch[1]) ? html_entity_decode(strip_tags($titleMatch[1]), ENT_QUOTES | ENT_HTML5) : null;

            // Strip scripts, styles, and nav/header/footer noise
            $clean = preg_replace('/<(script|style|noscript|nav|header|footer|aside)[^>]*>.*?<\/\1>/si', '', $html);

            // If a selector was requested, try to find that element
            if ($selector !== null) {
                $clean = $this->extractSelector($html, $selector) ?? $clean;
            }

            // Convert to plain text
            $text = strip_tags((string) $clean);
            $text = preg_replace('/\s{3,}/', "\n\n", (string) $text);
            $text = mb_substr(trim((string) $text), 0, 8000);

            return ToolResult::success([
                'url' => $finalUrl,
                'title' => $title,
                'status' => $status,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * Naively extract the first element matching a simple CSS selector (tag, #id, .class).
     */
    private function extractSelector(string $html, string $selector): ?string
    {
        // Map simple selectors to regex
        if (str_starts_with($selector, '#')) {
            $id = preg_quote(substr($selector, 1), '/');
            $pattern = '/<(\w+)[^>]+id=["\']'.$id.'["\'][^>]*>(.*?)<\/\1>/si';
        } elseif (str_starts_with($selector, '.')) {
            $cls = preg_quote(substr($selector, 1), '/');
            $pattern = '/<(\w+)[^>]+class=["\'][^"\']*'.$cls.'[^"\']*["\'][^>]*>(.*?)<\/\1>/si';
        } else {
            $tag = preg_quote($selector, '/');
            $pattern = '/<('.$tag.')[^>]*>(.*?)<\/\1>/si';
        }

        if (preg_match($pattern, $html, $m)) {
            return $m[0];
        }

        return null;
    }
}
