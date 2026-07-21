<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('marketaux_uuid')->unique();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('source')->nullable();
            $table->string('url');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->index('published_at');
        });

        Schema::create('instrument_news_article', function (Blueprint $table) {
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->foreignId('news_article_id')->constrained()->cascadeOnDelete();
            $table->primary(['instrument_id', 'news_article_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_news_article');
        Schema::dropIfExists('news_articles');
    }
};
