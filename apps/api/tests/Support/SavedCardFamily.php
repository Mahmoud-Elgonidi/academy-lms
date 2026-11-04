<?php

namespace Tests\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Modules\Billing\Actions\IssueRenewal;
use Modules\Billing\Actions\Payments\SaveGatewayAccount;
use Modules\Billing\Actions\Payments\SettleCheckout;
use Modules\Billing\Actions\Payments\StartCheckout;
use Modules\Billing\Models\GatewayAccount;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentCheckout;
use Modules\Billing\Models\Subscription;
use Modules\Billing\Payments\Cards\Mandate;
use Modules\Billing\Payments\Gateways\DemoGateway;
use Modules\Communication\Models\Notification;
use Modules\Identity\Models\User;

/**
 * Saved cards and automatic renewal (J5b), on top of EnrolledStudent: the
 * guardian (in Riyadh) pays the first block on the demo gateway, ticking
 * "save my card and renew automatically", and the course's next block is
 * issued when the test says.
 */
trait SavedCardFamily
{
    use EnrolledStudent;

    protected function savedCardFamily(): void
    {
        $this->enrolStudentOnPlan();
        $this->invoice = Invoice::query()->findOrFail($this->booked->invoice->id);
        $this->payer = User::query()->findOrFail(DB::table('billing_accounts')->where('id', $this->invoice->billing_account_id)->value('payer_id'));
        $this->payer->forceFill(['timezone' => 'Asia/Riyadh'])->save();
        $this->demo = app(SaveGatewayAccount::class)->handle(null, ['gateway' => 'demo', 'label' => 'بطاقة', 'mode' => 'test'], $this->admin->id);
    }

    /** Pays an invoice on the demo gateway, ticking "save my card" or not. */
    protected function payOnDemo(Invoice $invoice, bool $save = true, ?GatewayAccount $account = null): PaymentCheckout
    {
        $checkout = app(StartCheckout::class)->handle($invoice, $account ?? $this->demo, $this->payer->id, 'link', null, $save, $save ? Mandate::VERSION : null);
        DemoGateway::decide((string) $checkout->provider_ref, pay: true);

        return app(SettleCheckout::class)->handle($checkout->refresh());
    }

    /** The course's next block's invoice (block 2), as IssueRenewal sends it when the balance reaches its mark. */
    protected function renewal(): Invoice
    {
        return app(IssueRenewal::class)->handle(Subscription::query()->with('plan')->where('enrollment_id', $this->booked->enrollmentId)->firstOrFail());
    }

    protected function at(string $utc): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($utc, 'UTC'));
    }

    /** @return list<string> the billing templates this person heard, in order */
    protected function heard(string $userId): array
    {
        return Notification::query()->where('user_id', $userId)->where('template_key', 'like', 'billing.%')
            ->orderBy('created_at')->orderBy('id')->pluck('template_key')->all();
    }

    protected function cardApi(string $method, string $path, User $as, array $data = []): TestResponse
    {
        app('auth')->forgetGuards();
        $response = $this->withToken($as->createToken('phone')->plainTextToken)
            ->withHeaders(array_filter(['Accept-Language' => 'ar', 'Idempotency-Key' => $method === 'GET' ? null : 'k-'.bin2hex(random_bytes(6))]))
            ->json($method, 'http://noor.lms.test/api/v1/'.$path, $data);
        $this->actAsTenant($this->academy);

        return $response;
    }
}
