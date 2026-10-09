<?php

namespace Modules\Classroom\Filament\Pages;

use BackedEnum;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Modules\Classroom\Actions\Recordings\SaveRecordingSettings;
use Modules\Classroom\Actions\SaveClassroomSettings;
use Modules\Classroom\Models\ClassroomSettings;
use Modules\SharedKernel\Authorization\Ability;
use UnitEnum;

/**
 * Where the academy's lessons happen: its own classroom (LiveKit), or each
 * teacher's outside link as the fallback (docs/architecture/03, section 6);
 * whether students come in with their camera on (off for under-18s, or for
 * everyone; they can always switch it); and whether lessons are recorded, how, for how long, and who watches.
 *
 * @property-read Schema $form
 */
final class ClassroomSettingsPage extends Page
{
    protected string $view = 'classroom::filament.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?int $navigationSort = 85;

    protected static ?string $slug = 'settings/classroom';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Gate::allows(Ability::ManageClassroom->value);
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('platform::branding.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('classroom::classroom.settings.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('classroom::classroom.settings.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('classroom::classroom.settings.subtitle');
    }

    public function mount(): void
    {
        $settings = ClassroomSettings::current();
        $this->form->fill([
            'provider' => $settings->provider,
            'student_camera' => $settings->student_camera,
            'recording' => $settings->recording,
            'recording_media' => $settings->recording_media,
            'recording_days' => $settings->recording_days,
            'recording_family' => $settings->recording_family,
            'recording_teacher' => $settings->recording_teacher,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $r = 'classroom::recordings.settings.';
        $on = fn (Get $get) => $get('recording') !== 'off';

        return $schema->statePath('data')->components([
            Radio::make('provider')->label(__('classroom::classroom.settings.provider'))->required()
                ->options(collect(ClassroomSettings::PROVIDERS)->mapWithKeys(fn ($p) => [$p => __('classroom::classroom.settings.providers.'.$p)])),
            Radio::make('student_camera')->label(__('classroom::classroom.settings.student_camera'))->helperText(__('classroom::classroom.settings.student_camera_hint'))->required()
                ->options(collect(ClassroomSettings::STUDENT_CAMERA)->mapWithKeys(fn ($m) => [$m => __('classroom::classroom.settings.cameras.'.$m)])),
            Section::make(__($r.'section'))->description(__($r.'section_hint'))->schema([
                Radio::make('recording')->label(__($r.'fields.recording'))->required()->live()
                    ->options(collect(ClassroomSettings::RECORDING)->mapWithKeys(fn ($m) => [$m => __($r.'modes.'.$m)])),
                Radio::make('recording_media')->label(__($r.'fields.recording_media'))->required()->visible($on)
                    ->options(collect(ClassroomSettings::RECORDING_MEDIA)->mapWithKeys(fn ($m) => [$m => __($r.'media.'.$m)])),
                TextInput::make('recording_days')->label(__($r.'fields.recording_days'))->helperText(__($r.'days_hint'))->visible($on)
                    ->numeric()->integer()->required()->minValue(ClassroomSettings::RECORDING_DAYS[0])->maxValue(ClassroomSettings::RECORDING_DAYS[1])->suffix(__('classroom::recordings.settings.days')),
                Toggle::make('recording_family')->label(__($r.'fields.recording_family'))->visible($on),
                Toggle::make('recording_teacher')->label(__($r.'fields.recording_teacher'))->helperText(__($r.'teacher_hint'))->visible($on),
            ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $current = ClassroomSettings::current();
        app(SaveClassroomSettings::class)->handle($state['provider'], $state['student_camera'] ?? null);
        app(SaveRecordingSettings::class)->handle([
            'recording' => $state['recording'],
            'recording_media' => $state['recording_media'] ?? $current->recording_media,
            'recording_days' => $state['recording_days'] ?? $current->recording_days,
            'recording_family' => (bool) ($state['recording_family'] ?? $current->recording_family),
            'recording_teacher' => (bool) ($state['recording_teacher'] ?? $current->recording_teacher),
        ], (string) auth()->id());
        Notification::make()->success()->title(__('classroom::classroom.settings.saved'))->send();
    }
}
