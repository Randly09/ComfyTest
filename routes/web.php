<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GenerateController;


Route::get('/', [
    GenerateController::class,
    'index'
])->name('generate.index');


Route::get('/generate', [
    GenerateController::class,
    'index'
]);


Route::post('/generate', [
    GenerateController::class,
    'generate'
])->name('generate');


Route::get('/generate/status/{promptId}', [
    GenerateController::class,
    'status'
])->name('generate.status');