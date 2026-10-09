<?php

namespace Modules\People\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The welcome (wording in lang/{ar,en}/notifications.php): each person the
 * academy made an account for (by hand or from a sheet) gets their own link
 * to choose a password, on WhatsApp and email (they have no app yet), after
 * commit. Who already has a password, and children under a parent (their
 * parent signs in), get none.
 */
final class SendWelcome
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly TenantContext $tenant,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        foreach ([['welcome_teacher', 'staff', 'new_teacher'], ['welcome_family', 'lessons', 'new_family']] as [$name, $category, $audience]) {
            $notifications->define(new TemplateDefinition(
                key: "people.{$name}", category: $category,
                title: "people::notifications.{$name}.title", body: "people::notifications.{$name}.body",
                audience: "people::notifications.audience.{$audience}", channels: ['whatsapp', 'email'],
                sample: ['name' => 'أ. سارة', 'academy' => 'أكاديمية النور', 'link' => 'https://demo.lms.test/welcome/…'],
            ));
        }
    }

    /** @param  list<string>  $userIds */
    public function handle(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }
        DB::afterCommit(function () use ($userIds) {
            $tenant = $this->tenant->require();
            $people = DB::table('users')->whereIn('id', $userIds)->whereNull('password')->where('is_managed_minor', false)->where('status', 'active')->get(['id', 'name']);
            foreach ($people as $person) {
                $teacher = DB::table('user_roles')->where('user_id', $person->id)->where('role', 'teacher')->exists();
                $this->notifications->send(new Message($teacher ? 'people.welcome_teacher' : 'people.welcome_family', [$person->id], [
                    'name' => $person->name,
                    'academy' => new Localized($tenant->nameAr, $tenant->nameEn),
                ], ['screen' => 'welcome', 'id' => $person->id], 'welcome:'.$person->id));
            }
        });
    }
}
