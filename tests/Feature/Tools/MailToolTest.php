<?php

use App\Models\Connection;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Connections\ConnectionManager;
use App\Services\Connections\Contracts\MailConnector;
use App\Services\Tools\MailTool;

function createMailTool(MailConnector $connector): MailTool
{
    $manager = Mockery::mock(ConnectionManager::class);
    $manager->shouldReceive('resolveMailConnector')->andReturn($connector);

    return new MailTool($manager);
}

function createConversationWithMailConnection(): Conversation
{
    $user = User::factory()->create();
    Connection::factory()->create(['user_id' => $user->id, 'type' => 'mail', 'provider' => 'gmail']);

    return Conversation::create([
        'user_id' => $user->id,
        'channel' => 'web',
        'title' => 'Test',
    ]);
}

test('list returns recent emails', function () {
    $connector = Mockery::mock(MailConnector::class);
    $connector->shouldReceive('list')->with(15)->andReturn([
        ['uid' => 1, 'subject' => 'Hello', 'from' => 'bob@example.com', 'date' => '2026-04-11 10:00:00', 'snippet' => 'Hey there', 'is_read' => false],
    ]);

    $tool = createMailTool($connector);
    $tool->setConversation(createConversationWithMailConnection());

    $result = $tool->execute(['action' => 'list']);

    expect($result->success)->toBeTrue();
    expect($result->data)->toHaveCount(1);
    expect($result->data[0])->subject->toBe('Hello');
});

test('read returns error when uid is missing', function () {
    $connector = Mockery::mock(MailConnector::class);

    $tool = createMailTool($connector);
    $tool->setConversation(createConversationWithMailConnection());

    $result = $tool->execute(['action' => 'read']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('uid');
});

test('read returns full email by uid', function () {
    $connector = Mockery::mock(MailConnector::class);
    $connector->shouldReceive('read')->with(42)->andReturn([
        'uid' => 42,
        'subject' => 'Important',
        'from' => 'alice@example.com',
        'to' => 'user@example.com',
        'date' => '2026-04-11 09:00:00',
        'body' => 'Full email body here',
        'attachments' => [],
    ]);

    $tool = createMailTool($connector);
    $tool->setConversation(createConversationWithMailConnection());

    $result = $tool->execute(['action' => 'read', 'uid' => 42]);

    expect($result->success)->toBeTrue();
    expect($result->data)->subject->toBe('Important');
});

test('search returns error when query is missing', function () {
    $connector = Mockery::mock(MailConnector::class);

    $tool = createMailTool($connector);
    $tool->setConversation(createConversationWithMailConnection());

    $result = $tool->execute(['action' => 'search']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('query');
});

test('search returns matching emails', function () {
    $connector = Mockery::mock(MailConnector::class);
    $connector->shouldReceive('search')->with('invoice', 10)->andReturn([
        ['uid' => 5, 'subject' => 'Your Invoice', 'from' => 'billing@example.com', 'date' => '2026-04-10 14:00:00', 'snippet' => 'Invoice attached'],
    ]);

    $tool = createMailTool($connector);
    $tool->setConversation(createConversationWithMailConnection());

    $result = $tool->execute(['action' => 'search', 'query' => 'invoice', 'limit' => 10]);

    expect($result->success)->toBeTrue();
    expect($result->data)->toHaveCount(1);
});

test('draft returns error when required fields are missing', function () {
    $connector = Mockery::mock(MailConnector::class);

    $tool = createMailTool($connector);
    $tool->setConversation(createConversationWithMailConnection());

    $result = $tool->execute(['action' => 'draft', 'to' => 'bob@example.com']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('subject');
});

test('draft creates a draft email', function () {
    $connector = Mockery::mock(MailConnector::class);
    $connector->shouldReceive('draft')
        ->with('bob@example.com', 'Meeting', 'Let us meet tomorrow')
        ->andReturn(['success' => true, 'message' => 'Draft created: "Meeting" to bob@example.com']);

    $tool = createMailTool($connector);
    $tool->setConversation(createConversationWithMailConnection());

    $result = $tool->execute([
        'action' => 'draft',
        'to' => 'bob@example.com',
        'subject' => 'Meeting',
        'body' => 'Let us meet tomorrow',
    ]);

    expect($result->success)->toBeTrue();
    expect($result->data)->message->toContain('Draft created');
});

test('returns error when no mail connection exists', function () {
    $user = User::factory()->create();
    $conversation = Conversation::create([
        'user_id' => $user->id,
        'channel' => 'web',
        'title' => 'Test',
    ]);

    $manager = new ConnectionManager;
    $tool = new MailTool($manager);
    $tool->setConversation($conversation);

    $result = $tool->execute(['action' => 'list']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('No active mail connection');
});

test('returns error for unknown action', function () {
    $connector = Mockery::mock(MailConnector::class);

    $tool = createMailTool($connector);
    $tool->setConversation(createConversationWithMailConnection());

    $result = $tool->execute(['action' => 'delete']);

    expect($result->success)->toBeFalse();
    expect($result->error)->toContain('Unknown action');
});
