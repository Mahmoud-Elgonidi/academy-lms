<?php

namespace Modules\People\Filament\Teacher;

use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\People\Actions\Cards\SaveTeacherCard;
use Modules\People\Contracts\DTOs\PublicTeacherSubject;
use Modules\People\Models\TeacherPublicCard;
use Modules\People\Queries\PublicTeachers;

/**
 * "My card on the academy's page" in /teach: the teacher writes the card
 * families see about them (the name to show, a few words, a photo) and
 * switches it on or off; a preview beside the form shows it as the page
 * will. Whether the academy has taken it off is said, never why. The same
 * Action as everywhere (SaveTeacherCard).
 *
 * @property-read Schema $form
 */
final class MyCard extends Page
{
    protected string $view = 'people::filament.my-card';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 89;

    protected static ?string $slug = 'my-card';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->check() && DB::table('teacher_profiles')->where('user_id', auth()->id())->exists();
    }

    public static function getNavigationLabel(): string
    {
        return __('people::cards.nav');
    }

    public function getTitle(): string|Htmlable
    {
        return __('people::cards.title');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('people::cards.subtitle');
    }

    public function mount(): void
    {
        $card = $this->card();
        $this->form->fill([
            'shown' => $card?->shown ?? false,
            'display_name' => $card?->display_name ?? (string) auth()->user()?->getAttribute('name'),
            'bio' => $card?->bio,
            'remove_photo' => false,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $l = 'people::cards.fields.';

        return $schema->statePath('data')->components([
            Toggle::make('shown')->label(__($l.'shown'))->helperText(__($l.'shown_hint'))->live(),
            TextInput::make('display_name')->label(__($l.'display_name'))->helperText(__($l.'display_name_hint'))->required()->maxLength(60)->live(debounce: 400),
            Textarea::make('bio')->label(__($l.'bio'))->helperText(__($l.'bio_hint'))->rows(4)->maxLength(280)->live(debounce: 400),
            FileUpload::make('photo')->label(__($l.'photo'))->helperText(__($l.'photo_hint'))->image()->storeFiles(false)
                ->acceptedFileTypes(TeacherPublicCard::MIMES)->maxSize(TeacherPublicCard::MAX_KB),
            Toggle::make('remove_photo')->label(__($l.'remove_photo'))->visible(fn () => $this->card()?->photo_path !== null),
        ]);
    }

    /** @return array{name: string, bio: ?string, photo: ?string, subjects: list<PublicTeacherSubject>, state: string} the card as the page would show it now */
    #[Computed]
    public function preview(): array
    {
        $card = $this->card();
        $photo = ($this->data['remove_photo'] ?? false) ? null : $card?->photoUrl();
        $state = match (true) {
            $card === null => 'none',
            $card->hidden_by_academy => 'hidden',
            $card->shown => 'on',
            default => 'off',
        };

        return [
            'name' => trim((string) ($this->data['display_name'] ?? '')),
            'bio' => filled($this->data['bio'] ?? null) ? trim((string) $this->data['bio']) : null,
            'photo' => $photo,
            'subjects' => app(PublicTeachers::class)->subjectsOf((string) auth()->id()),
            'state' => $state,
        ];
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        $state = $this->form->getState();
        $photo = $state['photo'] ?? null;
        $photo = is_array($photo) ? (array_values($photo)[0] ?? null) : $photo;
        try {
            $card = app(SaveTeacherCard::class)->handle((string) auth()->id(), $state, $photo instanceof UploadedFile ? $photo : null, (bool) ($state['remove_photo'] ?? false));
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        // The card as kept (its words cleaned), and the photo field empty again.
        $this->form->fill(['shown' => $card->shown, 'display_name' => $card->display_name, 'bio' => $card->bio, 'remove_photo' => false]);
        unset($this->preview);
        Notification::make()->success()->title(__('people::cards.saved'))->send();
    }

    private function card(): ?TeacherPublicCard
    {
        return TeacherPublicCard::query()->where('teacher_id', auth()->id())->first();
    }
}
