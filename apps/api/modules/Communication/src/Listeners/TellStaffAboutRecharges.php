<?php

namespace Modules\Communication\Listeners;

use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\Events\AutoRechargeTurnedOff;
use Modules\SharedKernel\Plans\Events\RechargeFailed;
use Modules\SharedKernel\Plans\Events\RechargeSucceeded;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The academy's recharges, to the staff who see its plan (owner and admins),
 * in the inbox, by email and on their phones (docs/architecture/02, section
 * 11, "الشحن"): a pack bought online or automatically was added, an
 * automatic recharge was declined (tried again a day later, or turned off),
 * and automatic recharge turned itself off for another reason (no card, no
 * price). Platform raises the events (each once); this only words and sends.
 */
final class TellStaffAboutRecharges
{
    public const TEMPLATES = ['recharge_succeeded', 'recharge_failed', 'auto_recharge_off'];

    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly IdentityApi $identity,
        private readonly TenantContext $tenants,
    ) {}

    public static function define(NotificationsApi $notifications): void
    {
        $samples = [
            'recharge_succeeded' => ['meter' => 'دقائق الفيديو', 'amount' => '600 دقيقة', 'price' => '50.00 ر.س', 'how' => 'بالشحن التلقائي', 'receipt' => 'R-2026-000123'],
            'recharge_failed' => ['meter' => 'نقاط المساعد الآلي', 'reason' => 'رفض البنك العملية.', 'then' => 'سنحاول مرة أخرى بعد يوم.'],
            'auto_recharge_off' => ['meter' => 'دقائق الفيديو', 'reason' => 'لا توجد بطاقة محفوظة.'],
        ];
        foreach (self::TEMPLATES as $name) {
            $notifications->define(new TemplateDefinition(
                key: "plans.{$name}", category: 'staff',
                title: "communication::recharges.{$name}.title", body: "communication::recharges.{$name}.body",
                audience: 'communication::usage.audience', channels: ['push', 'email'], sample: $samples[$name] + ['link' => 'https://noor.example.com/admin/settings/usage'],
            ));
        }
    }

    public function succeeded(RechargeSucceeded $event): void
    {
        $this->send($event->academyId, 'recharge_succeeded', [
            'meter' => new Translatable('shared::plans.meters.'.$event->meter),
            'amount' => Translatable::count('communication::usage.units.'.$event->meter, $event->amount),
            'price' => $event->price,
            'how' => new Translatable('communication::recharges.how.'.$event->method),
            'receipt' => $event->receiptNumber,
        ], "plans-recharge:paid:{$event->purchaseId}");
    }

    public function failed(RechargeFailed $event): void
    {
        $this->send($event->academyId, 'recharge_failed', [
            'meter' => new Translatable('shared::plans.meters.'.$event->meter),
            'reason' => new Translatable('communication::recharges.declines.'.$event->reason),
            'then' => new Translatable('communication::recharges.then.'.($event->retries ? 'retry' : 'off')),
        ], "plans-recharge:failed:{$event->purchaseId}");
    }

    public function turnedOff(AutoRechargeTurnedOff $event): void
    {
        // A decline already said so (recharge_failed, "turned off").
        if ($event->reason === 'declined') {
            return;
        }
        $this->send($event->academyId, 'auto_recharge_off', [
            'meter' => new Translatable('shared::plans.meters.'.$event->meter),
            'reason' => new Translatable('communication::recharges.off.'.$event->reason),
        ], "plans-recharge:off:{$event->meter}:{$event->reason}:".now()->format('Y-m-d'));
    }

    /** @param  array<string, mixed>  $data */
    private function send(string $academyId, string $template, array $data, string $dedupe): void
    {
        if ($this->tenants->id() !== $academyId) {
            return;
        }
        $to = $this->identity->usersWithAbility(Ability::ViewPlan);
        if ($to !== []) {
            $this->notifications->send(new Message("plans.{$template}", $to, $data, ['screen' => 'usage'], $dedupe));
        }
    }
}
