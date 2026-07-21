<?php

use App\Http\Controllers\InstrumentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/instrument/{symbol}', [InstrumentController::class, 'show'])->name('instrument.show');
