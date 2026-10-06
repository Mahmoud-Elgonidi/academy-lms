<?php

namespace Modules\Billing\Filament\Resources\Invoices;

use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Filament\InvoiceActions;
use Modules\Billing\Models\Invoice;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Time\ViewerTimezone;

/** Invoices: what is due, what is late, what came in (docs/architecture/03, section 5). */
final class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = -2;

    protected static ?string $slug = 'invoices';

    protected static ?string $recordTitleAttribute = 'number';

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ViewBilling->value);
    }

    public static function getNavigationLabel(): string
    {
        return __('billing::invoices.nav');
    }

    public static function getModelLabel(): string
    {
        return __('billing::invoices.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('billing::invoices.nav');
    }

    public static function getNavigationBadge(): ?string
    {
        $late = Invoice::query()->where('status', 'issued')->where('due_at', '<', CarbonImmutable::now())->count();

        return $late > 0 ? (string) $late : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('billing::invoices.badge');
    }

    /** Invoices with the payer's and the student's names, for the list and its search. */
    public static function withNames(Builder $query): Builder
    {
        $on = fn (string $alias, string $key, string $from = 'invoices') => fn ($j) => $j->on("{$alias}.academy_id", '=', "{$from}.academy_id")->on("{$alias}.id", '=', $key);

        return $query
            ->select('invoices.*', 'payer.name as payer_name', 'student.name as student_name', 'subject.name_ar as subject_ar', 'subject.name_en as subject_en')
            ->join('billing_accounts as ba', $on('ba', 'invoices.billing_account_id'))
            ->join('users as payer', $on('payer', 'ba.payer_id'))
            ->leftJoin('subscriptions as sub', $on('sub', 'invoices.subscription_id'))
            ->leftJoin('enrollments as en', $on('en', 'sub.enrollment_id'))
            ->leftJoin('users as student', $on('student', 'en.student_id'))
            ->leftJoin('subjects as subject', $on('subject', 'en.subject_id'));
    }

    public static function table(Table $table): Table
    {
        $tz = app(ViewerTimezone::class)->effective();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => self::withNames($query))
            ->defaultSort('issued_at', 'desc')
            ->recordUrl(fn (Invoice $record) => self::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('number')->label(__('billing::invoices.fields.number'))->weight('semibold')->searchable(query: fn (Builder $query, string $search) => $query->where('invoices.number', 'ilike', "%{$search}%")),
                TextColumn::make('student_name')->label(__('billing::invoices.fields.student'))
                    ->state(fn (Invoice $r) => $r->getAttribute('student_name') ?? $r->getAttribute('payer_name'))
                    ->description(fn (Invoice $r) => self::forLine($r))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn ($w) => $w->where('student.name', 'ilike', "%{$search}%")->orWhere('payer.name', 'ilike', "%{$search}%"))),
                TextColumn::make('total_minor')->label(__('billing::invoices.fields.total'))
                    ->state(fn (Invoice $r) => $r->total()->format())
                    ->description(fn (Invoice $r) => $r->status === 'issued' && $r->paid_minor > 0 ? __('billing::invoices.left', ['amount' => $r->outstanding()->format()]) : null)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('invoices.total_minor', $direction)),
                TextColumn::make('status')->label(__('billing::invoices.fields.status'))->badge()
                    ->state(fn (Invoice $r) => self::statusKey($r))
                    ->formatStateUsing(fn (string $state) => __('billing::billing.statuses.'.$state))
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success', 'overdue' => 'danger', 'issued', 'partly_paid' => 'warning', default => 'gray',
                    }),
                TextColumn::make('issued_at')->label(__('billing::invoices.fields.issued_at'))
                    ->state(fn (Invoice $r) => $r->issued_at?->setTimezone($tz)->locale(app()->getLocale())->translatedFormat('j M Y'))
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('invoices.issued_at', $direction)),
                TextColumn::make('due_at')->label(__('billing::invoices.fields.due'))
                    ->state(fn (Invoice $r) => self::dueLine($r))
                    ->color(fn (Invoice $r) => $r->isOverdue() ? 'danger' : 'gray'),
            ])
            ->recordActions([InvoiceActions::recordPayment(), ViewAction::make()->label(__('billing::invoices.open'))])
            ->emptyStateHeading(__('billing::invoices.empty.title'))
            ->emptyStateDescription(__('billing::invoices.empty.hint'));
    }

    public static function statusKey(Invoice $invoice): string
    {
        return match (true) {
            $invoice->isOverdue() => 'overdue',
            $invoice->status === 'issued' && $invoice->paid_minor > 0 => 'partly_paid',
            default => $invoice->status,
        };
    }

    public static function forLine(Invoice $invoice): ?string
    {
        $subject = app()->getLocale() === 'ar' ? $invoice->getAttribute('subject_ar') : $invoice->getAttribute('subject_en');
        $payer = $invoice->getAttribute('student_name') && $invoice->getAttribute('student_name') !== $invoice->getAttribute('payer_name')
            ? __('billing::invoices.paid_by', ['name' => $invoice->getAttribute('payer_name')])
            : null;

        return implode(' · ', array_filter([$subject, $payer])) ?: null;
    }

    public static function dueLine(Invoice $invoice): ?string
    {
        if ($invoice->status === 'paid') {
            return __('billing::invoices.paid_on', ['date' => $invoice->paid_at?->setTimezone(app(ViewerTimezone::class)->effective())->locale(app()->getLocale())->translatedFormat('j M')]);
        }
        if ($invoice->status !== 'issued' || $invoice->due_at === null) {
            return null;
        }
        $days = (int) CarbonImmutable::now()->startOfDay()->diffInDays($invoice->due_at->startOfDay(), false);

        return match (true) {
            $days < 0 => trans_choice('billing::invoices.late_by', -$days, ['n' => -$days]),
            $days === 0 => __('billing::invoices.due_today'),
            default => trans_choice('billing::invoices.due_in', $days, ['n' => $days]),
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }
}
