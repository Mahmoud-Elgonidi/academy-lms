<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A coupon for one payer (docs/architecture/02, section 27, J2): the offer
 * after a free trial. It is made by the academy's own rules, not typed by
 * staff: one payer may use it (payer_id), once, on the first invoice of a
 * course, until a moment (expires_at; ends_on is that moment's day on the
 * academy's calendar). The staff's own codes stay "general".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('purpose', 12)->default('general');   // general | trial_offer
            $table->uuid('payer_id')->nullable();                  // a personal coupon: only this payer may use it
            $table->timestampTz('expires_at')->nullable();         // a personal coupon: usable until this moment

            $table->tenantForeign('payer_id', 'users');
            $table->index(['academy_id', 'payer_id']);
        });
        DB::statement(<<<'SQL'
            alter table coupons
                add constraint coupons_purpose_check check (purpose in ('general', 'trial_offer')),
                add constraint coupons_personal_check check (
                    purpose = 'general'
                    or (payer_id is not null and expires_at is not null and max_uses = 1 and blocks = 1 and kind = 'percent'))
        SQL);
    }

    public function down(): void
    {
        DB::statement('alter table coupons drop constraint coupons_personal_check, drop constraint coupons_purpose_check');
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropIndex(['academy_id', 'payer_id']);
            $table->dropForeign(['academy_id', 'payer_id']);
            $table->dropColumn(['purpose', 'payer_id', 'expires_at']);
        });
    }
};
