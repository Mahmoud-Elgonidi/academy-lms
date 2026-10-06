<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * Plans, prices, subscriptions, invoices and payments
 * (docs/architecture/02, section 3; 03, section 5). Money is minor units +
 * ISO currency. Gateways (Stripe, Moyasar, Paymob) come later; this is the
 * engine they plug into, with manual payments first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->tenant();
            $table->unsignedInteger('tax_rate_bp')->default(0);      // basis points: 1500 = 15%
            $table->boolean('prices_include_tax')->default(true);
            $table->string('tax_number', 30)->nullable();
            $table->string('invoice_prefix', 12)->default('INV-');
            $table->unsignedSmallInteger('due_days')->default(3);
            $table->timestampsTz();

            $table->primary('academy_id');
        });
        TenantTable::enableRls('billing_settings');

        Schema::create('price_regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('name_ar', 60);
            $table->string('name_en', 60);
            $table->char('currency', 3);
            $table->jsonb('countries')->default('[]');
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestampsTz();

            $table->tenantKey();
        });
        TenantTable::enableRls('price_regions');

        // A block plan: sessions_per_block sessions of session_minutes, usually
        // sessions_per_week a week, valid for validity_days from payment.
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('name_ar', 80);
            $table->string('name_en', 80);
            $table->unsignedSmallInteger('session_minutes');
            $table->unsignedSmallInteger('sessions_per_block');
            $table->unsignedSmallInteger('sessions_per_week');
            $table->unsignedSmallInteger('validity_days');
            $table->unsignedSmallInteger('renew_at_sessions')->default(2); // renewal invoice when this many are left
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestampsTz();

            $table->tenantKey();
        });
        DB::statement('alter table plans add constraint plans_positive check (session_minutes > 0 and sessions_per_block > 0 and sessions_per_week > 0 and validity_days > 0)');
        TenantTable::enableRls('plans');

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('plan_id');
            $table->uuid('price_region_id');
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->timestampsTz();

            $table->unique(['academy_id', 'plan_id', 'price_region_id']);
            $table->tenantForeign('plan_id', 'plans')->cascadeOnDelete();
            $table->tenantForeign('price_region_id', 'price_regions')->cascadeOnDelete();
        });
        DB::statement('alter table plan_prices add constraint plan_prices_non_negative check (amount_minor >= 0)');
        TenantTable::enableRls('plan_prices');

        // Who pays: a guardian or an adult student. The currency is fixed per account.
        Schema::create('billing_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('payer_id');
            $table->uuid('price_region_id');
            $table->char('currency', 3);
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'payer_id', 'currency']);
            $table->tenantForeign('payer_id', 'users');
            $table->tenantForeign('price_region_id', 'price_regions');
        });
        TenantTable::enableRls('billing_accounts');

        Schema::create('billing_account_students', function (Blueprint $table) {
            $table->tenant();
            $table->uuid('billing_account_id');
            $table->uuid('student_id');

            $table->primary(['academy_id', 'billing_account_id', 'student_id']);
            $table->tenantForeign('billing_account_id', 'billing_accounts')->cascadeOnDelete();
            $table->tenantForeign('student_id', 'users');
        });
        TenantTable::enableRls('billing_account_students');

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('billing_account_id');
            $table->uuid('enrollment_id');
            $table->uuid('plan_id');
            $table->uuid('credit_account_id');
            $table->bigInteger('amount_minor');         // the block price agreed, kept when the plan changes
            $table->char('currency', 3);
            $table->string('status', 20)->default('active'); // active | past_due | paused | cancelled
            $table->unsignedInteger('block_no')->default(0);  // blocks paid so far
            $table->timestampsTz();

            $table->tenantKey();
            $table->index(['academy_id', 'credit_account_id']);
            $table->tenantForeign('billing_account_id', 'billing_accounts');
            $table->tenantForeign('enrollment_id', 'enrollments');
            $table->tenantForeign('plan_id', 'plans');
            $table->tenantForeign('credit_account_id', 'credit_accounts');
        });
        DB::statement("create unique index subscriptions_one_live on subscriptions (academy_id, enrollment_id) where status <> 'cancelled'");
        TenantTable::enableRls('subscriptions');

        // Gapless numbering: one row per academy, locked while a number is taken.
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->tenant();
            $table->unsignedBigInteger('next_no')->default(1);

            $table->primary('academy_id');
        });
        TenantTable::enableRls('invoice_sequences');

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('number', 30)->nullable();   // given when issued, never reused
            $table->uuid('billing_account_id');
            $table->uuid('subscription_id')->nullable();
            $table->string('status', 10)->default('draft'); // draft | issued | paid | void
            $table->char('currency', 3);
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('tax_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('paid_minor')->default(0);
            $table->unsignedInteger('tax_rate_bp')->default(0);
            $table->boolean('prices_include_tax')->default(true);
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->string('void_reason', 200)->nullable();
            $table->uuid('voided_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();

            $table->tenantKey();
            $table->unique(['academy_id', 'number']);
            $table->index(['academy_id', 'status', 'due_at']);
            $table->index(['academy_id', 'subscription_id']);
            $table->tenantForeign('billing_account_id', 'billing_accounts');
            $table->tenantForeign('subscription_id', 'subscriptions');
        });
        DB::statement('alter table invoices add constraint invoices_amounts check (paid_minor >= 0 and paid_minor <= total_minor and total_minor >= 0)');
        TenantTable::enableRls('invoices');

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('invoice_id');
            $table->string('kind', 12);                 // block | extra | adjustment
            $table->string('description_ar', 200);
            $table->string('description_en', 200);
            $table->unsignedInteger('quantity')->default(1);
            $table->bigInteger('unit_minor');
            $table->bigInteger('amount_minor');
            $table->jsonb('meta')->default('{}');       // block: minutes, block_no, validity_days

            $table->index(['academy_id', 'invoice_id']);
            $table->tenantForeign('invoice_id', 'invoices')->cascadeOnDelete();
        });
        TenantTable::enableRls('invoice_lines');

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('invoice_id');
            $table->string('method', 20);               // bank_transfer | instapay | wallet | cash | card_terminal | other
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('reference', 120)->nullable();
            $table->timestampTz('received_at');
            $table->uuid('recorded_by')->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('created_at');

            $table->index(['academy_id', 'invoice_id']);
            $table->tenantForeign('invoice_id', 'invoices');
        });
        DB::statement('alter table payments add constraint payments_positive check (amount_minor > 0)');
        TenantTable::enableRls('payments');
        DB::statement('revoke update, delete on payments from lms_app');
    }

    public function down(): void
    {
        foreach (['payments', 'invoice_lines', 'invoices', 'invoice_sequences', 'subscriptions', 'billing_account_students', 'billing_accounts', 'plan_prices', 'plans', 'price_regions', 'billing_settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
