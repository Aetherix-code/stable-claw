<?php

use App\Models\Conversation;
use App\Services\Tools\HeadlessBrowserTool;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use HeadlessChromium\Page\PageEvaluation;
use Illuminate\Support\Facades\Cache;

test('execute_js returns error when script is empty', function () {
    $tool = new HeadlessBrowserTool;

    $result = $tool->execute(['action' => 'execute_js']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toBe('The "script" parameter is required for execute_js.');
});

test('execute_js evaluates script and returns result', function () {
    $evaluation = Mockery::mock(PageEvaluation::class);
    $evaluation->shouldReceive('getReturnValue')->andReturn(42);

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('evaluate')->with('1 + 41')->andReturn($evaluation);

    $browser = Mockery::mock(Browser::class);

    $tool = new HeadlessBrowserTool;

    // Inject mocked browser and page via reflection
    $ref = new ReflectionClass($tool);
    $ref->getProperty('browser')->setValue($tool, $browser);
    $ref->getProperty('page')->setValue($tool, $page);

    $result = $tool->execute(['action' => 'execute_js', 'script' => '1 + 41']);

    expect($result->success)->toBeTrue();
    expect($result->data)->result->toBe(42);
});

test('close clears cached socket uri', function () {
    $conversation = Conversation::factory()->create();
    $cacheKey = 'browser:socket_uri:conversation:'.$conversation->id;

    Cache::put($cacheKey, 'ws://127.0.0.1:9222/devtools/browser/fake', now()->addHour());

    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('close')->once();

    $tool = new HeadlessBrowserTool;
    $tool->setConversation($conversation);

    $ref = new ReflectionClass($tool);
    $ref->getProperty('browser')->setValue($tool, $browser);

    $result = $tool->execute(['action' => 'close']);

    expect($result->success)->toBeTrue();
    expect(Cache::has($cacheKey))->toBeFalse();
});

test('reconnects to existing browser from cached socket uri', function () {
    $conversation = Conversation::factory()->create();
    $cacheKey = 'browser:socket_uri:conversation:'.$conversation->id;
    $fakeUri = 'ws://127.0.0.1:9222/devtools/browser/fake-id';

    Cache::put($cacheKey, $fakeUri, now()->addHour());

    $evaluation = Mockery::mock(PageEvaluation::class);
    $evaluation->shouldReceive('getReturnValue')->andReturn('Test Page');

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('evaluate')->with('document.title')->andReturn($evaluation);

    $browser = Mockery::mock(Browser::class);
    $browser->shouldReceive('getPages')->andReturn([$page]);

    BrowserFactory::shouldReceive('connectToBrowser')
        ->with($fakeUri, ['sendSyncDefaultTimeout' => 30000])
        ->andReturn($browser);

    $tool = new HeadlessBrowserTool;
    $tool->setConversation($conversation);

    // Access getPage via reflection to verify reconnection
    $ref = new ReflectionClass($tool);
    $method = $ref->getMethod('getPage');
    $method->setAccessible(true);
    $resultPage = $method->invoke($tool);

    expect($resultPage)->toBe($page);
});

test('elements scans interactive elements on the page', function () {
    $fakeElements = [
        ['tag' => 'input', 'selector' => '#email', 'type' => 'email', 'name' => 'email', 'placeholder' => 'Email', 'required' => true],
        ['tag' => 'input', 'selector' => '#password', 'type' => 'password', 'name' => 'password'],
        ['tag' => 'button', 'selector' => 'button.btn-primary', 'type' => 'submit', 'text' => 'Sign In'],
        ['tag' => 'a', 'selector' => 'a.forgot-link', 'text' => 'Forgot password?', 'href' => 'https://example.com/reset'],
    ];

    $elementsEval = Mockery::mock(PageEvaluation::class);
    $elementsEval->shouldReceive('getReturnValue')->andReturn($fakeElements);

    $titleEval = Mockery::mock(PageEvaluation::class);
    $titleEval->shouldReceive('getReturnValue')->andReturn('Login');

    $page = Mockery::mock(Page::class);
    $page->shouldReceive('evaluate')->withArgs(fn ($s) => str_contains($s, 'querySelectorAll'))->andReturn($elementsEval);
    $page->shouldReceive('evaluate')->with('document.title')->andReturn($titleEval);
    $page->shouldReceive('getCurrentUrl')->andReturn('https://example.com/login');

    $browser = Mockery::mock(Browser::class);

    $tool = new HeadlessBrowserTool;

    $ref = new ReflectionClass($tool);
    $ref->getProperty('browser')->setValue($tool, $browser);
    $ref->getProperty('page')->setValue($tool, $page);

    $result = $tool->execute(['action' => 'elements']);

    expect($result->success)->toBeTrue();
    expect($result->data)
        ->url->toBe('https://example.com/login')
        ->title->toBe('Login')
        ->elements->toHaveCount(4);

    expect($result->data['elements'][0])
        ->tag->toBe('input')
        ->selector->toBe('#email')
        ->type->toBe('email');

    expect($result->data['elements'][2])
        ->tag->toBe('button')
        ->text->toBe('Sign In');
});
