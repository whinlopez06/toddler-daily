<?php

use Illuminate\Support\Facades\Route;

Route::get('/auth/demo-options', [\App\Http\Controllers\DemoController::class, 'options'])->middleware('throttle:60,1');
Route::post('/auth/demo', [\App\Http\Controllers\DemoController::class, 'store'])
    ->middleware(['demo.access', 'throttle:demo-registration']);

Route::get('/', function () {
    return view('welcome');
});
