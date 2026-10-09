<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GenerateController;

// Homepage
Route::get('/', [GenerateController::class, 'index']);

// Generator page
Route::get('/generate', [GenerateController::class, 'index'])
    ->name('generate.index');

// Form submission
Route::post('/generate', [GenerateController::class, 'generate'])
    ->name('generate');