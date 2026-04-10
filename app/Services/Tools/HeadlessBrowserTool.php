<?php

namespace App\Services\Tools;

use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;
use Exception;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HeadlessBrowserTool extends Tool
{
    private ?Browser $browser = null;

    private ?Page $page = null;

    public function name(): string
    {
        return 'browser';
    }

    public function description(): string
    {
        return 'Control a headless Chrome browser. Actions: navigate (go to URL), click (click element by CSS selector), fill (type text into input), read (get page text), screenshot (capture screenshot as base64 JPEG), pdf (generate PDF and return a download URL), scroll (scroll page), back (go back), close (close browser), wait (pause).';
    }

    public function parameters(): array
    {
        return [
            'action' => [
                'type' => 'string',
                'enum' => ['navigate', 'click', 'fill', 'read', 'screenshot', 'pdf', 'scroll', 'back', 'close', 'wait'],
                'description' => 'The browser action to perform.',
            ],
            'url' => [
                'type' => 'string',
                'description' => 'URL to navigate to (for action=navigate).',
            ],
            'selector' => [
                'type' => 'string',
                'description' => 'CSS selector for the target element (for click, fill).',
            ],
            'text' => [
                'type' => 'string',
                'description' => 'Text to type into an input (for action=fill).',
            ],
            'wait_seconds' => [
                'type' => 'integer',
                'description' => 'Seconds to wait (for action=wait). Default 1.',
            ],
        ];
    }

    public function required(): array
    {
        return ['action'];
    }

    public function execute(array $parameters): ToolResult
    {
        // Browser operations can legitimately take longer than the default PHP limit.
        // We suspend the limit for the duration of this tool call.
        $previousLimit = (int) ini_get('max_execution_time');
        set_time_limit(120);

        try {
            return match ($parameters['action']) {
                'navigate' => $this->navigate($parameters['url'] ?? ''),
                'click' => $this->click($parameters['selector'] ?? ''),
                'fill' => $this->fill($parameters['selector'] ?? '', $parameters['text'] ?? ''),
                'read' => $this->read(),
                'screenshot' => $this->screenshot(),
                'pdf' => $this->pdf(),
                'scroll' => $this->scroll(),
                'back' => $this->back(),
                'wait' => $this->wait((int) ($parameters['wait_seconds'] ?? 1)),
                'close' => $this->close(),
                default => ToolResult::error("Unknown action: {$parameters['action']}"),
            };
        } catch (Exception $e) {
            return ToolResult::error($e->getMessage());
        } finally {
            set_time_limit($previousLimit);
        }
    }

    private function getPage(): Page
    {
        if ($this->browser === null) {
            $chromeBinary = config('services.chrome.binary', env('CHROME_BINARY', 'google-chrome'));

            $factory = new BrowserFactory($chromeBinary);
            $this->browser = $factory->createBrowser([
                'headless' => true,
                'noSandbox' => true,
                'sendSyncDefaultTimeout' => 30000, // 30s per socket message
                'startupTimeout' => 30,            // 30s for Chrome to start
            ]);
        }

        if ($this->page === null) {
            $this->page = $this->browser->createPage();
        }

        return $this->page;
    }

    private function navigate(string $url): ToolResult
    {
        $page = $this->getPage();
        $page->navigate($url)->waitForNavigation(Page::LOAD, 10000);

        return ToolResult::success([
            'url' => $page->getCurrentUrl(),
            'title' => $page->evaluate('document.title')->getReturnValue(),
        ]);
    }

    private function click(string $selector): ToolResult
    {
        $page = $this->getPage();
        $page->mouse()->find($selector)->click();
        usleep(500000);

        return ToolResult::success(['clicked' => $selector]);
    }

    private function fill(string $selector, string $text): ToolResult
    {
        $page = $this->getPage();
        $page->mouse()->find($selector)->click();
        $page->keyboard()->typeRawKey('ctrl+a');
        $page->keyboard()->type($text);

        return ToolResult::success(['filled' => $selector, 'text' => $text]);
    }

    private function read(): ToolResult
    {
        $page = $this->getPage();

        $text = $page->evaluate('document.body.innerText')->getReturnValue();

        return ToolResult::success([
            'url' => $page->getCurrentUrl(),
            'title' => $page->evaluate('document.title')->getReturnValue(),
            'text' => mb_substr((string) $text, 0, 8000),
        ]);
    }

    private function pdf(): ToolResult
    {
        $filename = 'page-'.now()->format('Y-m-d-His').'.pdf';
        $path = 'secretary/files/'.Str::uuid().'/'.$filename;

        Storage::disk('public')->put(
            $path,
            $this->getPage()->pdf(['printBackground' => true])->getRawBinary()
        );

        return ToolResult::success([
            'download_url' => Storage::disk('public')->url($path),
            'download_filename' => $filename,
        ]);
    }

    private function screenshot(): ToolResult
    {
        $base64 = $this->getPage()->screenshot(['format' => 'jpeg', 'quality' => 75])->getBase64();

        return ToolResult::success(['screenshot_base64' => $base64, 'format' => 'image/jpeg']);
    }

    private function scroll(): ToolResult
    {
        $this->getPage()->evaluate('window.scrollBy(0, window.innerHeight)');

        return ToolResult::success(['scrolled' => true]);
    }

    private function back(): ToolResult
    {
        $this->getPage()->evaluate('history.back()');
        usleep(500000);

        return ToolResult::success(['back' => true]);
    }

    private function wait(int $seconds): ToolResult
    {
        sleep(max(1, min($seconds, 30)));

        return ToolResult::success(['waited' => $seconds]);
    }

    private function close(): ToolResult
    {
        $this->page = null;
        $this->browser?->close();
        $this->browser = null;

        return ToolResult::success(['closed' => true]);
    }
}
