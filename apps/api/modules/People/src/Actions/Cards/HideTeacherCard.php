<?php

namespace Modules\People\Actions\Cards;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\People\Models\TeacherPublicCard;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Authorization\Ability;

/**
 * The academy takes a teacher's card off its public page, or puts it back
 * (site.manage: the owner and admins). The teacher's own switch and words
 * stay as they are. Audited with the teacher's name.
 */
final class HideTeacherCard
{
    public function __construct(private readonly AuditTrail $audit) {}

    public function handle(string $cardId, bool $hidden): TeacherPublicCard
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($cardId, $hidden) {
            $card = TeacherPublicCard::query()->lockForUpdate()->whereKey(Str::isUuid($cardId) ? $cardId : null)->firstOrFail();
            if ($card->hidden_by_academy !== $hidden) {
                $card->forceFill(['hidden_by_academy' => $hidden])->save();
                $this->audit->record($hidden ? 'people.card_hidden' : 'people.card_unhidden', 'user', $card->teacher_id, [
                    'teacher' => (string) DB::table('users')->where('id', $card->teacher_id)->value('name'),
                ]);
            }

            return $card;
        });
    }
}
