<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('setups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->string('setup_type');
            $table->timestamp('detected_at');
            $table->decimal('entry_price', 18, 6)->nullable();
            $table->decimal('sl', 18, 6)->nullable();
            $table->decimal('tp1', 18, 6)->nullable();
            $table->decimal('tp2', 18, 6)->nullable();
            $table->string('bias');
            $table->timestamps();

            $table->index('detected_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setups');
    }
};
