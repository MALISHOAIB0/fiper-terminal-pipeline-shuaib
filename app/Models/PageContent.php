<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageContent extends Model
{
    protected $fillable = ['page_slug', 'field_key', 'value_en', 'value_ar'];

    public static function for(string $slug): array
    {
        return static::where('page_slug', $slug)
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->field_key => ['en' => $row->value_en, 'ar' => $row->value_ar],
            ])
            ->all();
    }
}
