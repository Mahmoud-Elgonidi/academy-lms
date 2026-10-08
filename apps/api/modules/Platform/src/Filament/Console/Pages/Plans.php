<?php

namespace Modules\Platform\Filament\Console\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
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
use Modules\Platform\Models\SaasPlan;
use Modules\Platform\Plans\PlanFormat;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Plans\Feature;
use Modules\SharedKernel\Plans\Limit;

/**
 * The plans the platform sells academies: each one's limits and areas, its
 * monthly price, how many academies are on it, and which one new academies
 * start on. Editing a plan changes it for every academy on it.
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
            'academies' => (int) ($on[$plan->id] ?? 0),
            'limits' => array_map(fn (Limit $l) => ['label' => __('shared::plans.limits.'.$l->value), 'value' => PlanFormat::amount($l, $plan->limit($l))], Limit::cases()),
            'features' => array_map(fn (Feature $f) => ['label' => $f->label(), 'on' => in_array($f->value, $plan->features, true)], Feature::cases()),
        ])->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new')->label(__('platform::plans.catalog.new'))->icon(Heroicon::Plus)
                ->modalHeading(__('platform::plans.catalog.new'))
                ->schema($this->form(...))
                ->fillForm(['features' => [], 'sort' => SaasPlan::query()->max('sort') + 1])
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

                return $plan->only(['code', 'name_ar', 'name_en', 'max_teachers', 'max_active_students', 'video_minutes', 'storage_mb', 'features', 'currency', 'is_default', 'sort']) + [
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
                ]),
            ]),
            CheckboxList::make('features')->label(__($l.'features'))->options(PlanFormat::features())->columns(2),
            Grid::make(3)->schema([
                TextInput::make('price')->label(__($l.'price'))->numeric()->minValue(0)->requiredWith('currency'),
                Select::make('currency')->label(__($l.'currency'))->options(['SAR' => 'SAR', 'USD' => 'USD', 'EGP' => 'EGP', 'AED' => 'AED', 'KWD' => 'KWD', 'EUR' => 'EUR'])->requiredWith('price')->native(false),
                TextInput::make('sort')->label(__($l.'sort'))->numeric()->integer()->minValue(0),
            ]),
            Toggle::make('is_default')->label(__($l.'default'))->helperText(__($l.'default_hint')),
            Toggle::make('archived')->label(__($l.'archived'))->helperText(__($l.'archived_hint')),
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
            'features' => array_values($data['features'] ?? []),
            'price_minor' => $price === null || $price === '' || empty($data['currency']) ? null : Money::parse((string) $price, (string) $data['currency'])->minor,
            'currency' => $price === null || $price === '' ? null : ($data['currency'] ?: null),
            'is_default' => (bool) ($data['is_default'] ?? false),
            'sort' => $num($data['sort'] ?? null),
            'archived' => (bool) ($data['archived'] ?? false),
        ]);
        Notification::make()->success()->title(__('platform::plans.catalog.saved'))->send();
        unset($this->plans);
    }
}
