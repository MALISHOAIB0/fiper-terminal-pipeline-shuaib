<?php

namespace Database\Seeders;

use App\Models\Instrument;
use Illuminate\Database\Seeder;

class InstrumentSeeder extends Seeder
{
    /**
     * Full 81-instrument catalogue (7 forex-major + 8 forex-minor + 12 crypto +
     * 4 metals + 25 stocks + 15 indices + 10 commodities), matching the counts
     * and named examples in the original handover docs (05-DATA-PIPELINE.md).
     * The docs never shipped the actual symbol list, only category counts and a
     * handful of examples — this list was curated from scratch to match them,
     * including all 10 documented tier-one symbols.
     */
    public function run(): void
    {
        $instruments = array_merge(
            $this->forexMajor(),
            $this->forexMinor(),
            $this->crypto(),
            $this->metals(),
            $this->stocks(),
            $this->indices(),
            $this->commodities(),
        );

        foreach ($instruments as $data) {
            Instrument::updateOrCreate(['symbol' => $data['symbol']], $data);
        }
    }

    private function forexMajor(): array
    {
        $pairs = [
            ['EURUSD', 'Euro / US Dollar', 'EUR/USD', 'يورو/دولار أمريكي', true],
            ['GBPUSD', 'British Pound / US Dollar', 'GBP/USD', 'جنيه إسترليني/دولار أمريكي', false],
            ['USDJPY', 'US Dollar / Japanese Yen', 'USD/JPY', 'دولار أمريكي/ين ياباني', true],
            ['USDCHF', 'US Dollar / Swiss Franc', 'USD/CHF', 'دولار أمريكي/فرنك سويسري', false],
            ['USDCAD', 'US Dollar / Canadian Dollar', 'USD/CAD', 'دولار أمريكي/دولار كندي', false],
            ['AUDUSD', 'Australian Dollar / US Dollar', 'AUD/USD', 'دولار أسترالي/دولار أمريكي', false],
            ['NZDUSD', 'New Zealand Dollar / US Dollar', 'NZD/USD', 'دولار نيوزيلندي/دولار أمريكي', false],
        ];

        return $this->forexRows($pairs, 'forex');
    }

    private function forexMinor(): array
    {
        $pairs = [
            ['EURGBP', 'Euro / British Pound', 'EUR/GBP', 'يورو/جنيه إسترليني'],
            ['AUDNZD', 'Australian Dollar / New Zealand Dollar', 'AUD/NZD', 'دولار أسترالي/دولار نيوزيلندي'],
            ['GBPJPY', 'British Pound / Japanese Yen', 'GBP/JPY', 'جنيه إسترليني/ين ياباني'],
            ['EURJPY', 'Euro / Japanese Yen', 'EUR/JPY', 'يورو/ين ياباني'],
            ['GBPCHF', 'British Pound / Swiss Franc', 'GBP/CHF', 'جنيه إسترليني/فرنك سويسري'],
            ['EURAUD', 'Euro / Australian Dollar', 'EUR/AUD', 'يورو/دولار أسترالي'],
            ['CHFJPY', 'Swiss Franc / Japanese Yen', 'CHF/JPY', 'فرنك سويسري/ين ياباني'],
            ['AUDCAD', 'Australian Dollar / Canadian Dollar', 'AUD/CAD', 'دولار أسترالي/دولار كندي'],
        ];

        return $this->forexRows(array_map(fn ($p) => [...$p, false], $pairs), 'forex');
    }

    private function forexRows(array $pairs, string $assetClass): array
    {
        return array_map(fn ($p) => [
            'symbol' => $p[0],
            'name' => $p[1],
            'short_name' => $p[2],
            'name_localized' => $p[3],
            'asset_class' => $assetClass,
            'icon_letter' => strtoupper($p[0][0]),
            'is_tier_one' => $p[4],
        ], $pairs);
    }

    private function crypto(): array
    {
        $rows = [
            ['BTCUSDT', 'Bitcoin', 'Bitcoin', 'بيتكوين', true],
            ['ETHUSDT', 'Ethereum', 'Ethereum', 'إيثريوم', true],
            ['SOLUSDT', 'Solana', 'Solana', 'سولانا', false],
            ['BNBUSDT', 'BNB', 'BNB', 'بي إن بي', false],
            ['XRPUSDT', 'XRP', 'XRP', 'إكس آر بي', false],
            ['ADAUSDT', 'Cardano', 'Cardano', 'كاردانو', false],
            ['DOGEUSDT', 'Dogecoin', 'Dogecoin', 'دوجكوين', false],
            ['AVAXUSDT', 'Avalanche', 'Avalanche', 'أفالانش', false],
            ['DOTUSDT', 'Polkadot', 'Polkadot', 'بولكادوت', false],
            ['LINKUSDT', 'Chainlink', 'Chainlink', 'تشين لينك', false],
            ['TONUSDT', 'Toncoin', 'Toncoin', 'تون كوين', false],
            ['LTCUSDT', 'Litecoin', 'Litecoin', 'لايتكوين', false],
        ];

        return array_map(fn ($r) => [
            'symbol' => $r[0],
            'name' => $r[1],
            'short_name' => $r[2],
            'name_localized' => $r[3],
            'asset_class' => 'crypto',
            'icon_letter' => strtoupper($r[2][0]),
            'is_tier_one' => $r[4],
        ], $rows);
    }

