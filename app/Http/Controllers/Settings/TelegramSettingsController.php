<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\TelegramSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TelegramSettingsController extends Controller
{
    /**
     * Show the Telegram settings page.
     */
    public function edit(): Response
    {
        $settings = TelegramSetting::instance();

        return Inertia::render('system/Telegram', [
            'settings' => [
                'bot_token_configured' => $settings->bot_token !== null,
                'allowed_usernames' => $settings->allowed_usernames ?? [],
                'conversation_timeout_minutes' => $settings->conversation_timeout_minutes,
            ],
        ]);
    }

    /**
     * Update the Telegram settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bot_token' => ['nullable', 'string', 'max:255'],
            'allowed_usernames' => ['nullable', 'string', 'max:1000'],
            'conversation_timeout_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
        ]);

        $settings = TelegramSetting::instance();

        // Only update token if a new value was provided (not the placeholder)
        if ($validated['bot_token'] !== null && $validated['bot_token'] !== '') {
            $settings->bot_token = $validated['bot_token'];
        }

        $usernames = array_values(array_filter(
            array_map('trim', explode(',', $validated['allowed_usernames'] ?? '')),
            fn (string $u) => $u !== ''
        ));

        $settings->allowed_usernames = $usernames;
        $settings->conversation_timeout_minutes = $validated['conversation_timeout_minutes'];
        $settings->save();

        return back()->with('success', 'Telegram settings updated.');
    }
}
