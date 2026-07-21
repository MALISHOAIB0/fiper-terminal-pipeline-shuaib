<?php

namespace Database\Seeders;

use App\Models\PageContent;
use Illuminate\Database\Seeder;

class PageContentSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['home', 'hero_title', 'Markets, decoded.', 'الأسواق، بوضوح.'],
            ['home', 'hero_subtitle', 'Live prices, AI-generated briefs, and Shariah screening across 81 instruments — forex, crypto, metals, stocks, indices, and commodities.', 'أسعار حية، وموجزات بالذكاء الاصطناعي، وفحص شرعي عبر 81 أداة مالية — فوركس وعملات رقمية ومعادن وأسهم ومؤشرات وسلع.'],
            ['markets', 'page_title', 'Markets', 'الأسواق'],
            ['markets', 'page_subtitle', 'All 81 instruments, live prices and AI sentiment at a glance.', 'كل الأدوات الـ81، بأسعارها الحية وتوجهها حسب الذكاء الاصطناعي دفعة واحدة.'],
            ['heatmap', 'page_title', 'Heatmap', 'الخريطة الحرارية'],
            ['heatmap', 'page_subtitle', "Today's move across every instrument, grouped by asset class.", 'حركة اليوم عبر كل أداة، مجمّعة حسب فئة الأصل.'],
        ];

        foreach ($rows as [$slug, $key, $en, $ar]) {
            PageContent::updateOrCreate(
                ['page_slug' => $slug, 'field_key' => $key],
                ['value_en' => $en, 'value_ar' => $ar],
            );
        }
    }
}
