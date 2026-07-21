<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiRequestLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'api_request_log';

    protected $fillable = [
        'provider', 'endpoint', 'response_code', 'response_time_ms', 'error_message', 'cache_hit',
    ];

    protected $casts = [
        'cache_hit' => 'boolean',
    ];
}
