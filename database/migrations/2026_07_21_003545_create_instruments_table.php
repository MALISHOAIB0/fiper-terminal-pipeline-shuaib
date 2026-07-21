<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instruments', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique();
            $table->string('name');
            $table->string('short_name');
            $table->string('name_localized')->nullable();
            $table->string('asset_class'); // forex, crypto, metals, stocks, indices, commodities
            $table->string('icon_letter', 4)->nullable();
            $table->boolean('is_active')->default(true);

            // Replaces the legacy hardcoded 10-symbol Tier-1 list: this is the
            // single flag that drives model selection (Opus vs Haiku) and
            // refresh cadence in the unified brief pipeline.
            $table->boolean('is_tier_one')->default(false);

            $table->string('country')->nullable();
            $table->string('sector')->nullable();
            $table->enum('shariah_status', ['compliant', 'non_compliant', 'mixed', 'unknown'])->nullable();
            $table->text('shariah_screening_notes')->nullable();

            // Structured AI brief JSON: title, summary, key_levels, sentiment, catalysts, risks, bias
            $table->jsonb('ai_brief_en')->nullable();
            $table->jsonb('ai_brief_ar')->nullable();
            $table->string('ai_bias')->nullable(); // bullish|bearish|neutral|lean_bullish|lean_bearish
            $table->timestamp('analytics_refreshed_at')->nullable();

            $table->timestamps();

            $table->index('asset_class');
            $table->index('is_tier_one');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instruments');
    }
};