    private function metals(): array
    {
        $rows = [
            ['XAUUSD', 'Gold Spot', 'Gold', 'الذهب', true],
            ['XAGUSD', 'Silver Spot', 'Silver', 'الفضة', false],
            ['XPTUSD', 'Platinum Spot', 'Platinum', 'البلاتين', false],
            ['XPDUSD', 'Palladium Spot', 'Palladium', 'البلاديوم', false],
        ];

        return array_map(fn ($r) => [
            'symbol' => $r[0],
            'name' => $r[1],
            'short_name' => $r[2],
            'name_localized' => $r[3],
            'asset_class' => 'metals',
            'icon_letter' => strtoupper($r[2][0]),
            'is_tier_one' => $r[4],
        ], $rows);
    }

    /**
     * shariah_status is a good-faith approximation for seed/demo data (not a
     * ruling) — same disclaimer as the original handover pattern.
     */
    private function stocks(): array
    {
        $rows = [
            ['2222.SR', 'Saudi Aramco', 'Aramco', 'أرامكو السعودية', 'Saudi Arabia', 'Energy', 'compliant'],
            ['1120.SR', 'Al Rajhi Bank', 'Al Rajhi Bank', 'مصرف الراجحي', 'Saudi Arabia', 'Financials', 'compliant'],
            ['2010.SR', 'Saudi Basic Industries Corp', 'SABIC', 'سابك', 'Saudi Arabia', 'Materials', 'compliant'],
            ['7010.SR', 'Saudi Telecom Company', 'STC', 'الاتصالات السعودية', 'Saudi Arabia', 'Communication Services', 'compliant'],
            ['1180.SR', 'Saudi National Bank', 'SNB', 'البنك الأهلي السعودي', 'Saudi Arabia', 'Financials', 'non_compliant'],
            ['SABB.SR', 'Saudi Awwal Bank', 'SABB', 'البنك السعودي الأول', 'Saudi Arabia', 'Financials', 'non_compliant'],
            ['QIBK.QA', 'Qatar Islamic Bank', 'QIB', 'مصرف قطر الإسلامي', 'Qatar', 'Financials', 'compliant'],
            ['MARK.QA', 'Masraf Al Rayan', 'Al Rayan', 'مصرف الريان', 'Qatar', 'Financials', 'compliant'],
            ['QNBK.QA', 'Qatar National Bank', 'QNB', 'بنك قطر الوطني', 'Qatar', 'Financials', 'non_compliant'],
            ['FAB', 'First Abu Dhabi Bank', 'FAB', 'بنك أبوظبي الأول', 'United Arab Emirates', 'Financials', 'non_compliant'],
            ['DIB', 'Dubai Islamic Bank', 'DIB', 'بنك دبي الإسلامي', 'United Arab Emirates', 'Financials', 'compliant'],
            ['EMAAR', 'Emaar Properties', 'Emaar', 'إعمار العقارية', 'United Arab Emirates', 'Real Estate', 'mixed'],
            ['AAPL', 'Apple Inc.', 'Apple', 'آبل', 'United States', 'Technology', 'compliant'],
            ['MSFT', 'Microsoft Corp.', 'Microsoft', 'مايكروسوفت', 'United States', 'Technology', 'compliant'],
            ['NVDA', 'NVIDIA Corp.', 'Nvidia', 'إنفيديا', 'United States', 'Technology', 'compliant'],
            ['GOOGL', 'Alphabet Inc.', 'Alphabet', 'ألفابت (جوجل)', 'United States', 'Communication Services', 'mixed'],
            ['AMZN', 'Amazon.com Inc.', 'Amazon', 'أمازون', 'United States', 'Consumer Discretionary', 'mixed'],
            ['META', 'Meta Platforms Inc.', 'Meta', 'ميتا بلاتفورمز', 'United States', 'Communication Services', 'compliant'],
            ['TSLA', 'Tesla Inc.', 'Tesla', 'تسلا', 'United States', 'Consumer Discretionary', 'compliant'],
            ['JPM', 'JPMorgan Chase & Co.', 'JPMorgan', 'جي بي مورجان تشيس', 'United States', 'Financials', 'non_compliant'],
            ['XOM', 'Exxon Mobil Corp.', 'ExxonMobil', 'إكسون موبيل', 'United States', 'Energy', 'compliant'],
            ['JNJ', 'Johnson & Johnson', 'J&J', 'جونسون آند جونسون', 'United States', 'Health Care', 'compliant'],
            ['V', 'Visa Inc.', 'Visa', 'فيزا', 'United States', 'Financials', 'mixed'],
            ['WMT', 'Walmart Inc.', 'Walmart', 'وول مارت', 'United States', 'Consumer Staples', 'mixed'],
            ['BRK.B', 'Berkshire Hathaway Inc.', 'Berkshire', 'بيركشاير هاثاواي', 'United States', 'Financials', 'non_compliant'],
        ];

        return array_map(fn ($r) => [
            'symbol' => $r[0],
            'name' => $r[1],
            'short_name' => $r[2],
            'name_localized' => $r[3],
            'asset_class' => 'stocks',
            'icon_letter' => strtoupper($r[2][0]),
            'is_tier_one' => false,
            'country' => $r[4],
            'sector' => $r[5],
            'shariah_status' => $r[6],
            'shariah_screening_notes' => 'Research tool only — not a religious ruling. Consult a qualified scholar.',
        ], $rows);
    }

