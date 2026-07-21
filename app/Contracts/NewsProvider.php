<?php

namespace App\Contracts;

interface NewsProvider
{
    /**
     * @param  array<int, string>  $symbols
     * @return array<int, array{uuid: string, title: string, summary: string, source: string, url: string, published_at: string, related_symbols: array<int, string>}>
     */
    public function fetchNewsForSymbols(array $symbols, int $limit = 10): array;
}
