<?php

return [
    'providers' => ['google' => 'Google', 'apple' => 'Apple'],

    'sign_in' => [
        'or' => 'or',
        'with' => 'Sign in with :provider',
    ],

    'errors' => [
        'no_account' => 'There is no account at this academy with this :provider email. Sign in the way the academy sent you, then link :provider in your settings, or contact the academy.',
        'other_linked' => 'Your account here is linked to another :provider account. Sign in with that one, or unlink it in your settings first.',
        'taken' => 'This :provider account is linked to another account at this academy.',
        'not_linked' => 'Your account is not linked to :provider.',
        'last_way' => 'You cannot unlink :provider: it is your only way in. Set a password or an email first.',
        'impersonating' => 'The platform team cannot change how you sign in.',
        'failed' => 'Signing in with :provider did not finish. Try again.',
        'denied' => 'Signing in with :provider was cancelled.',
        'expired' => 'This step took too long. Start signing in again.',
        'off' => 'Signing in with :provider is not available here.',
    ],

    'settings' => [
        'title' => 'Sign in with Google and Apple',
        'description' => 'Link your account to Google or Apple and sign in with them, without a password. Two-step sign-in stays as it is.',
        'linked_as' => 'Linked: :email',
        'linked' => 'Linked',
        'not_linked' => 'Not linked',
        'link' => 'Link :provider',
        'unlink' => 'Unlink',
        'unlink_confirm' => 'Unlink your :provider account? You will not be able to sign in with it until you link it again.',
        'linked_done' => 'Your :provider account is linked.',
        'unlinked_done' => 'Your :provider account is unlinked.',
        'last_used' => 'Last used :date',
    ],
];
