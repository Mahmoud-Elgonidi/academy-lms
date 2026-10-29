<?php

namespace Modules\Identity\Filament\Pages;

use Filament\Auth\MultiFactor\Pages\SetUpRequiredMultiFactorAuthentication;
use Filament\Facades\Filament;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditActor;

/**
 * The back office's required two-step sign-in set-up (Filament's page). The
 * set-up asks for the person's password, so whoever has none chooses one
 * first (ChoosePassword), then comes back here.
 */
final class SetUpTwoFactor extends SetUpRequiredMultiFactorAuthentication
{
    public function mount(): void
    {
        $user = Filament::auth()->user();
        if ($user instanceof User && $user->password === null && AuditActor::current()->onBehalfOf === null) {
            $this->redirect(ChoosePassword::getUrl());

            return;
        }
        parent::mount();
    }
}
