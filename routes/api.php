<?php

use App\Http\Controllers\Api\GematriaApiController;
use Illuminate\Support\Facades\Route;

Route::get('/calculate', [GematriaApiController::class, 'calculate']);
Route::get('/explorer', [GematriaApiController::class, 'explorer']);
Route::get('/suggest', [GematriaApiController::class, 'suggest']);
Route::get('/hybrid-search', [GematriaApiController::class, 'hybridSearch']);
