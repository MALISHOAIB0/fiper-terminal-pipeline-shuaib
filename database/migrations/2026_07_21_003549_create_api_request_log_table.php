<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_request_log', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // anthropic, twelvedata, marketaux
            $table->string('endpoint');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('cache_hit')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_log');
    }
};
