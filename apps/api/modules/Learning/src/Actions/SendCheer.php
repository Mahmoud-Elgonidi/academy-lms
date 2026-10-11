<?php

namespace Modules\Learning\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Learning\Contracts\Events\CheerSent;
use Modules\Learning\Domain\Journey\Cheers;
use Modules\Learning\Models\Cheer;
use Modules\People\Contracts\FamilyView;

/**
 * A guardian cheers a child on from their app ("برافو!"): the child sees it
 * with a little celebration next time, and hears it by push. A few a day,
 * so it stays special.
 */
final class SendCheer
{
    public function __construct(private readonly FamilyView $family) {}

    /** @throws ValidationException not their child, an unknown kind, or too many today */
    public function handle(string $fromId, string $studentId, string $kind): Cheer
    {
        if ($fromId === $studentId || ! $this->family->follows($fromId, $studentId)) {
            throw ValidationException::withMessages(['learner_id' => __('learning::challenges.errors.not_yours')]);
        }
        if (! in_array($kind, Cheer::KINDS, true)) {
            throw ValidationException::withMessages(['kind' => __('validation.in', ['attribute' => 'kind'])]);
        }
        $today = Cheer::query()->where('from_id', $fromId)->where('student_id', $studentId)
            ->where('created_at', '>', CarbonImmutable::now()->subDay())->count();
        if ($today >= Cheers::PER_DAY) {
            throw ValidationException::withMessages(['kind' => __('learning::challenges.errors.too_many_cheers')]);
        }

        return DB::transaction(function () use ($fromId, $studentId, $kind) {
            $cheer = Cheer::query()->create(['student_id' => $studentId, 'from_id' => $fromId, 'kind' => $kind, 'created_at' => CarbonImmutable::now()]);
            DB::afterCommit(fn () => event(new CheerSent($cheer->academy_id, $cheer->id, $studentId, $fromId, $kind)));

            return $cheer;
        });
    }
}
