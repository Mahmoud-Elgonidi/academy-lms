<?php

namespace Modules\Classroom\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Modules\Classroom\Actions\Recordings\GiveRecordingConsent;
use Modules\Classroom\Actions\Recordings\WatchRecording;
use Modules\Classroom\Actions\Recordings\WithdrawRecordingConsent;
use Modules\Classroom\Models\Recording;
use Modules\Classroom\Models\RecordingConsent;

/**
 * What the family's and the teacher's recordings pages share: agreeing to
 * recordings (the box ticked first) and taking it back, and playing one
 * recording at a time (each opening logged).
 */
trait PlaysRecordings
{
    /** @var array{id: string, url: string, media: string}|null */
    public ?array $playing = null;

    /** @var array<string, bool> person id => the box "I agree" */
    public array $agreed = [];

    public function play(string $recordingId): void
    {
        $recording = Recording::query()->find($recordingId);
        try {
            $link = $recording === null ? throw new AuthorizationException : app(WatchRecording::class)->handle($recording, (string) auth()->id(), request()->ip(), request()->userAgent());
        } catch (AuthorizationException|ValidationException) {
            Notification::make()->danger()->title(__('classroom::recordings.errors.not_ready'))->send();

            return;
        }
        $this->playing = ['id' => $recording->id, 'url' => $link->url, 'media' => $recording->media];
    }

    public function closePlayer(): void
    {
        $this->playing = null;
    }

    public function agree(string $personId): void
    {
        $this->resetErrorBag();
        if (! ($this->agreed[$personId] ?? false)) {
            throw ValidationException::withMessages(["agreed.{$personId}" => __('classroom::recordings.consent.tick')]);
        }
        app(GiveRecordingConsent::class)->handle($personId, (string) auth()->id(), RecordingConsent::VERSION, request()->ip(), request()->userAgent());
        $this->forgetConsents();
        Notification::make()->success()->title(__('classroom::recordings.consent.agreed'))->send();
    }

    public function withdraw(string $personId): void
    {
        app(WithdrawRecordingConsent::class)->handle($personId, (string) auth()->id());
        $this->agreed[$personId] = false;
        $this->forgetConsents();
        Notification::make()->success()->title(__('classroom::recordings.consent.withdrawn'))->send();
    }

    abstract protected function forgetConsents(): void;
}
