<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Discounts (docs/architecture/02, section 3, "الخصومات"): the family
 * discount from the second child on, as a rate on the academy's settings,
 * and coupons, a code staff give out that takes a percentage or an amount
 * off a subscription's invoices (the first, the first N, or every one).
 * A coupon is used once per payer; what it took off stays on the invoices
 * as their own lines, so nothing here is counted twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('sibling_second_bp')->default(0);   // the second child: 1000 = 10%
            $table->unsignedSmallInteger('sibling_more_bp')->default(0);     // the third child and after
        });
        DB::statement('alter table billing_settings add constraint billing_settings_sibling_check check (sibling_second_bp <= 10000 and sibling_more_bp <= 10000)');

        Schema::create('coupons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('code', 32);                         // upper case, as typed: RAMADAN10
            $table->string('name', 80)->nullable();             // for staff: "Ramadan campaign"
            $table->string('kind', 8);                          // percent | amount
            $table->unsignedSmallInteger('percent_bp')->nullable();
            $table->bigInteger('amount_minor')->nullable();
            $table->char('currency', 3)->nullable();
            $table->unsignedSmallInteger('blocks')->nullable(); // the invoices it takes off; null: every one
            $table->date('starts_on')->nullable();              // when it can be used, on the academy's calendar
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->jsonb('plan_ids')->default('[]');           // the plans it is for; empty: any
            $table->boolean('with_sibling')->default(false);    // on top of the family discount, or the bigger of the two
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'code']);
        });
        DB::statement(<<<'SQL'
            alter table coupons
                add constraint coupons_kind_check check (kind in ('percent', 'amount')),
                add constraint coupons_value_check check (
                    (kind = 'percent' and percent_bp between 1 and 10000 and amount_minor is null and currency is null)
                    or (kind = 'amount' and amount_minor > 0 and currency is not null and percent_bp is null)),
                add constraint coupons_blocks_check check (blocks is null or blocks >= 1),
                add constraint coupons_dates_check check (starts_on is null or ends_on is null or ends_on >= starts_on),
                add constraint coupons_code_check check (code = upper(code) and code ~ '^[A-Z0-9_-]{3,32}$')
        SQL);
        TenantTable::enableRls('coupons');

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('coupon_id');
            $table->uuid('billing_account_id');
            $table->uuid('subscription_id');
            $table->uuid('applied_by')->nullable();
            $table->timestampTz('applied_at');
            $table->uuid('removed_by')->nullable();
            $table->timestampTz('removed_at')->nullable();     // no more invoices off from then

            $table->tenantKey();
            $table->tenantForeign('coupon_id', 'coupons');
            $table->tenantForeign('billing_account_id', 'billing_accounts');
            $table->tenantForeign('subscription_id', 'subscriptions');
        });
        // One coupon at a time on a subscription, and a coupon once per payer's account.
        DB::statement('create unique index coupon_redemptions_one_live on coupon_redemptions (academy_id, subscription_id) where removed_at is null');
        DB::statement('create unique index coupon_redemptions_once on coupon_redemptions (academy_id, coupon_id, billing_account_id) where removed_at is null');
        TenantTable::enableRls('coupon_redemptions');

        // What a coupon took off, read back by redemption.
        DB::statement("create index invoice_lines_discount on invoice_lines (academy_id, (meta->>'redemption_id')) where kind = 'discount'");

        // A new invoice reaches its payer's open screens too, not only a payment on it.
        DB::statement(<<<'SQL'
            create or replace function lms_invoice_paid_changed() returns trigger language plpgsql as $$
            declare
                payer uuid;
                changed boolean;
            begin
                if tg_op = 'INSERT' then
                    changed := true;
                else
                    changed := new.paid_minor is distinct from old.paid_minor or new.status is distinct from old.status;
                end if;
                if changed then
                    select ba.payer_id into payer from billing_accounts ba where ba.academy_id = new.academy_id and ba.id = new.billing_account_id;
                    if payer is not null then
                        perform pg_notify('lms_live', 'payments:' || payer);
                    end if;
                end if;
                return null;
            end
            $$
        SQL);
        DB::statement('create trigger invoices_live_issued after insert on invoices for each row execute function lms_invoice_paid_changed()');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists invoices_live_issued on invoices');
        DB::statement('drop index if exists invoice_lines_discount');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
        DB::statement('alter table billing_settings drop constraint if exists billing_settings_sibling_check');
        Schema::table('billing_settings', fn (Blueprint $table) => $table->dropColumn(['sibling_second_bp', 'sibling_more_bp']));
    }
};
