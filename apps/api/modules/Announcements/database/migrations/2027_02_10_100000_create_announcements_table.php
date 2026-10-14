<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The academy's announcements to families or teachers (docs/architecture/02,
 * section 20): the words, who they were for, which channels besides the
 * inbox, who sent them and to how many people. Written once when sent:
 * never changed or deleted (the grants are revoked), like what was said in a
 * conversation. Who received them is in each person's inbox (notifications).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('title', 120);
            $table->text('body');                                   // plain text, line breaks kept, up to 1,000 characters
            $table->string('audience', 10);                         // families | guardians | students | subject | teachers
            $table->string('stage', 8)->nullable();                 // students only: kids | middle | teen | adult
            $table->uuid('subject_id')->nullable();                 // subject only: the families of its courses that have not ended
            $table->jsonb('channels')->default('[]');               // besides the inbox: push, email
            $table->unsignedInteger('recipients_count');
            $table->uuid('sent_by');
            $table->timestampTz('sent_at');

            $table->tenantKey();
            $table->index(['academy_id', 'sent_at']);
            $table->index(['academy_id', 'sent_by']);
            $table->tenantForeign('sent_by', 'users');
            $table->tenantForeign('subject_id', 'subjects');
        });
        DB::statement("alter table announcements add constraint announcements_audience check (audience in ('families', 'guardians', 'students', 'subject', 'teachers'))");
        DB::statement("alter table announcements add constraint announcements_stage check (stage is null or (audience = 'students' and stage in ('kids', 'middle', 'teen', 'adult')))");
        DB::statement("alter table announcements add constraint announcements_subject check ((audience = 'subject') = (subject_id is not null))");
        DB::statement('alter table announcements add constraint announcements_words check (char_length(btrim(title)) > 0 and char_length(btrim(body)) between 1 and 1000)');
        DB::statement("alter table announcements add constraint announcements_channels check (jsonb_typeof(channels) = 'array' and channels <@ '[\"push\", \"email\"]'::jsonb)");
        TenantTable::enableRls('announcements');
        DB::statement('revoke update, delete on announcements from lms_app');
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
