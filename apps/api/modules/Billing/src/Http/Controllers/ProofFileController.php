<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Billing\Models\PaymentProof;
use Modules\SharedKernel\Authorization\Ability;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A proof's receipt, inline, for whoever looks after the academy's money and for the payer who sent it; nobody else. */
final class ProofFileController
{
    public function __invoke(Request $request, string $proof): StreamedResponse
    {
        $user = $request->user() ?? abort(403);
        $proof = Str::isUuid($proof) ? PaymentProof::query()->find($proof) : null;
        abort_unless($proof !== null && ($proof->payer_id === $user->getAuthIdentifier() || Gate::forUser($user)->allows(Ability::ViewBilling->value)), 404);

        return Storage::disk(PaymentProof::DISK)->response($proof->file_path, $proof->file_name, [
            'Content-Type' => $proof->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
