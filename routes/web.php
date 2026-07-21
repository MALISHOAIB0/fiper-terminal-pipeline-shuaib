<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\HeatmapController;
use App\Http\Controllers\InstrumentController;
use App\Http\Controllers\MarketsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/markets', [MarketsController::class, 'index'])->name('markets');

Route::get('/heatmap', [HeatmapController::class, 'index'])->name('heatmap');

Route::get('/instrument/{symbol}', [InstrumentController::class, 'show'])->name('instrument.show');
