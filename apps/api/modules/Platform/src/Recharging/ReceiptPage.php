<?php

namespace Modules\Platform\Recharging;

use Illuminate\Contracts\View\View;
use Modules\Platform\Metering\MeterFormat;
use Modules\Platform\Models\Recharging\PlatformReceipt;

/** What a receipt's page shows, in the reader's language (dates on UTC: the platform's own receipt). */
final class ReceiptPage
{
    public static function view(PlatformReceipt $receipt): View
    {
        $locale = app()->getLocale();

        return view('platform::recharge.receipt', [
            'receipt' => $receipt,
            'rows' => [
                'number' => $receipt->number,
                'date' => $receipt->paid_at->utc()->locale($locale)->translatedFormat('j F Y'),
                'seller' => $receipt->seller,
                'buyer' => $receipt->academy_name,
                'pack' => $receipt->packName($locale),
                'what' => $receipt->meter()->label($locale).' · +'.MeterFormat::amount($receipt->meter(), $receipt->amount),
                'method' => __('platform::recharge.methods.'.$receipt->method),
                'amount' => $receipt->price()->format($locale),
            ],
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'locale' => $locale,
        ]);
    }
}
