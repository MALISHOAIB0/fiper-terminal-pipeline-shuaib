<?php

namespace App\Filament\Resources\PageContents\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PageContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('page_slug')
                    ->options([
                        'home' => 'Home',
                        'markets' => 'Markets',
                        'heatmap' => 'Heatmap',
                    ])
                    ->required(),
                TextInput::make('field_key')
                    ->required(),
                Textarea::make('value_en')
                    ->label('Value (English)')
                    ->rows(4)
                    ->required(),
                Textarea::make('value_ar')
                    ->label('Value (Arabic)')
                    ->rows(4)
                    ->required(),
            ]);
    }
}
