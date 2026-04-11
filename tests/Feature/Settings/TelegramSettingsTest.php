<?php

use App\Models\TelegramSetting;
use App\Models\User;

test('telegram settings page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('telegram.settings.edit'));

    $response->assertOk();
});

test('telegram settings can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('telegram.settings.update'), [
            'bot_token' => 'test-bot-token-123',
            'allowed_usernames' => 'alice, bob, charlie',
            'conversation_timeout_minutes' => 60,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $settings = TelegramSetting::instance();
    expect($settings->bot_token)->toBe('test-bot-token-123')
        ->and($settings->allowed_usernames)->toBe(['alice', 'bob', 'charlie'])
        ->and($settings->conversation_timeout_minutes)->toBe(60);
});

test('telegram settings update preserves existing token when none provided', function () {
    $settings = TelegramSetting::instance();
    $settings->bot_token = 'original-token';
    $settings->save();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('telegram.settings.update'), [
            'bot_token' => null,
            'allowed_usernames' => '',
            'conversation_timeout_minutes' => 30,
        ]);

    $settings->refresh();
    expect($settings->bot_token)->toBe('original-token');
});

test('telegram settings validates timeout range', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('telegram.settings.update'), [
            'conversation_timeout_minutes' => 0,
        ]);

    $response->assertSessionHasErrors('conversation_timeout_minutes');

    $response = $this
        ->actingAs($user)
        ->patch(route('telegram.settings.update'), [
            'conversation_timeout_minutes' => 20000,
        ]);

    $response->assertSessionHasErrors('conversation_timeout_minutes');
});

test('guests cannot access telegram settings', function () {
    $this->get(route('telegram.settings.edit'))->assertRedirect(route('login'));
    $this->patch(route('telegram.settings.update'))->assertRedirect(route('login'));
});
