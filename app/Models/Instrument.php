<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends Model
{
    protected $fillable = [
        'symbol', 'name', 'short_name', 'name_localized',
        'asset_class', 'icon_letter', 'is_active', 'is_tier_one',
        'country', 'sector', 'shariah_status', 'shariah_screening_notes',
        'ai_brief_en', 'ai_brief_ar', 'ai_bias', 'analytics_refreshed_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_tier_one' => 'boolean',
        'ai_brief_en' => 'array',
        'ai_brief_ar' => 'array',
        'analytics_refreshed_at' => 'datetime',
    ];

    public function ohlcDaily(): HasMany
    {
        return $this->hasMany(OhlcDaily::class);
    }

    public function quoteSnapshots(): HasMany
    {
        return $this->hasMany(QuoteSnapshot::class);
    }

    public function setups(): HasMany
    {
        return $this->hasMany(Setup::class);
    }

    public function newsArticles(): BelongsToMany
    {
        return $this->belongsToMany(NewsArticle::class);
    }
}
