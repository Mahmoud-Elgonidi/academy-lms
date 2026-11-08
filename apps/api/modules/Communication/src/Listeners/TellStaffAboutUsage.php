<?php

namespace Modules\Communication\Listeners;

use Modules\Communication\Contracts\DTOs\Day;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\DTOs\Translatable;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Identity\Contracts\IdentityApi;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Plans\Events\BalanceRunningOut;
use Modules\SharedKernel\Plans\Events\LotExpiringSoon;
use Modules\SharedKernel\Plans\Events\UsageThresholdReached;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The academy's usage, to the staff who see its plan (owner and admins), in
 * the inbox, by email and on their phones (docs/architecture/02, section
 * 11): half, most and all of the month's allowance of a meter used, bought
 * balance ending soon, and bought balance running out at the recent pace.
 * Platform raises the events (each once); this only words and sends them.
 */
final class TellStaffAboutUsage
{
    public const TEMPLATES = ['usage_half', 'usage_near', 'usage_full', 'usage_expiring', 'usage_runout'];

    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly IdentityApi $identity,
        private readonly TenantContext $tenants,
    ) {}

    public static function define(NotificationsApi $notifications): void
    {
        $samples = [
            'usage_half' => ['meter' => 'نقاط المساعد الآلي', 'used' => '120 نقطة', 'allowance' => '240 نقطة', 'then' => 'وبعد نفاده يُستخدم الرصيد الذي اشتريتموه.'],
            'usage_near' => ['meter' => 'دقائق الفيديو', 'used' => '480 دقيقة', 'allowance' => '600 دقيقة', 'then' => 'وبعد نفاده يُستخدم الرصيد الذي اشتريتموه.'],
            'usage_full' => ['meter' => 'دقائق الفيديو', 'used' => '600 دقيقة', 'allowance' => '600 دقيقة', 'then' => 'ويُستخدم الآن الرصيد الذي اشتريتموه.'],
            'usage_expiring' => ['meter' => 'دقائق الفيديو', 'left' => '150 دقيقة', 'on' => 'الأحد 6 ديسمبر'],
            'usage_runout' => ['meter' => 'نقاط المساعد الآلي', 'left' => '90 نقطة', 'days' => '6 أيام'],
        ];
        foreach (self::TEMPLATES as $name) {
            $notifications->define(new TemplateDefinition(
                key: "plans.{$name}", category: 'staff',
                title: "communication::usage.{$name}.title", body: "communication::usage.{$name}.body",
                audience: 'communication::usage.audience', channels: ['push', 'email'], sample: $samples[$name] + ['link' => 'https://noor.example.com/admin/settings/usage'],
            ));
        }
    }

    public function threshold(UsageThresholdReached $event): void
    {
        $name = match (true) {
            $event->threshold >= 100 => 'usage_full',
            $event->threshold >= 80 => 'usage_near',
            default => 'usage_half',
        };
        $this->send($event->academyId, $name, [
            'meter' => new Translatable('shared::plans.meters.'.$event->meter),
            'used' => $this->amount($event->meter, $event->used),
            'allowance' => $this->amount($event->meter, $event->allowance),
            // What happens now: the bought balance is used next, else the meter's own way (no lesson is cut; the helpers stop).
            'then' => new Translatable('communication::usage.then.'.($event->bought > 0 ? 'bought' : $event->meter).($event->threshold >= 100 ? '' : '_later')),
        ], "plans-usage:{$event->meter}:{$event->period}:{$event->threshold}");
    }

    public function expiring(LotExpiringSoon $event): void
    {
        $tz = $this->tenants->require()->defaultTimezone;
        $this->send($event->academyId, 'usage_expiring', [
            'meter' => new Translatable('shared::plans.meters.'.$event->meter),
            'left' => $this->amount($event->meter, $event->left),
            'on' => new Day($event->expiresAt->setTimezone($tz)->subSecond()->toDateString()),
        ], "plans-usage:lot:{$event->lotId}");
    }

    public function runningOut(BalanceRunningOut $event): void
    {
        $this->send($event->academyId, 'usage_runout', [
            'meter' => new Translatable('shared::plans.meters.'.$event->meter),
            'left' => $this->amount($event->meter, $event->left),
            'days' => Translatable::count('communication::usage.units.days', max(1, $event->days)),
        ], "plans-usage:runout:{$event->meter}:".now()->format('Y-m'));
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

    private function amount(string $meter, int $n): Translatable
    {
        return Translatable::count('communication::usage.units.'.$meter, $n);
    }
}
