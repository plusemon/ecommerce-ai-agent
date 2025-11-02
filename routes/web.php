<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::get('/', [ChatController::class, 'welcome'])->name('home');
Route::post('/chat', [ChatController::class, 'chat'])->name('chat');
