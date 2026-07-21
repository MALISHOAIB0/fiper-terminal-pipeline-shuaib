<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OhlcDaily extends Model
{
    protected $table = 'ohlc_daily';

    protected $fillable = ['instrument_id', 'date', 'open', 'high', 'low', 'close', 'volume'];

    protected $casts = [
        'date' => 'date',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
