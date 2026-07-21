<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NewsArticle extends Model
{
    protected $fillable = ['marketaux_uuid', 'title', 'summary', 'source', 'url', 'published_at'];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function instruments(): BelongsToMany
    {
        return $this->belongsToMany(Instrument::class);
    }
}
