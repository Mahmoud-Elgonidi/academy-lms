<?php

namespace Modules\Platform\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Platform\Actions\UpdateAcademyIdentity;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The demo academy's logo (our own drawing, files/demo-logo.png: a book
 * under an eight-pointed star in the Noor template's colours), set the way
 * Settings › Identity and colours sets it: the file on the public disk in the
 * academy's own folder, then UpdateAcademyIdentity with the names it has.
 * Its name and logo are the first of "Start here"'s essentials. Runs inside
 * the demo academy.
 */
final class DemoBrandingSeeder extends Seeder
{
    public function run(UpdateAcademyIdentity $identity, TenantContext $tenants): void
    {
        $academy = $tenants->require();
        $path = UpdateAcademyIdentity::directory($academy->id).'/logo.png';
        Storage::disk('public')->put($path, (string) file_get_contents(__DIR__.'/files/demo-logo.png'));

        $identity->handle($academy->nameAr, $academy->nameEn, $path, null, DB::table('users')->where('email', 'owner@demo.test')->value('id'));
    }
}
