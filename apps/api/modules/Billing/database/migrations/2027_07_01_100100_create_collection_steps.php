<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The collection timeline (docs/architecture/02, section 27, J5a): each
 * message of it that went out, once. An invoice's steps (issued,
 * due_tomorrow, due_today, overdue, lesson_at_risk, and each pay_link the
 * office sent) or a course's renewal preview for its next block. `key` is
 * unique per academy, so a step claimed by one run is never sent by another
 * ("{invoice}:{step}", "{subscription}:renewal_preview:{block}",
 * "{invoice}:pay_link:{minute}"). payer_id is who it was for (their copy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->uuid('invoice_id')->nullable();
            $table->uuid('subscription_id')->nullable();
            $table->uuid('payer_id');
            $table->string('step', 20);
            $table->string('key', 120);
            $table->unsignedSmallInteger('recipients')->default(0);
            $table->uuid('sent_by')->nullable();           // the office's pay link; null: the timeline
            $table->timestampTz('sent_at');

            $table->tenantKey();
            $table->unique(['academy_id', 'key']);
            $table->index(['academy_id', 'invoice_id', 'step']);
            $table->index(['academy_id', 'payer_id']);
            $table->tenantForeign('invoice_id', 'invoices')->cascadeOnDelete();
            $table->tenantForeign('subscription_id', 'subscriptions')->cascadeOnDelete();
            $table->tenantForeign('payer_id', 'users');
        });
        DB::statement("alter table collection_steps add constraint collection_steps_step check (step in ('issued', 'due_tomorrow', 'due_today', 'overdue', 'lesson_at_risk', 'renewal_preview', 'pay_link'))");
        TenantTable::enableRls('collection_steps');
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_steps');
    }
};
