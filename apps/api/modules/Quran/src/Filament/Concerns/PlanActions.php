<?php

namespace Modules\Quran\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Modules\Enrollment\Contracts\EnrollmentApi;
use Modules\Quran\Actions\Plans\AdjustQuranPlan;
use Modules\Quran\Actions\Plans\StartQuranPlan;
use Modules\Quran\Contracts\QuranApi;
use Modules\Quran\Domain\Programs\ProgramRules;
use Modules\Quran\Models\QuranPlan;
use Modules\Quran\Models\QuranProgram;
use Modules\Quran\Programs\Plans;
use Modules\Quran\Support\Amounts;
use Modules\SharedKernel\Authorization\Ability;

/**
 * Starting a course's Quran plan (the office: a program, where the learner
 * stands, where they start and their own pace; StartQuranPlan) and adjusting
 * it (AdjustQuranPlan: the pace, and for the office where they are), as
 * panels at the side of a screen. The page says how to refresh after.
 */
trait PlanActions
{
    abstract protected function refreshPlans(): void;

    protected function startPlan(): Action
    {
        $p = 'quran::screens.plan.';

        return Action::make('startPlan')->label(__($p.'start'))->icon(Heroicon::OutlinedPlayCircle)->size('sm')
            ->authorize(fn () => Gate::allows(Ability::ManageQuran->value))
            ->slideOver()->modalWidth(Width::Large)
            ->modalHeading(__($p.'start_heading'))->modalDescription(__($p.'start_hint'))->modalSubmitActionLabel(__($p.'start_submit'))
            ->schema([
                Select::make('program_id')->label(__($p.'program'))->required()->native(false)->live()
                    ->options(fn () => QuranProgram::query()->whereNull('archived_at')->orderBy('name')->get()
                        ->mapWithKeys(fn (QuranProgram $pr) => [$pr->id => $pr->name.' · '.__('quran::tracks.names.'.$pr->track)])->all())
                    ->helperText(function (Get $get) use ($p) {
                        $program = QuranProgram::query()->find($get('program_id'));

                        return $program === null ? __($p.'program_hint') : Amounts::pace($program->new_amount, $program->unit, $program->near_portions, $program->far_pages);
                    }),
                Section::make(__($p.'placement'))->description(__($p.'placement_hint'))->compact()
                    ->visible(fn () => app(QuranApi::class)->available())
                    ->schema([
                        Select::make('juz')->label(__('quran::hifz.attributes.juz'))->multiple()->native(false)
                            ->options(collect(range(1, 30))->mapWithKeys(fn (int $j) => [$j => __('quran::hifz.juz', ['juz' => $j])])->all()),
                        Select::make('surahs')->label(__('quran::hifz.attributes.surahs'))->multiple()->searchable()->native(false)->options(fn () => $this->surahOptions()),
                        Radio::make('strength')->label(__('quran::hifz.attributes.strength'))->options(trans('quran::hifz.marks'))->default('good')->inline(),
                    ]),
                Toggle::make('choose_start')->label(__($p.'choose_start'))->helperText(__($p.'choose_start_hint'))->live(),
                Grid::make(2)->visible(fn (Get $get) => (bool) $get('choose_start'))->schema($this->positionFields('start_surah', 'start_verse')),
                Section::make(__($p.'own_pace'))->description(__($p.'own_pace_hint'))->compact()->collapsed()->schema($this->paceFields()),
            ])
            ->action(function (array $data, array $arguments) use ($p) {
                $enrollment = (string) ($arguments['enrollment'] ?? '');
                $options = [
                    'placement' => ['juz' => array_map('intval', $data['juz'] ?? []), 'surahs' => array_map('intval', $data['surahs'] ?? []), 'strength' => (string) ($data['strength'] ?? 'good')],
                    'pace' => $this->pace($data),
                ];
                if (! empty($data['choose_start'])) {
                    $options['start'] = ['surah' => isset($data['start_surah']) ? (int) $data['start_surah'] : null, 'verse' => (int) ($data['start_verse'] ?? 0)];
                }
                Refusals::under(fn () => app(StartQuranPlan::class)->handle($enrollment, (string) $data['program_id'], $options, (string) auth()->id()),
                    ['program_id', 'juz', 'surahs', 'new_amount', 'near_portions', 'far_pages']);
                $this->refreshPlans();
                Notification::make()->success()->title(__($p.'started'))->send();
            });
    }

