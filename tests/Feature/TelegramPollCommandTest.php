<?php

use App\Models\TelegramSetting;

test('telegram:poll fails when bot token is not configured', function () {
    TelegramSetting::instance()->update(['bot_token' => null]);

    $this->artisan('telegram:poll')
        ->expectsOutputToContain('bot token is not configured')
        ->assertExitCode(1);
});
