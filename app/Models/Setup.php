<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setup extends Model
{
    protected $fillable = [
        'instrument_id', 'setup_type', 'detected_at', 'entry_price', 'sl', 'tp1', 'tp2', 'bias',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
