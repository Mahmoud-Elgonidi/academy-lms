<?php

namespace Modules\Privacy\Filament\Concerns;

use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Modules\Identity\Contracts\PersonalChannel;
use Modules\Privacy\Actions\CancelDataRequest;
use Modules\Privacy\Actions\RequestDataExport;
use Modules\Privacy\Actions\RequestErasure;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\PrivacyRules;
use Modules\Privacy\Queries\DataRequests;
use Modules\Privacy\Queries\ErasurePreview;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * "My data", the same in /my and /teach: for each person this one may ask
 * about (themselves; a guardian also the learners they follow), the last
 * copy (being prepared, or ready to download until its day) and the
 * erasure (waiting, approved with its day, or refused with the academy's
 * reason), then every request, and the academy's public pages. Follows the
 * person's own live channel.
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
        foreach (app(PrivacyRules::class)->subjectsFor($id, (string) $me?->name, $this->side()) as $subject) {
            $theirs = array_values(array_filter($rows, fn (array $r) => $r['subject_id'] === $subject['id']));
            $cards[] = $subject + [
                'can_copy' => $requests->canCopy($subject['id']),
                'export' => collect($theirs)->firstWhere('kind', DataRequest::EXPORT),
                'erasure' => collect($theirs)->first(fn (array $r) => $r['kind'] === DataRequest::ERASURE && $r['status'] !== 'cancelled'),
            ];
        }

        return [
            'channel' => PersonalChannel::name($id),
            'cards' => $cards,
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
        Notification::make()->success()->title(__('privacy::screens.mine.erase.cancelled'))->send();
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
