<?php

namespace Modules\Billing\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Models\BillingSettings;
use Modules\Billing\Models\PriceRegion;
use Modules\Billing\Queries\TransferDetails;
use Modules\SharedKernel\Audit\AuditTrail;

/**
 * Where families send the money by hand (docs/architecture/02, section 3):
 * the academy's own text for every payer, and a price region's own for the
 * payers in it (empty: they read the academy's). Plain text of several
 * lines, up to TransferDetails::MAX characters, without markup: it is shown
 * escaped, line breaks kept, with every invoice still to pay. The audit says
 * which texts changed, never what they say.
 */
final class SaveTransferDetails
{
    public function __construct(private readonly AuditTrail $audit) {}

    /**
     * @param  ?string  $academy  for every payer (null or empty: none)
     * @param  array<string, ?string>  $regions  price region id => its own text (null or empty: the academy's); regions left out keep theirs
     *
     * @throws ValidationException under "academy" or "regions.{id}"
     */
    public function handle(?string $academy, array $regions = []): void
    {
        $academy = $this->check('academy', $academy);
        $texts = [];
        foreach ($regions as $id => $text) {
            $id = (string) $id;
            if (! Str::isUuid($id)) {
                throw ValidationException::withMessages(["regions.{$id}" => __('billing::transfer.errors.region')]);
            }
            $texts[$id] = $this->check("regions.{$id}", $text);
        }

        DB::transaction(function () use ($academy, $texts) {
            $found = PriceRegion::query()->whereIn('id', array_keys($texts))->lockForUpdate()->get()->keyBy('id');
            if ($missing = array_diff(array_keys($texts), $found->keys()->all())) {
                throw ValidationException::withMessages(['regions.'.reset($missing) => __('billing::transfer.errors.region')]);
            }

            $settings = BillingSettings::current();
            $academyChanged = $settings->manual_payment_note !== $academy;
            if ($academyChanged) {
                $settings->manual_payment_note = $academy;
                $settings->save();
            }
            $changed = [];
            foreach ($texts as $id => $text) {
                $region = $found[$id];
                if ($region->transfer_details !== $text) {
                    $region->transfer_details = $text;
                    $region->save();
                    $changed[] = $id;
                }
            }

            if ($academyChanged || $changed !== []) {
                $this->audit->record('billing.transfer_details_saved', null, null, ['academy' => $academyChanged, 'regions' => $changed]);
            }
        });
    }

    /** The text as it is kept, or why it cannot be. */
    private function check(string $field, ?string $text): ?string
    {
        // Control characters other than line breaks and tabs say nothing a reader needs.
        $plain = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $text);
        if ($plain === null) {
            throw ValidationException::withMessages([$field => __('billing::transfer.errors.markup')]);   // not text at all
        }
        $text = TransferDetails::clean($plain);
        if ($text === null) {
            return null;
        }
        if (mb_strlen($text) > TransferDetails::MAX) {
            throw ValidationException::withMessages([$field => __('billing::transfer.errors.too_long', ['max' => TransferDetails::MAX])]);
        }
        // Shown as text everywhere: a tag, a comment or a processing instruction is refused rather than shown as typed.
        if (preg_match('/<(?:\s*[\/?]?\s*[a-z]|!)/i', $text)) {
            throw ValidationException::withMessages([$field => __('billing::transfer.errors.markup')]);
        }

        return $text;
    }
}
