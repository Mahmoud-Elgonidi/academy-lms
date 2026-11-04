<?php

namespace Modules\Billing\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Actions\Cards\RevokePaymentMethod;
use Modules\Billing\Actions\Cards\SetAutoRenew;
use Modules\Billing\Queries\Cards\PayerCards;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\LearnerStage;
use Modules\SharedKernel\Http\ProblemException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The payer's saved cards and automatic renewal (docs/architecture/02,
 * section 27, J5b), in the classic look only (a guardian, an adult learner):
 * brand, last four and expiry, never a card's references; turn automatic
 * renewal on or off, or remove a card, in one tap. A card is saved only by
 * ticking "save my card and renew automatically" when paying online
 * (POST family/invoices/{id}/checkout with save_card), on the gateway's page.
 */
final class FamilyCardsController
{
    /**
     * My saved cards and automatic renewal.
     *
     * The cards kept for me (brand, last four, expiry), per account whether renewals are charged automatically and to
     * which card, the automatic charges coming, and the mandate's words and version (to tick when paying). 403
     * cards-refused (family_only) outside the classic look.
     */
    public function index(Request $request, FamilyView $family, PayerCards $cards): JsonResponse
    {
        return response()->json(['data' => $cards->present($this->me($request, $family))]);
    }

    /**
     * Turn automatic renewal on or off.
     *
     * on: true needs a live card for the account's currency (422 with why not, in my words); off cancels the charges
     * still to come (one under way finishes). billing_account_id narrows it to one account of mine. Answers my cards
     * and renewal as GET family/cards. Send an Idempotency-Key.
     */
    public function autoRenew(Request $request, FamilyView $family, SetAutoRenew $set, PayerCards $cards): JsonResponse
    {
        $data = $request->validate([
            'on' => ['required', 'boolean'],
            'billing_account_id' => ['nullable', 'uuid'],
        ]);
        $me = $this->me($request, $family);
        $set->handle($me, (bool) $data['on'], $data['billing_account_id'] ?? null, 'family');

        return response()->json([
            'data' => $cards->present($me),
            'message' => __($data['on'] ? 'billing::cards.done.on' : 'billing::cards.done.off'),
        ]);
    }

    /**
     * Remove a saved card.
     *
     * The card is let go at once (its reference at the gateway is dropped here); with no card left, automatic renewal
     * goes off and the charges still to come are cancelled. Answers my cards and renewal as GET family/cards. Send an
     * Idempotency-Key.
     *
     * @throws NotFoundHttpException not a card of mine
     */
    public function revoke(Request $request, string $card, FamilyView $family, RevokePaymentMethod $revoke, PayerCards $cards): JsonResponse
    {
        $me = $this->me($request, $family);
        if (! Str::isUuid($card)) {
            throw new NotFoundHttpException;
        }
        try {
            $revoke->handle($card, $me, 'payer');
        } catch (ValidationException) {
            throw new NotFoundHttpException;
        }

        return response()->json([
            'data' => $cards->present($me),
            'message' => __('billing::cards.done.removed'),
        ]);
    }

    /** The person signed in, in the classic look (money is theirs to see). */
    private function me(Request $request, FamilyView $family): string
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        if ($family->stage($me) !== LearnerStage::Classic) {
            throw new ProblemException(403, 'cards-refused', __('billing::cards.errors.family_only'), ['reason' => 'family_only']);
        }

        return $me;
    }
}
