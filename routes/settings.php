<?php

use App\Http\Controllers\Settings\ConnectionController;
use App\Http\Controllers\Settings\DataController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TelegramSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');

    Route::redirect('system', '/system/data');

    Route::get('system/data', [DataController::class, 'edit'])->name('data.edit');
    Route::get('system/data/export', [DataController::class, 'export'])->name('data.export');
    Route::post('system/data/import', [DataController::class, 'import'])->name('data.import');

    Route::get('system/telegram', [TelegramSettingsController::class, 'edit'])->name('telegram.settings.edit');
    Route::patch('system/telegram', [TelegramSettingsController::class, 'update'])->name('telegram.settings.update');

    Route::get('system/connections', [ConnectionController::class, 'edit'])->name('connections.edit');
    Route::post('system/connections', [ConnectionController::class, 'store'])->name('connections.store');
    Route::patch('system/connections/{connection}', [ConnectionController::class, 'update'])->name('connections.update');
    Route::delete('system/connections/{connection}', [ConnectionController::class, 'destroy'])->name('connections.destroy');
    Route::post('system/connections/{connection}/test', [ConnectionController::class, 'test'])->name('connections.test');
});
