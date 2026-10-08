<?php

namespace Modules\People\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\People\Actions\Consent\GrantConsent;
use Modules\People\Actions\Consent\WithdrawConsent;
use Modules\People\Models\GuardianConsent;
use Modules\People\Queries\FamilyConsents;
use Modules\Platform\Contracts\BrandingApi;
use Modules\SharedKernel\Time\LocalTimePresenter;

/** A guardian's consent to their children's accounts, for the apps (the same as "Children's privacy" in /my). */
final class ConsentController
{
    /**
     * My children's consent.
     *
     * Each child of the guardian signed in who needs a guardian's consent (under the academy's consent age), or has one:
     * waiting or granted, who agreed and when, and the wording to agree to (send its version back when agreeing).
     * A student signed in, or a guardian with no such child, gets an empty list.
     */
    public function index(Request $request, FamilyConsents $consents, BrandingApi $branding, LocalTimePresenter $time): JsonResponse
    {
        $viewer = (string) $request->user()?->getAuthIdentifier();
        $academy = $branding->current()->name();
        $children = [];
        foreach ($consents->children($viewer) as $child) {
            $state = $child['state'];
            if (! $state->needed && ! $state->granted) {
                continue;
            }
            $text = $consents->text($child['student']['name'], $academy);
            $sections = [];
            foreach ($text['sections'] as $part) {
                $sections[] = ['title' => (string) $part['title'], 'body' => (string) $part['body']];
            }
            $children[] = [
                'student' => ['id' => (string) $child['student']['id'], 'name' => (string) $child['student']['name']],
                /** waiting | granted */
                'state' => $state->waiting() ? 'waiting' : 'granted',
                'granted_at' => $state->grantedAt ? $time->present($state->grantedAt) : null,
                /** Who agreed, in words ("You agreed on 5 October 2026"); null while waiting. */
                'by' => $state->granted ? FamilyConsents::who($state, $viewer, $state->grantedAt?->locale(app()->getLocale())->translatedFormat('j F Y')) : null,
                /** Any of the child's guardians can take it back. */
                'can_withdraw' => (bool) $state->granted,
                /** What the guardian agrees to, in their language; send version back when agreeing. */
                'text' => [
                    'version' => (int) $text['version'],
                    'title' => (string) $text['title'],
                    'intro' => (string) $text['intro'],
                    /** Each part: a heading and its words. */
                    'sections' => $sections,
                    'agree' => (string) $text['agree'],
                ],
            ];
        }

        return response()->json(['age' => $consents->text('', $academy)['age'], 'children' => $children]);
    }

    /**
     * Agree to a child's account.
     *
     * The child may sign in and join with their own link from now on. version is the wording's version from the list
     * (a newer wording since then answers 422: read it again).
     */
    public function grant(Request $request, string $student, GrantConsent $grant): JsonResponse
    {
        $data = $request->validate([
            'agree' => ['accepted'],
            'version' => ['required', 'integer'],
        ], ['agree.accepted' => __('people::consent.link.tick')]);
        if ((int) $data['version'] !== GuardianConsent::VERSION) {
            throw ValidationException::withMessages(['version' => __('people::consent.errors.changed')]);
        }
        $consent = $grant->handle($student, (string) $request->user()->getAuthIdentifier(), 'family', $request->ip(), $request->userAgent());

        return response()->json(['id' => $consent->id, 'state' => 'granted']);
    }

    /**
     * Take back your consent to a child's account.
     *
     * The child is signed out on every device and waits again.
     */
    public function withdraw(Request $request, string $student, WithdrawConsent $withdraw): JsonResponse
    {
        $withdraw->handle($student, (string) $request->user()->getAuthIdentifier(), true);

        return response()->json(['state' => 'waiting']);
    }
}
