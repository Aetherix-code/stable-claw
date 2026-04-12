<?php

namespace App\Services\Tools;

use App\Models\Conversation;
use App\Services\Tools\Contracts\NeedsConversationContext;
use App\Services\Tools\Contracts\Tool;
use App\Services\Tools\DTOs\ToolResult;
use Exception;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HeadlessBrowserTool extends Tool implements NeedsConversationContext
{
    private ?Browser $browser = null;

    private ?Page $page = null;

    private ?Conversation $conversation = null;

    public function setConversation(Conversation $conversation): void
    {
        $this->conversation = $conversation;
    }

    public function name(): string
    {
        return 'browser';
    }

    public function description(): string
    {
        return 'Control a headless Chrome browser. Actions: navigate (go to URL), click (click element by CSS selector), fill (type text into input), elements (scan page for interactive elements like inputs, selects, buttons, links — use this instead of read to understand the page), read (get full page text — prefer elements or execute_js when possible), screenshot (capture screenshot as base64 JPEG), pdf (generate PDF and return a download URL), scroll (scroll page), back (go back), close (close browser), wait (pause), execute_js (run JavaScript on the page and return the result).';
    }

    public function parameters(): array
    {
        return [
            'action' => [
                'type' => 'string',
                'enum' => ['navigate', 'click', 'fill', 'elements', 'read', 'screenshot', 'pdf', 'scroll', 'back', 'close', 'wait', 'execute_js'],
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
            'script' => [
                'type' => 'string',
                'description' => 'JavaScript code to execute on the page (for action=execute_js). Must be an expression or IIFE that returns a value.',
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
                'elements' => $this->elements(),
                'read' => $this->read(),
                'screenshot' => $this->screenshot(),
                'pdf' => $this->pdf(),
                'scroll' => $this->scroll(),
                'back' => $this->back(),
                'wait' => $this->wait((int) ($parameters['wait_seconds'] ?? 1)),
                'execute_js' => $this->executeJs($parameters['script'] ?? ''),
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
            $this->browser = $this->reconnectBrowser() ?? $this->startBrowser();
        }

        if ($this->page === null) {
            $pages = $this->browser->getPages();
            $this->page = $pages[0] ?? $this->browser->createPage();
        }

        return $this->page;
    }

    private function reconnectBrowser(): ?Browser
    {
        $uri = Cache::get($this->cacheKey());

        if ($uri === null) {
            return null;
        }

        try {
            return BrowserFactory::connectToBrowser($uri, [
                'sendSyncDefaultTimeout' => 30000,
            ]);
        } catch (Exception) {
            Cache::forget($this->cacheKey());

            return null;
        }
    }

    private function startBrowser(): Browser
    {
        $chromeBinary = config('services.chrome.binary', env('CHROME_BINARY', 'google-chrome'));

        $factory = new BrowserFactory($chromeBinary);
        $browser = $factory->createBrowser([
            'headless' => true,
            'noSandbox' => true,
            'keepAlive' => true,
            'windowSize' => [1920, 1080],
            'sendSyncDefaultTimeout' => 30000,
            'startupTimeout' => 30,
            'customFlags' => [
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--disable-setuid-sandbox',
                '--ignore-certificate-errors',
                '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            ],
        ]);

        Cache::put($this->cacheKey(), $browser->getSocketUri(), now()->addHour());

        return $browser;
    }

    private function cacheKey(): string
    {
        return 'browser:socket_uri:conversation:'.$this->conversation?->id;
    }

    private function navigate(string $url): ToolResult
    {
        $page = $this->getPage();
        $page->navigate($url)->waitForNavigation(Page::LOAD, 30000);

        return ToolResult::success([
            'url' => $page->getCurrentUrl(),
            'title' => $page->evaluate('document.title')->getReturnValue(),
        ]);
    }

    private function click(string $selector): ToolResult
    {
        $page = $this->getPage();
        $selector = addslashes($selector);

        $page->mouse()->find($selector)->click();
        sleep(1);

        return ToolResult::success(['clicked' => $selector]);
    }

    private function fill(string $selector, string $text): ToolResult
    {
        $page = $this->getPage();
        $this->click($selector);
        $page->keyboard()->typeText($text);

        return ToolResult::success(['filled' => $selector, 'text' => $text]);
    }

    private function elements(): ToolResult
    {
        $page = $this->getPage();

        $elements = $page->evaluate(<<<'JS'
            (() => {
                const sel = (el) => {
                    if (el.id) return '#' + CSS.escape(el.id);
                    if (el.name) return el.tagName.toLowerCase() + '[name="' + el.name + '"]';
                    const classes = [...el.classList].slice(0, 2).join('.');
                    if (classes) return el.tagName.toLowerCase() + '.' + classes;
                    return el.tagName.toLowerCase();
                };
                const vis = (el) => {
                    const r = el.getBoundingClientRect();
                    return r.width > 0 && r.height > 0 && getComputedStyle(el).visibility !== 'hidden';
                };
                const results = [];
                document.querySelectorAll('input, textarea, select, button, a[href], [role="button"]').forEach(el => {
                    if (!vis(el)) return;
                    const info = { tag: el.tagName.toLowerCase(), selector: sel(el) };
                    if (el.type) info.type = el.type;
                    if (el.name) info.name = el.name;
                    if (el.placeholder) info.placeholder = el.placeholder;
                    if (el.value && el.value.length < 100) info.value = el.value;
                    const label = el.labels?.[0]?.textContent?.trim()
                        || el.getAttribute('aria-label')
                        || el.getAttribute('title');
                    if (label) info.label = label.substring(0, 80);
                    const text = el.textContent?.trim();
                    if (text && text.length < 80 && ['button', 'a'].includes(info.tag)) info.text = text;
                    if (el.href) info.href = el.href;
                    if (el.tagName === 'SELECT') {
                        info.options = [...el.options].slice(0, 10).map(o => ({ value: o.value, text: o.text.substring(0, 50) }));
                    }
                    if (el.checked !== undefined) info.checked = el.checked;
                    if (el.disabled) info.disabled = true;
                    if (el.required) info.required = true;
                    results.push(info);
                });
                return results;
            })()
        JS)->getReturnValue();

        return ToolResult::success([
            'url' => $page->getCurrentUrl(),
            'title' => $page->evaluate('document.title')->getReturnValue(),
            'elements' => $elements,
        ]);
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

    private function executeJs(string $script): ToolResult
    {
        if ($script === '') {
            return ToolResult::error('The "script" parameter is required for execute_js.');
        }

        $page = $this->getPage();
        $result = $page->evaluate($script)->getReturnValue();

        return ToolResult::success(['result' => $result]);
    }

    private function close(): ToolResult
    {
        $this->page = null;
        $this->browser?->close();
        $this->browser = null;

        Cache::forget($this->cacheKey());

        return ToolResult::success(['closed' => true]);
    }
}
