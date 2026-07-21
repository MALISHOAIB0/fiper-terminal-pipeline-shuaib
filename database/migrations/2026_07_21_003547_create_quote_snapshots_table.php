<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->timestamp('quoted_at');
            $table->decimal('price', 18, 6);
            $table->decimal('change', 18, 6)->nullable();
            $table->decimal('change_percent', 8, 4)->nullable();
            $table->bigInteger('volume')->nullable();
            $table->timestamps();

            $table->index(['instrument_id', 'quoted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_snapshots');
    }
};
