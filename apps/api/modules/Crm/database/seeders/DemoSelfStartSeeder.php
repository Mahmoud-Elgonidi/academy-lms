<?php

namespace Modules\Crm\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Crm\Models\Lead;

/**
 * The family starts a course on its own in the demo (docs/architecture/02,
 * section 27, J3): self-serve is on (unless the demo's owner chose), and
 * Jana's mother, whose account the CRM made with her number alone, signs in
 * as jana.mother@demo.test with the demo's password, so «ابدأ بهذه الباقة» on
 * Jana's report opens the start flow (her consent for Jana is asked there).
 * Once: an account that has an email or a password already is left as it is
 * (DemoCatchUpSeeder runs this).
 */
final class DemoSelfStartSeeder extends Seeder
{
    public const EMAIL = 'jana.mother@demo.test';

    public function run(): void
    {
        DB::table('billing_settings')->whereNull('self_serve_saved_by')->update(['self_serve_enrol' => true]);

        $mother = Lead::query()->where('contact_name', 'أم جنى')->where('learner_name', 'جنى')->value('contact_id');
        if ($mother === null || DB::table('users')->where('email', self::EMAIL)->exists()) {
            return;
        }
        $account = DB::table('users')->where('id', $mother)->first(['email', 'password']);
        if ($account === null || $account->email !== null || $account->password !== null) {
            return;
        }
        DB::table('users')->where('id', $mother)->update([
            'email' => self::EMAIL,
            'password' => Hash::make((string) config('app.demo.password')),
            'updated_at' => now(),
        ]);
    }
}
