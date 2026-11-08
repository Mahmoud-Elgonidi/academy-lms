<?php

namespace Modules\Platform\Metering;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Platform\Domain\Metering\Hold;
use Modules\Platform\Domain\Metering\Ledger;
use Modules\Platform\Domain\Metering\Lot;
use Modules\Platform\Domain\Metering\Reservation;
use Modules\Platform\Domain\Metering\SpendOrder;
use Modules\Platform\Models\Academy;
use Modules\Platform\Models\SaasSubscription;
use Modules\Platform\Plans\PlanTerms;
use Modules\SharedKernel\Plans\Meter;
use Modules\SharedKernel\Plans\UsagePeriod;
use Throwable;

/**
 * An academy's usage ledger (usage_ledger, docs/architecture/02, section 11),
 * read and written on the platform connection only, by academy id (no
 * tenant needed: the gateway, the classroom, the console and the sweeps all
 * come here). Every write takes the academy and meter's advisory lock in its
 * own transaction, so two requests cannot both take the last credits; the
 * month's allowance comes in on the first write or read of the month (and
 * the hourly sweep). The rules are Domain\Metering's; this only reads the
 * entries and writes new ones. Nothing is ever changed or deleted.
 *
 * The details of an entry ($about): subject, user_id, ref_type, ref_id,
 * reason, actor_id, occurred_at.
 */
final class Meters
{
    public const LOCK = 'usage:';

    /** @var array<string, string> academy id => its clock */
    private array $zones = [];

    // Reading ---------------------------------------------------------------------------------------------------------

    /** The plan's monthly amount for the meter, or null when nothing limits it (no plan, no limit). */
    public function limitOf(string $academyId, Meter $meter): ?int
    {
        $subscription = SaasSubscription::currentFor($academyId);

        return $subscription === null ? null : PlanTerms::of($subscription)->limit($meter->limit());
    }

    public function state(string $academyId, Meter $meter, ?CarbonImmutable $at = null): MeterState
    {
        $at ??= CarbonImmutable::now();
        $limit = $this->limitOf($academyId, $meter);
        $period = $this->month($academyId, $at);
        if ($limit !== null && ! $this->hasAllowance($academyId, $meter, $period)) {
            $this->locked($academyId, $meter, fn () => $this->grantAllowance($academyId, $meter, $at), alerts: false);
        }

        $lots = $this->lots($academyId, $meter, $at);
        $holds = $this->holds($academyId, $meter);
        $balance = Ledger::balance($lots, $this->debt($academyId, $meter), Reservation::held($holds, $at), $at);
        $key = $period->key($this->zone($academyId));
        $allowance = (int) $this->db()->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)
            ->where('kind', Ledger::ALLOWANCE)->where('period', $key)->sum('amount');
        $bought = array_values(array_filter(SpendOrder::of($lots, $at), fn (Lot $l) => ! $l->isAllowance()));
        $amounts = $this->db()->table('usage_ledger')->whereIn('id', array_map(fn (Lot $l) => $l->id, $bought))->pluck('amount', 'id');

