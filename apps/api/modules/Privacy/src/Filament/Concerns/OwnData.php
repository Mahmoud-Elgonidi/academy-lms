<?php

namespace Modules\Privacy\Filament\Concerns;

use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\People\Contracts\PeopleApi;
use Modules\Privacy\Actions\CancelDataRequest;
use Modules\Privacy\Actions\CorrectDetails;
use Modules\Privacy\Actions\RequestCorrection;
use Modules\Privacy\Actions\RequestDataExport;
use Modules\Privacy\Actions\RequestErasure;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\PrivacyRules;
use Modules\Privacy\Queries\DataRequests;
use Modules\Privacy\Queries\EditableDetails;
use Modules\Privacy\Queries\ErasurePreview;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * "My data", the same in /my and /teach: for each person this one may ask
 * about (themselves; a guardian also the learners they follow), their
 * details to correct on the spot (the name; a student's gender, country,
 * grade and who should teach them) and a correction request for the rest,
 * the last copy (being prepared, or ready to download until its day) and
 * the erasure (waiting, approved with its day, or refused with the
 * academy's reason), then every request, and the academy's public pages.
 * Follows the person's own live channel.
 */
trait OwnData
{
    abstract protected function side(): string;

    /** @return array<string, mixed> */
    #[Computed]
    public function mine(): array
    {
        $me = auth()->user();
        $id = (string) $me?->getAuthIdentifier();
        $requests = app(DataRequests::class);
        $rows = $requests->mine($id);
        $cards = [];
        $details = collect(app(EditableDetails::class)->forViewer($id, (string) $me?->name, $this->side()))->keyBy('id');
        foreach (app(PrivacyRules::class)->subjectsFor($id, (string) $me?->name, $this->side()) as $subject) {
            $theirs = array_values(array_filter($rows, fn (array $r) => $r['subject_id'] === $subject['id']));
            $cards[] = $subject + [
                'can_copy' => $requests->canCopy($subject['id']),
                'export' => collect($theirs)->firstWhere('kind', DataRequest::EXPORT),
                'erasure' => collect($theirs)->first(fn (array $r) => $r['kind'] === DataRequest::ERASURE && $r['status'] !== 'cancelled'),
                'correction' => collect($theirs)->first(fn (array $r) => $r['kind'] === DataRequest::CORRECTION && $r['status'] !== 'cancelled'),
                'details' => $details[$subject['id']] ?? null,
            ];
        }

        return [
            'channel' => PersonalChannel::name($id),
            'cards' => $cards,
            'choices' => app(PeopleApi::class)->studentDetailChoices(),
            'settings_url' => Filament::getCurrentPanel()?->getProfileUrl(),
            'history' => $rows,
            'links' => $requests->legalLinks(),
            'goes' => array_map(fn (string $k) => __("privacy::requests.goes.{$k}"), ErasurePreview::GOES),
            'stays' => array_map(fn (string $k) => __("privacy::requests.stays.{$k}"), ErasurePreview::STAYS),
        ];
    }

    public function askCopy(string $subject): void
    {
        try {
            app(RequestDataExport::class)->handle($subject, (string) auth()->id(), $this->side());
            Notification::make()->success()->title(__('privacy::screens.mine.copy.preparing'))->send();
        } catch (ValidationException $e) {
            Notification::make()->warning()->title(collect($e->errors())->flatten()->first())->send();
        }
        unset($this->mine);
    }

    public function cancelRequest(string $id): void
    {
        $request = app(DataRequests::class)->find($id);
        abort_if($request === null, 404);
        app(CancelDataRequest::class)->handle($request, (string) auth()->id());
        unset($this->mine);
        Notification::make()->success()->title(__($request->kind === DataRequest::CORRECTION ? 'privacy::screens.mine.fix.cancelled' : 'privacy::screens.mine.erase.cancelled'))->send();
    }

    /** Correct the details this person may correct for the subject, on the spot. */
    public function correctAction(): Action
    {
        $l = 'privacy::screens.mine.fix.';
        $card = fn (array $arguments): array => collect($this->mine['cards'])->firstWhere('id', (string) ($arguments['subject'] ?? '')) ?? [];
        $has = fn (array $arguments, string $field): bool => in_array($field, $card($arguments)['details']['fields'] ?? [], true);
        $choices = fn (string $field): array => $this->mine['choices'][$field] ?? [];

        return Action::make('correct')->label(__($l.'edit'))->icon('heroicon-o-pencil-square')->color('gray')->size('sm')
            ->modalHeading(fn (array $arguments) => __($l.'edit_title', ['name' => $card($arguments)['name'] ?? '']))
            ->modalSubmitActionLabel(__($l.'save'))
            ->fillForm(fn (array $arguments) => array_map(fn ($v) => $v ?? '', $card($arguments)['details']['values'] ?? []))
            ->schema(fn (array $arguments) => array_values(array_filter([
                TextInput::make('name')->label(__($l.'fields.name'))->required()->maxLength(255),
                $has($arguments, 'gender') ? Select::make('gender')->label(__($l.'fields.gender'))->options($choices('gender'))->native(false) : null,
                $has($arguments, 'country') ? Select::make('country')->label(__($l.'fields.country'))->options($choices('country'))->searchable() : null,
                $has($arguments, 'school_grade') ? Select::make('school_grade')->label(__($l.'fields.school_grade'))->options($choices('school_grade'))->native(false) : null,
                $has($arguments, 'teacher_gender') ? Select::make('teacher_gender')->label(__($l.'fields.teacher_gender'))->options($choices('teacher_gender'))->selectablePlaceholder(false)->native(false) : null,
            ])))
            ->action(function (array $arguments, array $data) use ($l) {
                $changed = app(CorrectDetails::class)->handle((string) ($arguments['subject'] ?? ''), (string) auth()->id(), $this->side(), $data);
                unset($this->mine);
                Notification::make()->success()->title(__($changed === [] ? $l.'unchanged' : $l.'saved'))->send();
            });
    }

    /** Ask the academy to correct what cannot be corrected here. */
    public function askCorrectionAction(): Action
    {
        $l = 'privacy::screens.mine.fix.';

        return Action::make('askCorrection')->label(__($l.'ask'))->icon('heroicon-o-chat-bubble-left-ellipsis')->color('gray')->outlined()->size('sm')
            ->modalHeading(fn (array $arguments) => __($l.'ask_title', ['name' => $this->nameOf((string) ($arguments['subject'] ?? ''))]))
            ->modalDescription(__($l.'ask_body', ['days' => DataRequest::ANSWER_DAYS]))
            ->modalSubmitActionLabel(__($l.'send'))
            ->schema([Textarea::make('says')->label(__('privacy::requests.says'))->placeholder(__($l.'placeholder'))->rows(4)->required()
                ->minLength(RequestCorrection::SAYS_MIN)->maxLength(RequestCorrection::SAYS_MAX)])
            ->action(function (array $arguments, array $data) use ($l) {
                app(RequestCorrection::class)->handle((string) ($arguments['subject'] ?? ''), (string) auth()->id(), $this->side(), (string) $data['says']);
                unset($this->mine);
                Notification::make()->success()->title(__($l.'asked'))->send();
            });
    }

    public function eraseAction(): Action
    {
        $l = 'privacy::screens.mine.erase.';

        return Action::make('erase')->label(__($l.'action'))->icon('heroicon-o-trash')->color('danger')->outlined()->size('sm')
            ->modalHeading(fn (array $arguments) => __($l.'confirm', ['name' => $this->nameOf((string) ($arguments['subject'] ?? ''))]))
            ->modalDescription(__($l.'body', ['days' => DataRequest::ANSWER_DAYS]).' '.__($l.'copy_first'))
            ->modalSubmitActionLabel(__($l.'action'))
            ->schema([Textarea::make('reason')->label(__($l.'reason'))->rows(2)->maxLength(RequestErasure::REASON_MAX)])
            ->action(function (array $arguments, array $data) use ($l) {
                app(RequestErasure::class)->handle((string) ($arguments['subject'] ?? ''), (string) auth()->id(), $this->side(), $data['reason'] ?? null);
                unset($this->mine);
                Notification::make()->success()->title(__($l.'asked'))->send();
            });
    }

    public function when(mixed $at): ?string
    {
        return $at instanceof CarbonInterface ? app(LocalTimePresenter::class)->present($at)['label'] ?? null : null;
    }

    private function nameOf(string $subject): string
    {
        return (string) (collect($this->mine['cards'])->firstWhere('id', $subject)['name'] ?? '');
    }
}
