<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Modules\Platform\Actions\Plans\SavePlan;
use Modules\Platform\Actions\Plans\SaveTopUpPack;
use Modules\Platform\Models\Plans\TopUpPack;
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Plans\PlanFormat;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;

/**
 * The plans the platform sells academies: each one's limits and areas, its
 * monthly price, how many academies are on it, and which one new academies
 * start on. Editing a plan changes it for every academy on it. Below them,
 * the extra packs academies may ask for in a month (video minutes, AI uses).
 */
final class Plans extends Page
{
    protected string $view = 'platform::plans.catalog';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'plans';

    public static function getNavigationLabel(): string
    {
        return __('platform::plans.catalog.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('platform::plans.catalog.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('platform::plans.catalog.subtitle');
    }

    /** @return list<array<string, mixed>> */
    #[Computed]
    public function plans(): array
    {
        $on = DB::connection('pgsql_platform')->table('saas_subscriptions')->whereNull('ended_at')
            ->groupBy('plan_id')->selectRaw('plan_id, count(*) as academies')->pluck('academies', 'plan_id');

        return SaasPlan::query()->orderBy('archived_at')->orderBy('sort')->orderBy('code')->get()->map(fn (SaasPlan $plan) => [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name(),
            'other' => $plan->name(app()->getLocale() === 'ar' ? 'en' : 'ar'),
            'price' => PlanFormat::price($plan),
            'default' => $plan->is_default,
            'archived' => $plan->archived_at !== null,
            'on_site' => $plan->shown_on_site && $plan->archived_at === null,
            'highlight' => $plan->highlight,
            'academies' => (int) ($on[$plan->id] ?? 0),
            'limits' => array_map(fn (Limit $l) => ['label' => __('shared::plans.limits.'.$l->value), 'value' => PlanFormat::amount($l, $plan->limit($l))], Limit::cases()),
            'features' => array_map(fn (Feature $f) => ['label' => $f->label(), 'on' => in_array($f->value, $plan->features, true)], Feature::cases()),
        ])->all();
    }

    /** @return array<string, list<array<string, mixed>>> the extra packs by limit, offered first */
    #[Computed]
    public function packs(): array
    {
        $out = array_fill_keys(SaveTopUpPack::LIMITS, []);
        foreach (TopUpPack::query()->orderBy('archived_at')->orderBy('sort')->orderBy('amount')->get() as $p) {
            $out[$p->limit][] = [
                'id' => $p->id,
                'name' => $p->name(),
                'amount' => '+'.PlanFormat::amount($p->limitKind(), $p->amount),
                'price' => $p->pricesText() ?? __('platform::recharge.packs.no_price'),
                'archived' => $p->archived_at !== null,
            ];
        }

        return $out;
    }

    public function newPackAction(): Action
    {
        return Action::make('newPack')->label(__('platform::topups.packs.new'))->icon(Heroicon::Plus)->color('gray')->size('sm')
            ->modalHeading(__('platform::topups.packs.new'))
            ->schema($this->packForm(...))
            ->fillForm(fn (array $arguments) => ['limit' => $arguments['limit'] ?? 'video_minutes', 'sort' => (int) TopUpPack::query()->max('sort') + 1])
            ->action(fn (array $data) => $this->savePack(null, $data));
    }

    public function editPackAction(): Action
    {
        return Action::make('editPack')->label(__('platform::plans.catalog.edit'))->icon(Heroicon::OutlinedPencilSquare)->color('gray')->size('xs')->link()
            ->modalHeading(__('platform::topups.packs.edit'))
            ->schema($this->packForm(...))
            ->fillForm(function (array $arguments) {
                $pack = TopUpPack::query()->findOrFail($arguments['pack']);

                return $pack->only(['limit', 'amount', 'name_ar', 'name_en', 'sort']) + [
                    'prices' => array_map(fn (Money $m) => ['currency' => $m->currency, 'price' => $m->decimal()], $pack->priceList()),
                    'archived' => $pack->archived_at !== null,
                ];
            })
            ->action(fn (array $data, array $arguments) => $this->savePack($arguments['pack'], $data));
    }

    /** @return list<mixed> */
    private function packForm(): array
    {
        $l = 'platform::topups.packs.';

        return [
            Grid::make(2)->schema([
                Select::make('limit')->label(__($l.'limit'))->required()->native(false)
                    ->options(array_combine(SaveTopUpPack::LIMITS, array_map(fn (string $k) => __('shared::plans.limits.'.$k), SaveTopUpPack::LIMITS))),
                TextInput::make('amount')->label(__($l.'amount'))->helperText(__($l.'amount_hint'))->required()->numeric()->integer()->minValue(1),
                TextInput::make('name_ar')->label(__('platform::plans.catalog.form.name_ar'))->required()->maxLength(80),
                TextInput::make('name_en')->label(__('platform::plans.catalog.form.name_en'))->required()->maxLength(80),
                TextInput::make('sort')->label(__('platform::plans.catalog.form.sort'))->numeric()->integer()->minValue(0),
            ]),
            // A price per currency (none: an academy in that currency asks for the pack by transfer).
            Repeater::make('prices')->label(__('platform::recharge.packs.prices'))->helperText(__('platform::recharge.packs.prices_hint'))
                ->addActionLabel(__('platform::recharge.packs.add_price'))->defaultItems(0)->reorderable(false)->columns(2)
                ->maxItems(count(SaveTopUpPack::CURRENCIES))
                ->schema([
                    Select::make('currency')->label(__('platform::recharge.packs.currency'))->options(array_combine(SaveTopUpPack::CURRENCIES, SaveTopUpPack::CURRENCIES))
                        ->required()->native(false)->distinct(),
                    TextInput::make('price')->label(__('platform::recharge.packs.price'))->required()->numeric()->minValue(0.01),
                ]),
            Toggle::make('archived')->label(__($l.'archived'))->helperText(__($l.'archived_hint')),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function savePack(?string $packId, array $data): void
    {
        $prices = [];
        foreach ((array) ($data['prices'] ?? []) as $row) {
            if (! empty($row['currency']) && ($row['price'] ?? '') !== '' && $row['price'] !== null) {
                $prices[(string) $row['currency']] = Money::parse((string) $row['price'], (string) $row['currency'])->minor;
            }
        }
        app(SaveTopUpPack::class)->handle($packId, [
            'limit' => $data['limit'],
            'amount' => (int) $data['amount'],
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'prices' => $prices,
            'sort' => $data['sort'] === null || $data['sort'] === '' ? null : (int) $data['sort'],
            'archived' => (bool) ($data['archived'] ?? false),
        ]);
        Notification::make()->success()->title(__('platform::topups.packs.saved'))->send();
        unset($this->packs);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')->label(__('platform::plans.catalog.new'))->icon(Heroicon::Plus)
                ->modalHeading(__('platform::plans.catalog.new'))
                ->schema($this->form(...))
                ->fillForm(['features' => [], 'features_optional' => array_keys(PlanFormat::features('optional')), 'sort' => SaasPlan::query()->max('sort') + 1, 'shown_on_site' => true])
                ->action(fn (array $data) => $this->save(null, $data)),
        ];
    }

    public function editAction(): Action
    {
        return Action::make('edit')->label(__('platform::plans.catalog.edit'))->icon(Heroicon::OutlinedPencilSquare)->color('gray')->size('sm')
            ->modalHeading(fn (array $arguments) => __('platform::plans.catalog.edit').' · '.SaasPlan::query()->findOrFail($arguments['plan'])->name())
            ->schema($this->form(...))
            ->fillForm(function (array $arguments) {
                $plan = SaasPlan::query()->findOrFail($arguments['plan']);

                return $plan->only(['code', 'name_ar', 'name_en', 'max_teachers', 'max_active_students', 'video_minutes', 'storage_mb', 'ai_uses', 'currency', 'is_default', 'sort', 'shown_on_site', 'highlight', 'tagline_ar', 'tagline_en']) + [
                    'features' => array_values(array_intersect((array) $plan->features, array_keys(PlanFormat::features('paid')))),
                    'features_optional' => array_values(array_intersect((array) $plan->features, array_keys(PlanFormat::features('optional')))),
                    'price' => $plan->price_minor === null ? null : Money::of($plan->price_minor, (string) $plan->currency)->decimal(),
                    'archived' => $plan->archived_at !== null,
                ];
            })
            ->action(fn (array $data, array $arguments) => $this->save($arguments['plan'], $data));
    }

    /** @return list<mixed> */
    private function form(): array
    {
        $l = 'platform::plans.catalog.form.';
        $limit = fn (string $name, Limit $limit) => TextInput::make($name)->label(__('shared::plans.limits.'.$limit->value))
            ->numeric()->integer()->minValue(0)->placeholder(__('platform::plans.unlimited'));

        return [
            Grid::make(3)->schema([
                TextInput::make('code')->label(__('platform::plans.attributes.code'))->required()->maxLength(31)->regex('/^[a-z0-9][a-z0-9_-]{1,30}$/')->helperText(__($l.'code_hint')),
                TextInput::make('name_ar')->label(__($l.'name_ar'))->required()->maxLength(80),
                TextInput::make('name_en')->label(__($l.'name_en'))->required()->maxLength(80),
            ]),
            Section::make(__($l.'limits'))->description(__($l.'limits_hint'))->compact()->schema([
                Grid::make(2)->schema([
                    $limit('max_teachers', Limit::Teachers),
                    $limit('max_active_students', Limit::ActiveStudents),
                    $limit('video_minutes', Limit::VideoMinutes),
                    $limit('storage_mb', Limit::StorageMb)->suffix(__($l.'mb')),
                    // The month's uses of the AI helpers, while the plan has the AI area; at the limit they stop and the work goes on by hand.
                    $limit('ai_uses', Limit::AiUses)->helperText(__($l.'ai_uses_hint')),
                ]),
            ]),
            CheckboxList::make('features')->label(__($l.'features'))->options(PlanFormat::features('paid'))->columns(2),
            // The free areas: in a plan at first; an academy on it may still hide any of them (Settings › Academy modules).
            CheckboxList::make('features_optional')->label(__($l.'features_optional'))->helperText(__($l.'features_optional_hint'))->options(PlanFormat::features('optional'))->columns(2),
            Grid::make(3)->schema([
                TextInput::make('price')->label(__($l.'price'))->numeric()->minValue(0)->requiredWith('currency'),
                Select::make('currency')->label(__($l.'currency'))->options(['SAR' => 'SAR', 'USD' => 'USD', 'EGP' => 'EGP', 'AED' => 'AED', 'KWD' => 'KWD', 'EUR' => 'EUR'])->requiredWith('price')->native(false),
                TextInput::make('sort')->label(__($l.'sort'))->numeric()->integer()->minValue(0),
            ]),
            Toggle::make('is_default')->label(__($l.'default'))->helperText(__($l.'default_hint')),
            Toggle::make('archived')->label(__($l.'archived'))->helperText(__($l.'archived_hint')),
            Section::make(__($l.'site'))->description(__($l.'site_hint'))->compact()->schema([
                Grid::make(2)->schema([
                    Toggle::make('shown_on_site')->label(__($l.'shown_on_site')),
                    Toggle::make('highlight')->label(__($l.'highlight'))->helperText(__($l.'highlight_hint')),
                    TextInput::make('tagline_ar')->label(__($l.'tagline_ar'))->maxLength(120),
                    TextInput::make('tagline_en')->label(__($l.'tagline_en'))->maxLength(120),
                ]),
            ]),
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function save(?string $planId, array $data): void
    {
        $num = fn ($v) => $v === null || $v === '' ? null : (int) $v;
        $price = $data['price'] ?? null;
        app(SavePlan::class)->handle($planId, [
            'code' => strtolower(trim((string) $data['code'])),
            'name_ar' => $data['name_ar'],
            'name_en' => $data['name_en'],
            'max_teachers' => $num($data['max_teachers'] ?? null),
            'max_active_students' => $num($data['max_active_students'] ?? null),
            'video_minutes' => $num($data['video_minutes'] ?? null),
            'storage_mb' => $num($data['storage_mb'] ?? null),
            'ai_uses' => $num($data['ai_uses'] ?? null),
            'features' => array_values([...($data['features'] ?? []), ...($data['features_optional'] ?? [])]),
            'price_minor' => $price === null || $price === '' || empty($data['currency']) ? null : Money::parse((string) $price, (string) $data['currency'])->minor,
            'currency' => $price === null || $price === '' ? null : ($data['currency'] ?: null),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'sort' => $num($data['sort'] ?? null),
            'archived' => (bool) ($data['archived'] ?? false),
            'shown_on_site' => (bool) ($data['shown_on_site'] ?? false),
            'highlight' => (bool) ($data['highlight'] ?? false),
            'tagline_ar' => $data['tagline_ar'] ?? null,
            'tagline_en' => $data['tagline_en'] ?? null,
        ]);
        Notification::make()->success()->title(__('platform::plans.catalog.saved'))->send();
        unset($this->plans);
    }
}
