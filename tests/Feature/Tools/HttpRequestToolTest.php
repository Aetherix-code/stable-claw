<?php

use App\Services\Tools\HttpRequestTool;
use Illuminate\Support\Facades\Http;

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('save_to_file writes response body to a temp file', function () {
    Http::fake(['https://example.com/data' => Http::response('csv,content,here', 200, ['Content-Type' => 'text/csv'])]);

    $tool = new HttpRequestTool;
    $result = $tool->execute([
        'method' => 'GET',
        'url' => 'https://example.com/data',
        'save_to_file' => true,
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data['saved_to'])->toStartWith(sys_get_temp_dir().'/secretary_');
    expect($result->data['status'])->toBe(200);
    expect($result->data['size_bytes'])->toBe(16);
    expect(file_get_contents($result->data['saved_to']))->toBe('csv,content,here');

    @unlink($result->data['saved_to']);
});

test('save_to_file false returns body inline', function () {
    Http::fake(['https://example.com/api' => Http::response(['key' => 'value'], 200)]);

    $tool = new HttpRequestTool;
    $result = $tool->execute([
        'method' => 'GET',
        'url' => 'https://example.com/api',
        'save_to_file' => false,
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data['body'])->toBe(['key' => 'value']);
    expect($result->data)->not->toHaveKey('saved_to');
});
