<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\GenerateController;
use Illuminate\Support\Facades\Route;

Route::get('/', [
    ChatController::class,
    'index',
])->name('chat.index');

Route::post('/chats', [
    ChatController::class,
    'store',
])->name('chats.store');

Route::get('/chats/{chat}', [
    ChatController::class,
    'show',
])->name('chats.show');

Route::delete('/chats/{chat}', [
    ChatController::class,
    'destroy',
])->name('chats.destroy');

Route::post('/generate', [
    GenerateController::class,
    'generate',
])->name('generate');

Route::get('/generate/status/{message}', [
    GenerateController::class,
    'status',
])->name('generate.status');

Route::get('/messages/{message}/media', [
    GenerateController::class,
    'media',
])->name('messages.media');