        return new MeterState(
            meter: $meter,
            limited: $limit !== null,
            allowance: $allowance,
            allowanceLeft: $balance->allowanceLeft,
            bought: $balance->bought,
            debt: $balance->debt,
            held: $balance->held,
            used: $this->usedIn($academyId, $meter, $period),
            lots: array_map(fn (Lot $l) => ['id' => $l->id, 'kind' => $l->kind, 'amount' => (int) ($amounts[$l->id] ?? $l->remaining), 'left' => $l->remaining, 'expires_at' => $l->expiresAt, 'created_at' => $l->createdAt], $bought),
            period: $key,
        );
    }

    /** What was used in the month (uses only: not holds, not an overage moved onto a later lot). */
    public function usedIn(string $academyId, Meter $meter, UsagePeriod $period): int
    {
        return (int) -$this->db()->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)
            ->where('kind', Ledger::CONSUME)->where(fn ($q) => $q->whereNull('ref_type')->orWhere('ref_type', '<>', 'overage'))
            ->where('occurred_at', '>=', $period->from)->where('occurred_at', '<', $period->to)->sum('amount');
    }

    /** The academy's calendar month at $at. */
    public function month(string $academyId, ?CarbonImmutable $at = null): UsagePeriod
    {
        return UsagePeriod::monthOf($at ?? CarbonImmutable::now(), $this->zone($academyId));
    }

    public function zone(string $academyId): string
    {
        return $this->zones[$academyId] ??= (string) (Academy::query()->whereKey($academyId)->value('default_timezone') ?: 'UTC');
    }

    // Writing ---------------------------------------------------------------------------------------------------------

    /**
     * Holds $amount for a request about to start. Nothing to hold without a limit (null).
     *
     * @param  array<string, mixed>  $about
     *
     * @throws NotEnoughBalance
     */
    public function reserve(string $academyId, Meter $meter, int $amount, array $about = []): ?string
    {
        return $this->locked($academyId, $meter, function () use ($academyId, $meter, $amount, $about): ?string {
            if ($this->limitOf($academyId, $meter) === null) {
                return null;
            }
            $now = CarbonImmutable::now();
            $this->grantAllowance($academyId, $meter, $now);
            $available = Ledger::balance($this->lots($academyId, $meter, $now), $this->debt($academyId, $meter), Reservation::held($this->holds($academyId, $meter), $now), $now)->available();
            if (! Reservation::fits($available, $amount, true)) {
                throw new NotEnoughBalance($amount, $available);
            }
            $hold = (string) Str::uuid7();
            $this->insert($academyId, $meter, Ledger::RESERVE, -max(1, $amount), ['hold_id' => $hold] + $this->only($about, ['subject', 'user_id']));

            return $hold;
        }, alerts: false);
    }

    /**
     * The request went through: its hold lets go and its actual cost is taken.
     *
     * @param  array<string, mixed>  $about
     */
    public function settle(string $academyId, Meter $meter, ?string $holdId, int $amount, array $about = []): void
    {
        $this->locked($academyId, $meter, function () use ($academyId, $meter, $holdId, $amount, $about): void {
            if ($holdId !== null) {
                $this->letGo($academyId, $meter, $holdId);
            }
            $this->draw($academyId, $meter, Ledger::CONSUME, $amount, ['hold_id' => $holdId] + $about);
        });
    }

    /** The request failed or timed out: it costs nothing. */
    public function release(string $academyId, Meter $meter, string $holdId): void
    {
        $this->locked($academyId, $meter, fn () => $this->letGo($academyId, $meter, $holdId), alerts: false);
    }

    /**
     * A use with no hold before it (a lesson's room): taken now, as overage when nothing covers it.
     *
     * @param  array<string, mixed>  $about
     */
    public function consume(string $academyId, Meter $meter, int $amount, array $about = []): void
    {
        $this->locked($academyId, $meter, fn () => $this->draw($academyId, $meter, Ledger::CONSUME, $amount, $about));
    }

    /**
     * What one thing (a room) counts in all, taken once: only what it adds to what it was counted for before.
     *
     * @param  array<string, mixed>  $about
     * @return int what it counted for before this
     */
    public function consumeUpTo(string $academyId, Meter $meter, int $total, string $refType, string $refId, array $about = []): int
    {
        return $this->locked($academyId, $meter, function () use ($academyId, $meter, $total, $refType, $refId, $about): int {
            $counted = (int) -$this->db()->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)
                ->where('kind', Ledger::CONSUME)->where('ref_type', $refType)->where('ref_id', $refId)->sum('amount');
            if ($total > $counted) {
                $this->draw($academyId, $meter, Ledger::CONSUME, $total - $counted, ['ref_type' => $refType, 'ref_id' => $refId] + $about);
            }

            return $counted;
        });
    }

    /**
     * A new lot (a purchase, a bonus, a positive adjustment); it covers any overage owed first.
     *
     * @param  array<string, mixed>  $about
     * @return string the lot's id
     */
    public function addLot(string $academyId, Meter $meter, string $kind, int $amount, CarbonImmutable $expiresAt, array $about = []): string
    {
        if (! in_array($kind, [Ledger::PURCHASE, Ledger::BONUS, Ledger::ADJUST], true) || $amount <= 0) {
            throw new InvalidArgumentException('A lot is a purchase, a bonus or an adjustment that adds.');
        }

        return $this->locked($academyId, $meter, function () use ($academyId, $meter, $kind, $amount, $expiresAt, $about): string {
            $this->grantAllowance($academyId, $meter, CarbonImmutable::now());

            return $this->newLot($academyId, $meter, $kind, $amount, $expiresAt, $about);
        });
    }

    /**
     * Takes back what is left of a lot, or some of it (a refund): never more than is left.
     *
     * @param  array<string, mixed>  $about
     */
    public function takeBack(string $academyId, Meter $meter, string $lotId, int $amount, array $about = []): void
    {
        $this->locked($academyId, $meter, function () use ($academyId, $meter, $lotId, $amount, $about): void {
            $lot = collect($this->lots($academyId, $meter, CarbonImmutable::now()))->first(fn (Lot $l) => $l->id === $lotId);
            if ($lot === null || $amount <= 0 || $amount > $lot->remaining) {
                throw new NotEnoughBalance($amount, $lot === null ? 0 : $lot->remaining);
            }
            $this->insert($academyId, $meter, Ledger::REFUND, -$amount, ['lot_id' => $lotId] + $about);
        });
    }

    /**
     * A negative adjustment: taken like a use, in spend order (overage when nothing is left).
     *
     * @param  array<string, mixed>  $about
     */
    public function adjustDown(string $academyId, Meter $meter, int $amount, array $about = []): void
    {
        $this->locked($academyId, $meter, fn () => $this->draw($academyId, $meter, Ledger::ADJUST, $amount, $about));
    }

    /** This month's allowance, written once (and again only for what a bigger plan this month adds). */
    public function ensureAllowance(string $academyId, Meter $meter, ?CarbonImmutable $at = null): void
    {
        if ($this->limitOf($academyId, $meter) === null) {
            return;
        }
        $this->locked($academyId, $meter, fn () => $this->grantAllowance($academyId, $meter, $at ?? CarbonImmutable::now()), alerts: false);
    }

    /** @return int how many lots ended with something left (an expiry each) */
    public function expireLots(string $academyId, Meter $meter, ?CarbonImmutable $at = null): int
    {
        $at ??= CarbonImmutable::now();

        return $this->locked($academyId, $meter, function () use ($academyId, $meter, $at): int {
            $ended = $this->db()->table('usage_ledger as l')
                ->leftJoin('usage_ledger as d', fn ($j) => $j->on('d.lot_id', '=', 'l.id')->whereColumn('d.id', '<>', 'l.id'))
                ->where('l.academy_id', $academyId)->where('l.meter', $meter->value)->whereColumn('l.lot_id', 'l.id')->where('l.expires_at', '<=', $at)
                ->groupBy('l.id', 'l.amount', 'l.expires_at')
                ->havingRaw("bool_or(d.kind = 'expire') is not true")
                ->havingRaw('l.amount + coalesce(sum(d.amount), 0) > 0')
                ->selectRaw('l.id, l.expires_at, l.amount + coalesce(sum(d.amount), 0) as left_over')
                ->get();
            foreach ($ended as $lot) {
                $this->insert($academyId, $meter, Ledger::EXPIRE, -(int) $lot->left_over, ['lot_id' => $lot->id, 'occurred_at' => CarbonImmutable::parse($lot->expires_at)]);
            }

            return $ended->count();
        }, alerts: false);
    }

    /** @return int the holds nobody settled in time, released now */
    public function releaseStale(string $academyId, Meter $meter, ?CarbonImmutable $at = null): int
    {
        $at ??= CarbonImmutable::now();

        return $this->locked($academyId, $meter, function () use ($academyId, $meter, $at): int {
            $stale = Reservation::stale($this->holds($academyId, $meter), $at);
            foreach ($stale as $hold) {
                $this->letGo($academyId, $meter, $hold);
            }

            return count($stale);
        }, alerts: false);
    }

    // Inside the lock -------------------------------------------------------------------------------------------------

    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function locked(string $academyId, Meter $meter, Closure $work, bool $alerts = true): mixed
    {
        $result = $this->db()->transaction(function () use ($academyId, $meter, $work) {
            $this->db()->select('select pg_advisory_xact_lock(hashtext(?))', [self::LOCK.$academyId.':'.$meter->value]);

            return $work();
        });
        // The alerts read the balance as it now stands, once it is committed (an outer transaction leaves them to the sweep).
        if ($alerts && $this->db()->transactionLevel() === 0) {
            try {
                app(UsageAlerts::class)->check($academyId, $meter);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $result;
    }

    private function grantAllowance(string $academyId, Meter $meter, CarbonImmutable $at): void
    {
        $subscription = SaasSubscription::currentFor($academyId);
        $limit = $subscription === null ? null : PlanTerms::of($subscription)->limit($meter->limit());
        if ($limit === null) {
            return;
        }
        $period = $this->month($academyId, $at);
        $key = $period->key($this->zone($academyId));
        $rows = $this->db()->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)
            ->where('kind', Ledger::ALLOWANCE)->where('period', $key)->get(['amount', 'ref_id']);
        if ($rows->contains('ref_id', $subscription->id)) {
            return;
        }
        // A bigger plan within the month adds what it adds; a smaller one takes nothing back.
        $amount = $limit - (int) $rows->sum('amount');
        if ($amount <= 0) {
            return;
        }
        // The month's own allowance belongs to its first day (in the statement it comes before the month's uses); what a bigger plan adds, to when.
        $since = $rows->isEmpty() ? $period->from : $at;
        $this->newLot($academyId, $meter, Ledger::ALLOWANCE, $amount, $period->to, ['period' => $key, 'ref_type' => 'subscription', 'ref_id' => $subscription->id, 'occurred_at' => $since]);
    }

    private function hasAllowance(string $academyId, Meter $meter, UsagePeriod $period): bool
    {
        $subscription = SaasSubscription::currentFor($academyId);

        return $subscription !== null && $this->db()->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)
            ->where('kind', Ledger::ALLOWANCE)->where('period', $period->key($this->zone($academyId)))->where('ref_id', $subscription->id)->exists();
    }

    /** @param  array<string, mixed>  $about */
    private function newLot(string $academyId, Meter $meter, string $kind, int $amount, CarbonImmutable $expiresAt, array $about): string
    {
        $id = (string) Str::uuid7();
        $this->insert($academyId, $meter, $kind, $amount, ['id' => $id, 'lot_id' => $id, 'expires_at' => $expiresAt] + $about);
        // The overage owed comes off the new lot first.
        $covered = Ledger::cover($this->debt($academyId, $meter), $amount);
        if ($covered > 0) {
            $this->insert($academyId, $meter, Ledger::CONSUME, -$covered, ['lot_id' => $id, 'ref_type' => 'overage', 'ref_id' => $id]);
            $this->insert($academyId, $meter, Ledger::CONSUME, $covered, ['overage' => true, 'ref_type' => 'overage', 'ref_id' => $id]);
        }

        return $id;
    }

    /**
     * Takes $amount in spend order; what no lot covers is overage. Without a limit it is only noted.
     *
     * @param  array<string, mixed>  $about
     */
    private function draw(string $academyId, Meter $meter, string $kind, int $amount, array $about): void
    {
        if ($amount <= 0) {
            return;
        }
        $now = CarbonImmutable::now();
        if ($this->limitOf($academyId, $meter) === null) {
            $this->insert($academyId, $meter, $kind, -$amount, $about);

            return;
        }
        $this->grantAllowance($academyId, $meter, $now);
        $draw = Ledger::draw($this->lots($academyId, $meter, $now), $amount, $now);
        foreach ($draw->fromLots as $lotId => $take) {
            $this->insert($academyId, $meter, $kind, -$take, ['lot_id' => $lotId] + $about);
        }
        if ($draw->overage > 0) {
            $this->insert($academyId, $meter, $kind, -$draw->overage, ['overage' => true] + $about);
        }
    }

    private function letGo(string $academyId, Meter $meter, string $holdId): void
    {
        $rows = $this->db()->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)->where('hold_id', $holdId)
            ->whereIn('kind', [Ledger::RESERVE, Ledger::RELEASE])->get(['kind', 'amount']);
        $reserved = $rows->firstWhere('kind', Ledger::RESERVE);
        if ($reserved === null || $rows->contains('kind', Ledger::RELEASE)) {
            return;
        }
        $this->insert($academyId, $meter, Ledger::RELEASE, -(int) $reserved->amount, ['hold_id' => $holdId]);
    }

    /** @return list<Lot> every lot still live at $at, with what is left of it */
    private function lots(string $academyId, Meter $meter, CarbonImmutable $at): array
    {
        return $this->db()->table('usage_ledger as l')
            ->leftJoin('usage_ledger as d', fn ($j) => $j->on('d.lot_id', '=', 'l.id')->whereColumn('d.id', '<>', 'l.id'))
            ->where('l.academy_id', $academyId)->where('l.meter', $meter->value)->whereColumn('l.lot_id', 'l.id')->where('l.expires_at', '>', $at)
            ->groupBy('l.id', 'l.kind', 'l.amount', 'l.expires_at', 'l.created_at')
            ->selectRaw('l.id, l.kind, l.expires_at, l.created_at, l.amount + coalesce(sum(d.amount), 0) as remaining')
            ->get()
            ->map(fn ($r) => new Lot((string) $r->id, (string) $r->kind, (int) $r->remaining, CarbonImmutable::parse($r->expires_at), CarbonImmutable::parse($r->created_at)))
            ->all();
    }

    /** The overage owed now (what uses no lot covered took, less what later lots covered). */
    private function debt(string $academyId, Meter $meter): int
    {
        return max(0, (int) -$this->db()->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)
            ->whereNull('lot_id')->where('overage', true)->sum('amount'));
    }

    /** @return list<Hold> the holds not let go yet */
    private function holds(string $academyId, Meter $meter): array
    {
        return $this->db()->table('usage_ledger')->where('academy_id', $academyId)->where('meter', $meter->value)
            ->whereIn('kind', [Ledger::RESERVE, Ledger::RELEASE])->whereNotNull('hold_id')
            ->groupBy('hold_id')->havingRaw("bool_or(kind = 'release') is not true")
            ->selectRaw('hold_id, -sum(amount) as held, min(created_at) as at')
            ->get()
            ->map(fn ($r) => new Hold((string) $r->hold_id, (int) $r->held, CarbonImmutable::parse($r->at)))
            ->all();
    }

    /** @param  array<string, mixed>  $about */
    private function insert(string $academyId, Meter $meter, string $kind, int $amount, array $about = []): void
    {
        $now = CarbonImmutable::now();
        $row = $this->only($about, ['id', 'lot_id', 'expires_at', 'period', 'overage', 'hold_id', 'subject', 'user_id', 'ref_type', 'ref_id', 'reason', 'actor_id', 'occurred_at']);
        $this->db()->table('usage_ledger')->insert($row + [
            'id' => (string) Str::uuid7(),
            'academy_id' => $academyId,
            'meter' => $meter->value,
            'kind' => $kind,
            'amount' => $amount,
            'overage' => false,
            'occurred_at' => $now,
            'created_at' => $now,
        ]);
    }

    /**
     * @param  array<string, mixed>  $about
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function only(array $about, array $keys): array
    {
        $out = array_intersect_key($about, array_flip($keys));
        if (isset($out['subject'])) {
            $out['subject'] = mb_substr((string) $out['subject'], 0, 40);
        }
        if (isset($out['ref_id'])) {
            $out['ref_id'] = mb_substr((string) $out['ref_id'], 0, 64);
        }

        return array_filter($out, fn ($v) => $v !== null);
    }

    private function db(): Connection
    {
        return DB::connection('pgsql_platform');
    }
}
