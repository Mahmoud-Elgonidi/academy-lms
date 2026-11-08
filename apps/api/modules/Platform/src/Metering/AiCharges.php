<?php

namespace Modules\Platform\Metering;

use Closure;
use Modules\Platform\Domain\Metering\CreditRates;
use Modules\SharedKernel\Ai\AiFailed;
use Modules\SharedKernel\Ai\AudioClip;
use Modules\SharedKernel\Ai\Capability;
use Modules\SharedKernel\Ai\Transcript;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * The AI helpers' credits, charged inside the gateway alone (docs/
 * architecture/02, section 11): before a call its estimate is held (the
 * base, and for a recording its minutes by its size), so it starts only when
 * it fits; after an answer the actual is taken (the minutes heard) and the
 * hold lets go; a call that failed or timed out costs nothing. A helper that
 * hears then writes is charged once, for what it heard, when its words come
 * back. With nothing left the helper stops (quota, with the credits the
 * request needed) and the work goes on by hand. Outside an academy nothing
 * is charged.
 */
final class AiCharges
{
    public function __construct(
        private readonly Meters $meters,
        private readonly AiCreditRates $rates,
        private readonly PendingCharges $pending,
        private readonly TenantContext $tenants,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $call
     * @return T
     */
    public function text(Capability $capability, Closure $call): mixed
    {
        $academyId = $this->tenants->id();
        if ($academyId === null) {
            return $call();
        }
        // It heard a recording first: that is what it costs, settled now its words came.
        $heard = $capability->needsSpeech() ? $this->pending->take($capability->key, $academyId) : null;
        if ($heard !== null) {
            return $this->run($academyId, $heard['hold'], $heard['cost'], $heard['about'], $call);
        }
        $cost = $this->rates->charge($capability);
        $about = $this->about($capability);

        return $this->run($academyId, $this->hold($academyId, $cost, $about), $cost, $about, $call);
    }

    /** @param  Closure(): Transcript  $call */
    public function speech(Capability $capability, AudioClip $clip, Closure $call): Transcript
    {
        $academyId = $this->tenants->id();
        if ($academyId === null) {
            return $call();
        }
        $rate = $this->rates->for($capability);
        $estimate = CreditRates::charge($rate['base'], $rate['per_minute'], CreditRates::estimateMinutes($clip->bytes()) * 60.0);
        $about = $this->about($capability);
        $hold = $this->hold($academyId, $estimate, $about);
        try {
            $heard = $call();
        } catch (Throwable $e) {
            $this->letGo($academyId, $hold);
            throw $e;
        }
        $cost = CreditRates::charge($rate['base'], $rate['per_minute'], $heard->seconds);
        if ($capability->needsLanguage()) {
            $this->pending->put($capability->key, $academyId, $hold, $cost, $about);
        } else {
            $this->meters->settle($academyId, Meter::AiCredits, $hold, $cost, $about);
        }

        return $heard;
    }

    /**
     * @template T
     *
     * @param  array<string, mixed>  $about
     * @param  Closure(): T  $call
     * @return T
     */
    private function run(string $academyId, ?string $hold, int $cost, array $about, Closure $call): mixed
    {
        try {
            $answer = $call();
        } catch (Throwable $e) {
            $this->letGo($academyId, $hold);
            throw $e;
        }
        $this->meters->settle($academyId, Meter::AiCredits, $hold, $cost, $about);

        return $answer;
    }

    /** @param  array<string, mixed>  $about */
    private function hold(string $academyId, int $estimate, array $about): ?string
    {
        try {
            return $this->meters->reserve($academyId, Meter::AiCredits, $estimate, $about);
        } catch (NotEnoughBalance $e) {
            throw new AiFailed('quota', needed: $e->needed);
        }
    }

    private function letGo(string $academyId, ?string $hold): void
    {
        if ($hold !== null) {
            $this->meters->release($academyId, Meter::AiCredits, $hold);
        }
    }

    /** @return array<string, mixed> */
    private function about(Capability $capability): array
    {
        $user = auth()->id();

        return ['subject' => $capability->key, 'user_id' => is_string($user) && preg_match('/^[0-9a-f-]{36}$/i', $user) ? $user : null, 'ref_type' => 'ai'];
    }
}
