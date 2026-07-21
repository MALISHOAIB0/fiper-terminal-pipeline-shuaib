<?php

return [
    // 'stub' = deterministic fake provider responses, no network calls, no keys needed.
    // 'live' = call the real TwelveData / Marketaux / Anthropic APIs.
    'provider_mode' => env('PIPELINE_PROVIDER_MODE', 'stub'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model_tier_one' => env('ANTHROPIC_MODEL_TIER_ONE', 'claude-opus-4-7'),
        'model_standard' => env('ANTHROPIC_MODEL_STANDARD', 'claude-haiku-4-5'),
    ],

    'twelvedata' => [
        'api_key' => env('TWELVEDATA_API_KEY'),
    ],

    'marketaux' => [
        'api_key' => env('MARKETAUX_API_KEY'),
    ],

    // Separate switch from provider_mode above: Kronos is an additive
    // forecasting layer, not a replacement for the market data / news /
    // brief providers, so it can go live independently of them.
    'forecast_mode' => env('FORECAST_PROVIDER_MODE', 'stub'),

    'kronos' => [
        'service_url' => env('KRONOS_SERVICE_URL'),
    ],
];
