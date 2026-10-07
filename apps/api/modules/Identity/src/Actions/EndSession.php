<?php

namespace Modules\Identity\Actions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Identity\Models\PersonalAccessToken;
use Modules\Identity\Models\User;

/** Signs one of the person's apps out (a lost phone): its token stops working at once. */
final class EndSession
{
    public function handle(User $user, int $tokenId): void
    {
        $deleted = $user->tokens()->whereKey($tokenId)->delete();

        if ($deleted === 0) {
            throw (new ModelNotFoundException)->setModel(PersonalAccessToken::class, [$tokenId]);
        }
    }
}
