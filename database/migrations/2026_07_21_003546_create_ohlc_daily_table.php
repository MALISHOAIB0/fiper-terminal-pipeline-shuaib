<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohlc_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('open', 18, 6);
            $table->decimal('high', 18, 6);
            $table->decimal('low', 18, 6);
            $table->decimal('close', 18, 6);
            $table->bigInteger('volume')->nullable();
            $table->timestamps();

            $table->unique(['instrument_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohlc_daily');
    }
};
