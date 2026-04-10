<?php

use App\Models\Memory;
use App\Services\Tools\MemoryReadTool;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('list returns all memory keys with metadata', function () {
    Memory::remember('api_key', 'secret', 'credential', true, 'My API key');
    Memory::remember('user_name', 'Jerry', 'fact', false, 'Preferred name');

    $tool = new MemoryReadTool;
    $result = $tool->execute(['action' => 'list']);

    expect($result->success)->toBeTrue();
    $keys = collect($result->data['keys']);
    expect($result->data['count'])->toBe(2);
    expect($keys->pluck('key')->all())->toContain('api_key', 'user_name');
    // sensitive values not exposed by list
    $apiKeyEntry = $keys->firstWhere('key', 'api_key');
    expect($apiKeyEntry['sensitive'])->toBeTrue();
    expect($apiKeyEntry)->not->toHaveKey('value');
});

test('read returns the value for a known key', function () {
    Memory::remember('greeting', 'Hello', 'fact');

    $tool = new MemoryReadTool;
    $result = $tool->execute(['action' => 'read', 'key' => 'greeting']);

    expect($result->success)->toBeTrue();
    expect($result->data['value'])->toBe('Hello');
});

test('read returns error for unknown key', function () {
    $tool = new MemoryReadTool;
    $result = $tool->execute(['action' => 'read', 'key' => 'nonexistent']);

    expect($result->success)->toBeFalse();
});

test('read returns error when key is omitted', function () {
    $tool = new MemoryReadTool;
    $result = $tool->execute(['action' => 'read']);

    expect($result->success)->toBeFalse();
});
