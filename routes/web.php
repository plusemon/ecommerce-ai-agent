<?php

use App\Http\Controllers\ChatController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'welcome'])->name('home');
Route::get('/conversations', [ChatController::class, 'getConversationsList'])->name('conversations');
Route::get('/conversations/{conversation}', [ChatController::class, 'welcome'])->name('conversations.show');
Route::delete('/conversations/{conversation}', [ChatController::class, 'deleteConversation'])->name('conversations.destroy');

Route::post('/send-message', [ChatController::class, 'sendMessage'])->name('conversations.send')->withoutMiddleware(
    VerifyCsrfToken::class
);
