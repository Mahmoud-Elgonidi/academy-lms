<?php

namespace Modules\Billing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Billing\Actions\SaveBillingSettings;
use Modules\Billing\Actions\SavePlan;
use Modules\Billing\Actions\SavePriceRegions;
use Modules\Billing\Models\Plan;
use Modules\Billing\Models\PriceRegion;

/**
 * Demo prices: VAT 15% included (a Saudi academy), six regions, and the
 * block plans most academies sell: 4, 8 or 12 sessions of 30, 45 or 60 minutes.
 */
class DemoBillingSeeder extends Seeder
{
    public function run(SaveBillingSettings $settings, SavePriceRegions $regions, SavePlan $plans): void
    {
        // A Saudi academy registered for VAT: its invoices are simplified tax invoices with the ZATCA QR code.
        $settings->handle(15, true, '310123456700003', 'NR-', 3, 'مؤسسة النور التعليمية', true);

        $regions->handle([
            ['name_ar' => 'السعودية والخليج', 'name_en' => 'Saudi Arabia and the Gulf', 'currency' => 'SAR', 'is_default' => true],
            ['name_ar' => 'الإمارات', 'name_en' => 'UAE', 'currency' => 'AED'],
            ['name_ar' => 'بريطانيا', 'name_en' => 'United Kingdom', 'currency' => 'GBP'],
            ['name_ar' => 'أوروبا', 'name_en' => 'Europe', 'currency' => 'EUR'],
            ['name_ar' => 'أمريكا وكندا', 'name_en' => 'US and Canada', 'currency' => 'USD'],
            ['name_ar' => 'مصر', 'name_en' => 'Egypt', 'currency' => 'EGP'],
        ]);
        $region = PriceRegion::query()->pluck('id', 'currency');

        //  [name ar, name en, minutes, block, per week, [SAR, AED, GBP, EUR, USD, EGP]]
        $catalogue = [
            ['4 حصص · حصة في الأسبوع', '4 sessions · once a week', 30, 4, 1, [220, 215, 45, 52, 59, 1200]],
            ['8 حصص · حصتين في الأسبوع', '8 sessions · twice a week', 30, 8, 2, [400, 390, 82, 95, 107, 2200]],
            ['12 حصة · 3 في الأسبوع', '12 sessions · 3 a week', 30, 12, 3, [570, 560, 117, 135, 152, 3100]],
            ['4 حصص · حصة في الأسبوع', '4 sessions · once a week', 45, 4, 1, [300, 295, 62, 72, 80, 1650]],
            ['8 حصص · حصتين في الأسبوع', '8 sessions · twice a week', 45, 8, 2, [560, 550, 115, 133, 150, 3000]],
            ['8 حصص · حصتين في الأسبوع', '8 sessions · twice a week', 60, 8, 2, [720, 705, 148, 170, 192, 3900]],
            // A shared package: one balance for all of a student's subjects.
            ['12 حصة في أي مادة', '12 lessons · any subject', 30, 12, 3, [600, 590, 125, 145, 160, 3300], Plan::SHARED],
        ];
        foreach ($catalogue as $i => [$ar, $en, $minutes, $block, $perWeek, $amounts]) {
            $plan = $plans->handle(null, [
                'name_ar' => "{$ar} · {$minutes} دقيقة",
                'name_en' => "{$en} · {$minutes} min",
                'session_minutes' => $minutes,
                'sessions_per_block' => $block,
                'sessions_per_week' => $perWeek,
                'credit_scope' => $catalogue[$i][6] ?? 'enrollment',
                'prices' => array_map(fn (string $currency, int $amount) => ['price_region_id' => $region[$currency], 'amount' => $amount], ['SAR', 'AED', 'GBP', 'EUR', 'USD', 'EGP'], $amounts),
            ]);
            $plan->forceFill(['sort' => $i])->save();
        }
    }
}
