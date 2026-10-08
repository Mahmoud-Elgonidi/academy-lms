<?php

namespace Modules\Billing\Actions\Proofs;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Contracts\Events\PaymentProofSubmitted;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentProof;
use Modules\SharedKernel\Money\Money;
use Throwable;

/**
 * The payer says they paid an invoice by hand and shows it: how (a bank
 * transfer, InstaPay, a wallet), how much (up to what is left), the day,
 * the transfer's number, and the receipt (a photo, a screenshot or the
 * bank's PDF). Nothing is paid yet: the proof waits for the academy
 * (ApprovePaymentProof records the payment). One proof waits per invoice;
 * the file goes to the private disk under the academy's folder.
 */
final class SubmitPaymentProof
{
    /** @param  string  $channel  family (signed in: the panel, the app, the portal) | link (the invoice's pay link) */
    public function handle(
        Invoice $invoice,
        string $payerId,
        string $channel,
        ?string $method,
        string|int|float|null $amount,
        ?string $paidOn,
        ?string $reference,
        ?string $note,
        ?UploadedFile $file,
    ): PaymentProof {
        if (DB::table('billing_accounts')->where('id', $invoice->billing_account_id)->value('payer_id') !== $payerId) {
            throw new AuthorizationException;
        }
        $l = 'billing::proofs.';
        if ($invoice->status !== 'issued' || $invoice->outstanding()->minor <= 0) {
            throw ValidationException::withMessages(['invoice' => __('billing::billing.errors.not_payable')]);
        }
        if (PaymentProof::pendingFor($invoice->id) !== null) {
            throw ValidationException::withMessages(['invoice' => __($l.'errors.waiting')]);
        }

        $reference = trim((string) $reference) ?: null;
        $note = trim((string) $note) ?: null;
        $issued = CarbonImmutable::parse($invoice->issued_at ?? CarbonImmutable::now());
        Validator::make(compact('method', 'amount', 'reference', 'note', 'file') + ['paid_on' => $paidOn], [
            'method' => ['required', 'string', 'in:'.implode(',', PaymentProof::METHODS)],
            'amount' => ['required', 'numeric', 'gt:0'],
            // The payer's own day: up to tomorrow in UTC covers every time zone, and not long before the invoice.
            'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.CarbonImmutable::now()->addDay()->toDateString(), 'after_or_equal:'.$issued->subDays(31)->toDateString()],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'file', 'max:'.PaymentProof::MAX_KB, 'mimetypes:'.implode(',', PaymentProof::MIMES)],
        ], [
            'paid_on.before_or_equal' => __($l.'errors.future'),
            'paid_on.after_or_equal' => __($l.'errors.too_early'),
        ], trans($l.'attributes'))->validate();

        $money = Money::parse((string) $amount, $invoice->currency);
        if ($money->minor <= 0 || $money->minor > $invoice->outstanding()->minor) {
            throw ValidationException::withMessages(['amount' => __('billing::billing.errors.amount', ['max' => $invoice->outstanding()->format()])]);
        }

        $stored = $file->storeAs("academies/{$invoice->academy_id}/payment-proofs/{$invoice->id}", Str::uuid7().'.'.($file->guessExtension() ?: 'bin'), ['disk' => PaymentProof::DISK]);
        if ($stored === false) {
            throw ValidationException::withMessages(['file' => __($l.'errors.file')]);
        }
        try {
            return DB::transaction(function () use ($invoice, $payerId, $channel, $method, $money, $paidOn, $reference, $note, $file, $stored) {
                $proof = PaymentProof::query()->create([
                    'invoice_id' => $invoice->id,
                    'payer_id' => $payerId,
                    'channel' => $channel,
                    'method' => $method,
                    'amount_minor' => $money->minor,
                    'currency' => $money->currency,
                    'paid_on' => $paidOn,
                    'reference' => $reference,
                    'note' => $note,
                    'file_path' => $stored,
                    'file_name' => Str::limit($file->getClientOriginalName() ?: basename($stored), 190, ''),
                    'mime' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                    'status' => 'pending',
                ]);
                DB::afterCommit(fn () => event(new PaymentProofSubmitted($invoice->academy_id, $proof->id, $invoice->id)));

                return $proof;
            });
        } catch (UniqueConstraintViolationException) {
            Storage::disk(PaymentProof::DISK)->delete($stored);
            // Sent twice at once: the first one is waiting.
            throw ValidationException::withMessages(['invoice' => __($l.'errors.waiting')]);
        } catch (Throwable $e) {
            Storage::disk(PaymentProof::DISK)->delete($stored);
            throw $e;
        }
    }
}
