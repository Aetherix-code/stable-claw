<?php

use App\Services\Tools\SendFileTool;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('it sends a file from inline content', function () {
    $tool = new SendFileTool;
    $result = $tool->execute([
        'filename' => 'report.csv',
        'content' => 'a,b,c',
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data['download_filename'])->toBe('report.csv');
    expect($result->data['size_bytes'])->toBe(5);
});

test('it sends a file from source_path', function () {
    $tmpFile = sys_get_temp_dir().'/secretary_test_'.uniqid();
    file_put_contents($tmpFile, 'hello from disk');

    $tool = new SendFileTool;
    $result = $tool->execute([
        'filename' => 'output.txt',
        'source_path' => $tmpFile,
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data['download_filename'])->toBe('output.txt');
    expect($result->data['size_bytes'])->toBe(15);

    @unlink($tmpFile);
});

test('it rejects source_path outside temp directory', function () {
    $tool = new SendFileTool;
    $result = $tool->execute([
        'filename' => 'evil.txt',
        'source_path' => '/etc/passwd',
    ]);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('system temp directory');
});

test('it returns error when neither content nor source_path provided', function () {
    $tool = new SendFileTool;
    $result = $tool->execute([
        'filename' => 'empty.txt',
    ]);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('content or source_path is required');
});
