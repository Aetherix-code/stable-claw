<?php

use App\Http\Controllers\Secretary\ChatController;
use App\Http\Controllers\Secretary\ConversationController;
use App\Http\Controllers\Secretary\MemoryController;
use App\Http\Controllers\Secretary\SkillController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Secretary – authenticated web routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {
    // Chat
    Route::get('secretary', [ChatController::class, 'index'])->name('secretary.chat.index');
    Route::post('secretary/conversations', [ChatController::class, 'store'])->name('secretary.chat.store');
    Route::get('secretary/conversations/{conversation}', [ChatController::class, 'show'])->name('secretary.chat.show');
    Route::post('secretary/conversations/{conversation}/send', [ChatController::class, 'send'])->name('secretary.chat.send');
    Route::get('secretary/conversations/{conversation}/poll', [ChatController::class, 'poll'])->name('secretary.chat.poll');
    Route::post('secretary/conversations/{conversation}/learn/start', [ChatController::class, 'startLearnMode'])->name('secretary.chat.learn.start');
    Route::post('secretary/conversations/{conversation}/learn/end', [ChatController::class, 'endLearnMode'])->name('secretary.chat.learn.end');
    Route::delete('secretary/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('secretary.conversations.destroy');

    // Skills library
    Route::get('secretary/skills', [SkillController::class, 'index'])->name('secretary.skills.index');
    Route::post('secretary/skills', [SkillController::class, 'store'])->name('secretary.skills.store');
    Route::patch('secretary/skills/{skill}', [SkillController::class, 'update'])->name('secretary.skills.update');
    Route::patch('secretary/skills/{skill}/rename', [SkillController::class, 'rename'])->name('secretary.skills.rename');
    Route::post('secretary/skills/{skill}/refine', [SkillController::class, 'refine'])->name('secretary.skills.refine');
    Route::delete('secretary/skills/{skill}', [SkillController::class, 'destroy'])->name('secretary.skills.destroy');

    // Memory vault
    Route::get('secretary/memories', [MemoryController::class, 'index'])->name('secretary.memories.index');
    Route::post('secretary/memories', [MemoryController::class, 'store'])->name('secretary.memories.store');
    Route::delete('secretary/memories/{memory}', [MemoryController::class, 'destroy'])->name('secretary.memories.destroy');
});