    protected function adjustPlan(bool $office): Action
    {
        $p = 'quran::screens.plan.';

        return Action::make('adjustPlan')->label(__($p.'adjust'))->icon(Heroicon::OutlinedAdjustmentsHorizontal)->size('sm')->color('gray')
            ->authorize(fn (array $arguments) => $this->mayAdjust($arguments, $office))
            ->slideOver()->modalWidth(Width::Large)
            ->modalHeading(__($p.'adjust_heading'))->modalSubmitActionLabel(__($p.'save'))
            ->modalDescription(fn (array $arguments) => ($plan = $this->planOf($arguments)) === null ? null
                : $plan->program->name.' · '.Amounts::pace($plan->program->new_amount, $plan->program->unit, $plan->program->near_portions, $plan->program->far_pages))
            ->fillForm(function (array $arguments) {
                $plan = $this->planOf($arguments);

                return $plan === null ? [] : [
                    'new_amount' => $plan->new_amount, 'near_portions' => $plan->near_portions, 'far_pages' => $plan->far_pages,
                    'position_surah' => $plan->position_surah, 'position_verse' => $plan->position_verse,
                ];
            })
            ->schema(fn (array $arguments) => [
                Callout::make(__($p.'group_note'))->info()->visible(fn () => $this->planOf($arguments)?->isGroup() ?? false),
                Section::make(__($p.'own_pace'))->description(__($p.'pace_hint'))->compact()->schema($this->paceFields($this->planOf($arguments))),
                Section::make(__($p.'position'))->description(__($p.'position_hint'))->compact()->visible($office && ! ($this->planOf($arguments)?->program->track === 'noorani'))
                    ->schema([Grid::make(2)->schema($this->positionFields('position_surah', 'position_verse'))]),
                Section::make(__($p.'position'))->description(__($p.'position_hint'))->compact()->visible($office && $this->planOf($arguments)?->program->track === 'noorani')
                    ->schema([TextInput::make('position_verse')->label(__($p.'lesson'))->numeric()->minValue(1)]),
            ])
            ->action(function (array $data, array $arguments) use ($office, $p) {
                $plan = $this->planOf($arguments) ?? abort(404);
                $changes = $this->pace($data, keepNull: true);
                if ($office && isset($data['position_verse']) && $data['position_verse'] !== '') {
                    $given = ['surah' => isset($data['position_surah']) && $data['position_surah'] !== '' ? (int) $data['position_surah'] : null, 'verse' => (int) $data['position_verse']];
                    if ($given !== ['surah' => $plan->position_surah, 'verse' => $plan->position_verse]) {
                        $changes['position'] = $given;
                    }
                }
                Refusals::under(fn () => app(AdjustQuranPlan::class)->handle($plan, $changes, (string) auth()->id()), ['new_amount', 'near_portions', 'far_pages', 'position']);
                $this->refreshPlans();
                Notification::make()->success()->title(__($p.'saved'))->send();
            });
    }

    protected function planOf(array $arguments): ?QuranPlan
    {
        $enrollment = (string) ($arguments['enrollment'] ?? '');

        return $enrollment === '' ? null : app(Plans::class)->forEnrollment($enrollment);
    }

    private function mayAdjust(array $arguments, bool $office): bool
    {
        $plan = $this->planOf($arguments);
        if ($plan === null) {
            return false;
        }
        if ($office) {
            return Gate::allows(Ability::ManageQuran->value);
        }
        $course = app(EnrollmentApi::class)->find((string) ($arguments['enrollment'] ?? ''));

        return ! $plan->isGroup() && $course !== null && $course->teacherId === auth()->id() && Gate::allows(Ability::TeachQuran->value);
    }

    /** @return list<mixed> */
    private function paceFields(?QuranPlan $plan = null): array
    {
        $p = 'quran::screens.plan.';
        $unit = $plan?->program->unit;
        [$min, $max, $step] = ProgramRules::AMOUNTS[$unit ?? 'page'] ?? [0.25, 50, 0.25];

        return [
            Grid::make(3)->schema([
                TextInput::make('new_amount')->label(__($p.'new_amount', ['unit' => $unit === null ? '' : __('quran::programs.units.'.$unit)]))
                    ->numeric()->minValue($unit === null ? 0 : $min)->maxValue($unit === null ? 60 : $max)->step($unit === null ? 0.25 : $step)
                    ->placeholder($plan === null ? __($p.'program_value') : Amounts::number($plan->program->new_amount))
                    ->visible($plan === null || $plan->program->track !== 'review'),
                TextInput::make('near_portions')->label(__($p.'near'))->numeric()->minValue(0)->maxValue(ProgramRules::NEAR_MAX)
                    ->placeholder($plan === null ? __($p.'program_value') : (string) $plan->program->near_portions),
                TextInput::make('far_pages')->label(__($p.'far'))->numeric()->minValue(0)->maxValue(ProgramRules::FAR_MAX)
                    ->placeholder($plan === null ? __($p.'program_value') : (string) $plan->program->far_pages),
            ]),
        ];
    }

    /** @return list<mixed> */
    private function positionFields(string $surah, string $verse): array
    {
        $p = 'quran::screens.plan.';

        return [
            Select::make($surah)->label(__($p.'surah'))->searchable()->native(false)->options(fn () => $this->surahOptions()),
            TextInput::make($verse)->label(__($p.'verse'))->numeric()->minValue(1),
        ];
    }

    /** @return array<int, string> */
    private function surahOptions(): array
    {
        return collect(app(QuranApi::class)->surahs())->mapWithKeys(fn (array $s) => [$s['number'] => $s['number'].'. '.(app()->getLocale() === 'ar' ? $s['name_ar'] : $s['name_en'])])->all();
    }

    /** @return array{new_amount?: ?float, near_portions?: ?int, far_pages?: ?int} the pace typed; blank goes back to the program's */
    private function pace(array $data, bool $keepNull = false): array
    {
        $pace = [];
        foreach (['new_amount', 'near_portions', 'far_pages'] as $field) {
            $value = $data[$field] ?? null;
            if ($value === null || $value === '') {
                if ($keepNull && array_key_exists($field, $data)) {
                    $pace[$field] = null;
                }

                continue;
            }
            $pace[$field] = $field === 'new_amount' ? (float) $value : (int) $value;
        }

        return $pace;
    }
}