    private function indices(): array
    {
        $rows = [
            ['US500', 'S&P 500', 'S&P 500', 'مؤشر إس آند بي 500', true],
            ['US30', 'Dow Jones Industrial Average', 'Dow 30', 'مؤشر داو جونز الصناعي', false],
            ['US100', 'Nasdaq 100', 'Nasdaq 100', 'مؤشر ناسداك 100', false],
            ['UK100', 'FTSE 100', 'FTSE 100', 'مؤشر فوتسي 100', false],
            ['GER40', 'DAX 40', 'DAX 40', 'مؤشر داكس 40', false],
            ['FRA40', 'CAC 40', 'CAC 40', 'مؤشر كاك 40', false],
            ['JPN225', 'Nikkei 225', 'Nikkei 225', 'مؤشر نيكاي 225', false],
            ['HK50', 'Hang Seng Index', 'Hang Seng', 'مؤشر هانج سنج', false],
            ['AUS200', 'ASX 200', 'ASX 200', 'مؤشر إيه إس إكس 200', false],
            ['DXY', 'US Dollar Index', 'Dollar Index', 'مؤشر الدولار الأمريكي', true],
            ['US10Y', 'US 10-Year Treasury Yield', 'US 10Y', 'عائد سندات الخزانة الأمريكية 10 سنوات', true],
            ['VIX', 'CBOE Volatility Index', 'VIX', 'مؤشر التقلب VIX', true],
            ['TASI', 'Tadawul All Share Index', 'TASI', 'مؤشر تداول العام', false],
            ['DFMGI', 'Dubai Financial Market General Index', 'DFM General', 'مؤشر سوق دبي المالي العام', false],
            ['QSI', 'Qatar Exchange Index', 'QE Index', 'مؤشر بورصة قطر', false],
        ];

        return array_map(fn ($r) => [
            'symbol' => $r[0],
            'name' => $r[1],
            'short_name' => $r[2],
            'name_localized' => $r[3],
            'asset_class' => 'indices',
            'icon_letter' => strtoupper($r[2][0]),
            'is_tier_one' => $r[4],
        ], $rows);
    }

    private function commodities(): array
    {
        $rows = [
            ['BRENT', 'Brent Crude Oil', 'Brent', 'خام برنت', true],
            ['WTI', 'WTI Crude Oil', 'WTI', 'خام غرب تكساس الوسيط', false],
            ['NATGAS', 'Natural Gas', 'Natural Gas', 'الغاز الطبيعي', false],
            ['COPPER', 'Copper', 'Copper', 'النحاس', false],
            ['WHEAT', 'Wheat', 'Wheat', 'القمح', false],
            ['CORN', 'Corn', 'Corn', 'الذرة', false],
            ['SUGAR', 'Sugar', 'Sugar', 'السكر', false],
            ['COFFEE', 'Coffee', 'Coffee', 'القهوة', false],
            ['COTTON', 'Cotton', 'Cotton', 'القطن', false],
            ['SOYBEAN', 'Soybean', 'Soybean', 'فول الصويا', false],
        ];

        return array_map(fn ($r) => [
            'symbol' => $r[0],
            'name' => $r[1],
            'short_name' => $r[2],
            'name_localized' => $r[3],
            'asset_class' => 'commodities',
            'icon_letter' => strtoupper($r[2][0]),
            'is_tier_one' => $r[4],
        ], $rows);
    }
}
