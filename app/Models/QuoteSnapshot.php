<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteSnapshot extends Model
{
    protected $fillable = ['instrument_id', 'quoted_at', 'price', 'change', 'change_percent', 'volume'];

    protected $casts = [
        'quoted_at' => 'datetime',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
