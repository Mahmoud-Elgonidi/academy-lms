<?php

namespace Modules\Billing\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Models\AutoCharge;
use Modules\Billing\Models\PaymentMethod;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\Clock;
use Modules\SharedKernel\Time\ViewerTimezone;
use UnitEnum;

/**
 * The cards families saved while paying, and their automatic renewal
 * (docs/architecture/02, section 27, J5b), for whoever sees the academy's
 * money (billing.view): the payer, the card's brand, last four and expiry,
 * the payment account, whether automatic renewal is on, and the latest
 * automatic charge. Never a card's references at the gateway; nothing here
 * changes a card (that is the payer's).
 */
final class SavedCards extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'billing::filament.saved-cards';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = -1;

    protected static ?string $slug = 'billing/saved-cards';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewBilling->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return null;    // beside Invoices
    }

    public static function getNavigationLabel(): string
    {
        return __('billing::cards.admin.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('billing::cards.admin.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('billing::cards.admin.subtitle');
    }

    public function table(Table $table): Table
    {
        $tz = app(ViewerTimezone::class)->effective();
        $l = 'billing::cards.admin.';

        return $table
            ->query(PaymentMethod::query()->with('account')
                ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'payment_methods.academy_id')->on('u.id', '=', 'payment_methods.payer_id'))
                ->select('payment_methods.*', 'u.name as payer_name'))
            ->defaultSort('payment_methods.created_at', 'desc')
            ->columns([
                TextColumn::make('payer_name')->label(__($l.'payer'))->searchable('u.name')->weight('semibold'),
                TextColumn::make('last4')->label(__($l.'card'))->state(fn (PaymentMethod $m) => $m->label()),
                TextColumn::make('exp_year')->label(__($l.'expiry'))->state(fn (PaymentMethod $m) => sprintf('%02d/%d', $m->exp_month, $m->exp_year))
                    ->color(fn (PaymentMethod $m) => $m->revoked_at === null && $m->expiresBefore(now()->toImmutable()->addDays(30)) ? 'warning' : null),
                TextColumn::make('gateway_account_id')->label(__($l.'account'))->state(fn (PaymentMethod $m) => (string) $m->account?->label),
                TextColumn::make('auto_renew')->label(__($l.'auto_renew'))->badge()
                    ->state(fn (PaymentMethod $m) => $m->revoked_at === null && DB::table('billing_accounts')->where('payer_id', $m->payer_id)->where('auto_renew', true)->exists() ? 'on' : 'off')
                    ->formatStateUsing(fn (string $state) => __($l.$state))->color(fn (string $state) => $state === 'on' ? 'success' : 'gray')
                    ->description(function (PaymentMethod $m) use ($l, $tz) {
                        $charge = AutoCharge::query()->where('payment_method_id', $m->id)->latest()->first();

                        return $charge === null ? null : __($l.'charges').': '.__($l.'charge_statuses.'.$charge->status)
                            .($charge->nextAt() !== null ? ' · '.$charge->nextAt()->setTimezone($tz)->locale(app()->getLocale())->translatedFormat(Clock::pattern('D j M · H:i')) : '');
                    }),
                TextColumn::make('created_at')->label(__($l.'saved'))
                    ->state(fn (PaymentMethod $m) => $m->created_at->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y')),
                TextColumn::make('revoked_at')->label(__($l.'status'))->badge()
                    ->state(fn (PaymentMethod $m) => $m->revoked_at === null ? __($l.'live') : __($l.'revoked.'.$m->revoked_reason))
                    ->color(fn (PaymentMethod $m) => $m->revoked_at === null ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('live')->label(__($l.'filters.live'))->default(true)
                    ->queries(true: fn ($q) => $q->whereNull('payment_methods.revoked_at'), false: fn ($q) => $q->whereNotNull('payment_methods.revoked_at')),
            ]);
    }
}
