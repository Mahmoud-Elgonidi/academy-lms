<?php

namespace Modules\Identity\Http\V1\Controllers;

use Illuminate\Http\Request;
use Modules\Identity\Actions\Contact\ConfirmContactChange;
use Modules\Identity\Actions\Contact\RequestContactChange;
use Modules\Identity\Http\V1\Resources\SentCodeResource;
use Modules\Identity\Http\V1\Resources\UserResource;
use Modules\Identity\Models\User;

/**
 * The signed-in person makes a new email or mobile number theirs (the same
 * as "Email and mobile" on My settings): a code goes to the new address, and
 * nothing changes until it comes back. Not for a child the academy runs under
 * a guardian, and never while the platform team is signed in as someone.
 */
final class ContactController
{
    /**
     * Send a code to a new email or mobile number.
     *
     * `kind` email or phone; `value` the new address (a local number takes the calling code of the person's time
     * zone). Refused (422 on `value`) when it is not well formed, is the current one, or is another account's here.
     * Answers 202 with the challenge to confirm.
     */
    public function send(Request $request, RequestContactChange $change): SentCodeResource
    {
        $request->validate([
            'kind' => ['required', 'string', 'in:'.implode(',', RequestContactChange::KINDS)],
            /** The new email, or the new mobile number. */
            'value' => ['required', 'string', 'max:255'],
            /** A phone's code goes by WhatsApp unless "sms" is asked for. */
            'channel' => ['nullable', 'string', 'in:whatsapp,sms,email'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return new SentCodeResource($change->handle(
            $user,
            (string) $request->string('kind'),
            (string) $request->string('value'),
            $request->input('channel'),
            $request->ip(),
        ));
    }

    /**
     * Confirm the new address with its code.
     *
     * The address becomes the account's (to sign in with and to hear from the academy), and the account hears
     * that it changed on every way it can. Answers the account, like `GET me`.
     */
    public function confirm(Request $request, ConfirmContactChange $confirm): UserResource
    {
        $request->validate([
            'challenge_id' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string', 'max:12'],
        ]);
        /** @var User $user */
        $user = $request->user();

        return new UserResource($confirm->handle($user, (string) $request->string('challenge_id'), (string) $request->string('code'))->load('roleRecords'));
    }
}
