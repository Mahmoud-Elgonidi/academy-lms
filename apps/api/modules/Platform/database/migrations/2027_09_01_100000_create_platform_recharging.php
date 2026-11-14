<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Recharging (docs/architecture/02, section 11, "الشحن"): the platform is the
 * merchant and the academy buys packs of video minutes or AI credits.
 *
 * - A pack has a price per currency (saas_topup_packs.prices: {"SAR": 9900});
 *   one with no price in the academy's currency is bought by transfer only.
 * - The academy's Stripe customer on the platform's account (platform_customers)
 *   and its saved card (platform_payment_methods: only Stripe's references,
 *   encrypted, with what is shown: brand, last four, expiry; one live a academy).
 * - Each purchase (topup_purchases): a Stripe Checkout page (card), a charge on
 *   the saved card (auto) or a transfer the platform team turned on (transfer);
 *   open → paid | failed | expired, once. It adds its lot to the usage ledger
 *   once (usage_ledger_one_purchase: ref_type purchase, ref_id the purchase)
 *   and gets one receipt (platform_receipts, numbered R-2026-000123, never changed).
 * - Automatic recharge per meter (auto_recharges): when the balance falls below
 *   the threshold, buy the pack, at most so many times a calendar month.
 * - Stripe's events for the platform's own account, kept once (platform_webhook_inbox).
 *
 * Only the platform writes these, on its own connection. The academy's usage
 * page hears when a purchase moves (recharge:{academy}).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Prices per currency, from the one price a pack had.
        Schema::table('saas_topup_packs', fn (Blueprint $table) => $table->jsonb('prices')->default('{}'));
        DB::statement('alter table saas_topup_packs no force row level security');
        DB::update('update saas_topup_packs set prices = jsonb_build_object(currency, price_minor) where price_minor is not null and currency is not null');
        DB::statement('alter table saas_topup_packs force row level security');
        DB::statement('alter table saas_topup_packs drop constraint if exists saas_topup_packs_price');
        Schema::table('saas_topup_packs', fn (Blueprint $table) => $table->dropColumn(['price_minor', 'currency']));
        DB::statement("alter table saas_topup_packs add constraint saas_topup_packs_prices check (jsonb_typeof(prices) = 'object')");

        Schema::create('platform_customers', function (Blueprint $table) {
            $table->tenant();
            $table->text('customer_ref');                        // Stripe's cus_…, encrypted
            $table->timestampsTz();
            $table->primary('academy_id');
        });

        Schema::create('platform_payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->text('customer_ref');                        // encrypted
            $table->text('method_ref');                          // Stripe's pm_…, encrypted
            $table->string('brand', 20);
            $table->char('last4', 4);
            $table->smallInteger('exp_month');
            $table->smallInteger('exp_year');
            $table->uuid('saved_by')->nullable();                // users.id: who ticked "save my card"
            $table->timestampTz('removed_at')->nullable();
            $table->string('removed_reason', 20)->nullable();    // removed, replaced, declined
            $table->timestampsTz();
        });
        DB::statement('create unique index platform_payment_methods_one_live on platform_payment_methods (academy_id) where removed_at is null');

        Schema::create('topup_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->foreignUuid('pack_id')->nullable()->constrained('saas_topup_packs')->restrictOnDelete();
            $table->string('limit', 30);                         // the pack's monthly limit (video_minutes, ai_uses)
            $table->integer('amount');                           // units added (minutes, credits)
            $table->bigInteger('price_minor');                   // what was asked (card, auto) or what arrived (transfer)
            $table->char('currency', 3);
            $table->string('method', 10);                        // card, auto, transfer
            $table->string('status', 10)->default('open');       // open → paid | failed | expired
            $table->string('provider_ref', 120)->nullable();     // the Checkout Session (cs_…) or the PaymentIntent (pi_…)
            $table->string('payment_ref', 120)->nullable();      // the PaymentIntent that paid
            $table->boolean('save_card')->default(false);
            $table->uuid('payment_method_id')->nullable();       // the saved card charged (auto)
            $table->smallInteger('attempt')->default(1);         // auto: 2 is the one retry after a soft decline
            $table->uuid('retry_of')->nullable();
            $table->string('failure', 40)->nullable();           // Payments\CardDecline
            $table->uuid('requested_by')->nullable();            // users.id (card), platform_admins.id (transfer)
            $table->uuid('topup_id')->nullable();                // usage_topups (transfer)
            $table->uuid('lot_id')->nullable();                  // the lot it added (usage_ledger)
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampsTz();
            $table->index(['academy_id', 'method', 'created_at']);
            $table->index(['status', 'created_at']);
        });
        DB::statement("alter table topup_purchases add constraint topup_purchases_limit check (\"limit\" in ('video_minutes', 'ai_uses'))");
        DB::statement('alter table topup_purchases add constraint topup_purchases_amount check (amount > 0 and price_minor >= 0)');
        DB::statement("alter table topup_purchases add constraint topup_purchases_method check (method in ('card', 'auto', 'transfer'))");
        DB::statement("alter table topup_purchases add constraint topup_purchases_status check (status in ('open', 'paid', 'failed', 'expired'))");
        DB::statement("alter table topup_purchases add constraint topup_purchases_paid check ((status = 'paid') = (paid_at is not null))");
        DB::statement('create unique index topup_purchases_provider on topup_purchases (provider_ref) where provider_ref is not null');
        DB::statement('create unique index topup_purchases_topup on topup_purchases (topup_id) where topup_id is not null');
        DB::statement('create unique index topup_purchases_one_retry on topup_purchases (retry_of) where retry_of is not null');

        Schema::create('platform_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->foreignUuid('purchase_id')->unique()->constrained('topup_purchases')->restrictOnDelete();
            $table->string('number', 20)->unique();              // R-2026-000123
            $table->smallInteger('year');
            $table->integer('seq');
            $table->string('seller', 150);                       // the platform's legal name at issue
            $table->string('academy_name', 200);
            $table->string('pack_name_ar', 80);
            $table->string('pack_name_en', 80);
            $table->string('limit', 30);
            $table->integer('amount');
            $table->bigInteger('price_minor');
            $table->char('currency', 3);
            $table->string('method', 10);
            $table->timestampTz('paid_at');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['year', 'seq']);
            $table->index(['academy_id', 'paid_at']);
        });

        Schema::create('auto_recharges', function (Blueprint $table) {
            $table->tenant();
            $table->string('meter', 20);                         // SharedKernel\Plans\Meter
            $table->foreignUuid('pack_id')->constrained('saas_topup_packs')->restrictOnDelete();
            $table->integer('threshold');                        // in the meter's unit
            $table->smallInteger('monthly_cap');                 // automatic purchases a calendar month, on the academy's clock
            $table->boolean('enabled')->default(false);
            $table->uuid('updated_by')->nullable();              // users.id
            $table->string('off_reason', 30)->nullable();        // why it turned itself off (declined, no_card, no_price)
            $table->timestampTz('off_at')->nullable();
            $table->timestampsTz();
            $table->primary(['academy_id', 'meter']);
        });
        DB::statement("alter table auto_recharges add constraint auto_recharges_meter check (meter in ('video_minutes', 'ai_credits'))");
        DB::statement('alter table auto_recharges add constraint auto_recharges_amounts check (threshold >= 0 and monthly_cap between 1 and 50)');

        Schema::create('platform_webhook_inbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_id', 190)->unique();
            $table->string('event', 80);
            $table->string('ref', 120)->nullable();
            $table->jsonb('payload');
            $table->timestampTz('received_at');
            $table->timestampTz('processed_at')->nullable();
            $table->string('error', 500)->nullable();
        });

        DB::unprepared(<<<'SQL'
            -- A purchase moves once, from open; what was bought stays as it was.
            create or replace function lms_topup_purchases_forward() returns trigger language plpgsql as $$
            begin
                if old.status <> 'open'
                   or (to_jsonb(new) - 'status' - 'provider_ref' - 'payment_ref' - 'failure' - 'lot_id' - 'paid_at' - 'failed_at' - 'payment_method_id' - 'updated_at')
                      <> (to_jsonb(old) - 'status' - 'provider_ref' - 'payment_ref' - 'failure' - 'lot_id' - 'paid_at' - 'failed_at' - 'payment_method_id' - 'updated_at') then
                    raise exception 'a purchase moves once, from open, and what was bought stays as it was';
                end if;
                return new;
            end $$;
            create trigger lms_topup_purchases_forward before update on topup_purchases
                for each row execute function lms_topup_purchases_forward();

            -- The academy's usage page follows its purchases.
            create or replace function lms_topup_purchases_live() returns trigger language plpgsql as $$
            begin
                perform pg_notify('lms_live', 'recharge:' || new.academy_id);
                return null;
            end $$;
            create trigger lms_topup_purchases_live after insert or update on topup_purchases
                for each row execute function lms_topup_purchases_live();

            -- A receipt is never changed.
            create or replace function lms_platform_receipts_append_only() returns trigger language plpgsql as $$
            begin
                raise exception 'a receipt is never changed';
            end $$;
            create trigger lms_platform_receipts_append_only before update or delete on platform_receipts
                for each row execute function lms_platform_receipts_append_only();
            SQL);

        foreach (['platform_customers', 'platform_payment_methods', 'topup_purchases', 'platform_receipts', 'auto_recharges'] as $t) {
            TenantTable::enableRls($t);
            DB::statement("revoke insert, update, delete on {$t} from lms_app");
        }
        DB::statement('revoke select on platform_customers, platform_payment_methods from lms_app');
        DB::statement('revoke delete on topup_purchases, platform_receipts, platform_payment_methods from lms_platform');
        DB::statement('alter table platform_webhook_inbox enable row level security');
        DB::statement('alter table platform_webhook_inbox force row level security');
        DB::statement('revoke all on platform_webhook_inbox from lms_app');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_webhook_inbox');
        Schema::dropIfExists('auto_recharges');
        Schema::dropIfExists('platform_receipts');
        Schema::dropIfExists('topup_purchases');
        Schema::dropIfExists('platform_payment_methods');
        Schema::dropIfExists('platform_customers');
        DB::unprepared('drop function if exists lms_topup_purchases_forward(); drop function if exists lms_topup_purchases_live(); drop function if exists lms_platform_receipts_append_only();');
        DB::statement('alter table saas_topup_packs drop constraint if exists saas_topup_packs_prices');
        Schema::table('saas_topup_packs', function (Blueprint $table) {
            $table->bigInteger('price_minor')->nullable();
            $table->char('currency', 3)->nullable();
        });
        DB::statement('alter table saas_topup_packs no force row level security');
        DB::update("update saas_topup_packs set currency = (select min(k) from jsonb_object_keys(prices) k), price_minor = (prices ->> (select min(k) from jsonb_object_keys(prices) k))::bigint where prices <> '{}'::jsonb");
        DB::statement('alter table saas_topup_packs force row level security');
        Schema::table('saas_topup_packs', fn (Blueprint $table) => $table->dropColumn('prices'));
    }
};
