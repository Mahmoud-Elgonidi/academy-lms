<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Family billing (docs/architecture/02, section 3): an invoice line says
 * which course (subscription) it is for, so one invoice can carry the
 * renewals of a family's courses that fall due in the same week; and a
 * plan can be a shared package ("12 lessons in any subject"), one balance
 * for all of a student's courses on it. invoices.subscription_id stays as
 * the one course an invoice is for, and is empty on a family invoice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->uuid('subscription_id')->nullable();

            $table->index(['academy_id', 'subscription_id']);
            $table->tenantForeign('subscription_id', 'subscriptions');
        });
        DB::statement(<<<'SQL'
            update invoice_lines l set subscription_id = i.subscription_id
            from invoices i
            where i.academy_id = l.academy_id and i.id = l.invoice_id and i.subscription_id is not null
        SQL);

        Schema::table('billing_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('family_invoice_days')->default(7);   // renewals due this close go on one invoice; 0: each on its own
        });
        DB::statement('alter table billing_settings add constraint billing_settings_family_days_check check (family_invoice_days <= 14)');

        Schema::table('plans', function (Blueprint $table) {
            $table->string('credit_scope', 16)->default('enrollment');        // enrollment | student_pool
        });
        DB::statement("alter table plans add constraint plans_credit_scope_check check (credit_scope in ('enrollment', 'student_pool'))");

        // One live subscription per balance: a shared package's courses all draw on its one account.
        DB::statement("create unique index subscriptions_one_live_account on subscriptions (academy_id, credit_account_id) where status <> 'cancelled'");
    }

    public function down(): void
    {
        DB::statement('drop index if exists subscriptions_one_live_account');
        Schema::table('plans', fn (Blueprint $table) => $table->dropColumn('credit_scope'));
        Schema::table('billing_settings', fn (Blueprint $table) => $table->dropColumn('family_invoice_days'));
        Schema::table('invoice_lines', fn (Blueprint $table) => $table->dropColumn('subscription_id'));
    }
};
