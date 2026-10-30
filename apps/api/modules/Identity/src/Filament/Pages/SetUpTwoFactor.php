<?php

namespace Modules\Identity\Filament\Pages;

use Filament\Auth\MultiFactor\Pages\SetUpRequiredMultiFactorAuthentication;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Modules\Identity\Models\User;
use Modules\SharedKernel\Audit\AuditActor;

/**
 * The back office's required two-step sign-in set-up (Filament's page). The
 * set-up asks for the person's password, so whoever has none chooses one
 * first (ChoosePassword), then comes back here. The platform team signed
 * in as someone never sets it up for them (the model refuses): an old link
 * or tab brings them home, told why, instead of to a refusal.
 */
final class SetUpTwoFactor extends SetUpRequiredMultiFactorAuthentication
{
    public function mount(): void
    {
        if (AuditActor::current()->onBehalfOf !== null) {
            Notification::make()->warning()->title(__('identity::two_factor.impersonating_set_up'))->send();
            $this->redirect(Filament::getUrl());

            return;
        }
        $user = Filament::auth()->user();
        if ($user instanceof User && $user->password === null) {
            $this->redirect(ChoosePassword::getUrl());

            return;
        }
        parent::mount();
    }
}
