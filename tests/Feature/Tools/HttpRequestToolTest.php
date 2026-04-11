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

test('auto saves to file when Content-Disposition attachment header is present', function () {
    Http::fake([
        'https://example.com/report' => Http::response('pdf-binary-content', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Toggl_Track_summary_report_2026-03-01_2026-03-31.pdf"',
        ]),
    ]);

    $tool = new HttpRequestTool;
    $result = $tool->execute([
        'method' => 'GET',
        'url' => 'https://example.com/report',
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data['saved_to'])->toStartWith(sys_get_temp_dir().'/secretary_');
    expect($result->data['suggested_filename'])->toBe('Toggl_Track_summary_report_2026-03-01_2026-03-31.pdf');
    expect($result->data['size_bytes'])->toBe(18);
    expect(file_get_contents($result->data['saved_to']))->toBe('pdf-binary-content');
    expect($result->data)->not->toHaveKey('body');

    @unlink($result->data['saved_to']);
});

test('does not auto save when Content-Disposition is inline', function () {
    Http::fake([
        'https://example.com/page' => Http::response('<html>hello</html>', 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline',
        ]),
    ]);

    $tool = new HttpRequestTool;
    $result = $tool->execute([
        'method' => 'GET',
        'url' => 'https://example.com/page',
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data)->not->toHaveKey('saved_to');
    expect($result->data['body'])->toBe('<html>hello</html>');
});
