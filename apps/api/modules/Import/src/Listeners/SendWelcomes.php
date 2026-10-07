<?php

namespace Modules\Import\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Communication\Contracts\DTOs\Localized;
use Modules\Communication\Contracts\DTOs\Message;
use Modules\Communication\Contracts\DTOs\TemplateDefinition;
use Modules\Communication\Contracts\NotificationsApi;
use Modules\Import\Contracts\Events\PeopleImported;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The welcome (wording in lang/{ar,en}/notifications.php): each person an
 * import made an account for gets their own link to choose a password, on
 * WhatsApp and email (they have no app yet). Children under a parent get
 * none; their parent signs in.
 */
final class SendWelcomes
{
    public function __construct(
        private readonly NotificationsApi $notifications,
        private readonly TenantContext $tenant,
    ) {}

    public static function templates(NotificationsApi $notifications): void
    {
        foreach ([['welcome_teacher', 'staff', 'teacher'], ['welcome_family', 'lessons', 'family']] as [$name, $category, $audience]) {
            $notifications->define(new TemplateDefinition(
                key: "import.{$name}", category: $category,
                title: "import::notifications.{$name}.title", body: "import::notifications.{$name}.body",
                audience: "import::notifications.audience.{$audience}", channels: ['whatsapp', 'email'],
                sample: ['name' => 'أ. سارة', 'academy' => 'أكاديمية النور', 'link' => 'https://demo.lms.test/welcome/…'],
            ));
        }
    }

    public function handle(PeopleImported $event): void
    {
        if (! $event->invite) {
            return;
        }
        $tenant = $this->tenant->require();
        $people = DB::table('users')->whereIn('id', $event->userIds)->whereNull('password')->where('is_managed_minor', false)->get(['id', 'name']);
        foreach ($people as $person) {
            $teacher = DB::table('user_roles')->where('user_id', $person->id)->where('role', 'teacher')->exists();
            $this->notifications->send(new Message($teacher ? 'import.welcome_teacher' : 'import.welcome_family', [$person->id], [
                'name' => $person->name,
                'academy' => new Localized($tenant->nameAr, $tenant->nameEn),
            ], ['screen' => 'welcome', 'id' => $person->id], 'welcome:'.$person->id));
        }
    }
}
