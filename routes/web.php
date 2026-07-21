<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstrumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/instrument/{symbol}', [InstrumentController::class, 'show'])->name('instrument.show');
