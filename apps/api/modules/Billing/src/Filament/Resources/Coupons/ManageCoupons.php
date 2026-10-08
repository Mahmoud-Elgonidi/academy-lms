<?php

namespace Modules\Billing\Filament\Resources\Coupons;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Billing\Actions\Discounts\SaveCoupon;

final class ManageCoupons extends ManageRecords
{
    protected static string $resource = CouponResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('billing::discounts.coupons.subtitle');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('billing::discounts.coupons.create'))
                ->modalWidth('2xl')
                ->using(fn (array $data) => CouponResource::attempt(fn () => app(SaveCoupon::class)->handle(null, CouponResource::data($data), (string) auth()->id())))
                ->successNotificationTitle(__('billing::discounts.coupons.saved')),
        ];
    }
}
