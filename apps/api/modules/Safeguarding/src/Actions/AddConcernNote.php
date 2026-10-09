<?php

namespace Modules\Safeguarding\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Safeguarding\Enums\ConcernStatus;
use Modules\Safeguarding\Models\Concern;
use Modules\Safeguarding\Models\ConcernEntry;

/** A lead writes down what they did or learned (encrypted, for the leads' eyes only); writing on a new concern takes it. */
final class AddConcernNote
{
    public function __construct(private readonly AcknowledgeConcern $acknowledge) {}

    public function handle(string $concernId, string $body, string $actorId): ConcernEntry
    {
        $body = trim($body);
        if (mb_strlen($body) < 3 || mb_strlen($body) > 5000) {
            throw ValidationException::withMessages(['body' => __('safeguarding::concerns.errors.note')]);
        }

        return DB::transaction(function () use ($concernId, $body, $actorId) {
            $concern = $this->acknowledge->handle($concernId, $actorId);
            if ($concern->status === ConcernStatus::Closed) {
                throw ValidationException::withMessages(['body' => __('safeguarding::concerns.errors.closed')]);
            }

            return ConcernEntry::query()->create(['concern_id' => $concern->id, 'author_id' => $actorId, 'kind' => 'note', 'body' => $body]);
        });
    }
}
