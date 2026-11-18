<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\SharedKernel\Tenancy\Database\TenantTable;

/*
 * The academy's page builder (docs/architecture/site-builder.md, section 1):
 * one page per academy with the draft its owner or admins edit and the page
 * that is live (each a Domain\Page\PageDoc), the last publishes kept so a
 * page can be brought back (site:sweep keeps 20), and the page's own
 * images and videos (public disk, site/{academy}/media/{id}.{ext}; files
 * nothing uses for 7 days leave with site:sweep).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_pages', function (Blueprint $table) {
            $table->tenant();
            $table->jsonb('draft');                                 // Domain\Page\PageDoc
            $table->jsonb('published')->nullable();                 // null: nothing published yet (the page falls back to the old one)
            $table->unsignedInteger('draft_revision')->default(1);  // +1 on every draft save
            $table->unsignedInteger('published_revision')->nullable();
            $table->string('template', 32)->nullable();             // the template the draft started from
            $table->timestampTz('draft_saved_at')->nullable();
            $table->uuid('draft_saved_by')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestampsTz();

            $table->primary('academy_id');
            $table->tenantForeign('draft_saved_by', 'users');
            $table->tenantForeign('published_by', 'users');
        });
        DB::statement("alter table site_pages add constraint site_pages_draft check (jsonb_typeof(draft) = 'object')");
        DB::statement("alter table site_pages add constraint site_pages_published check (published is null or jsonb_typeof(published) = 'object')");
        TenantTable::enableRls('site_pages');

        Schema::create('site_page_publishes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->unsignedInteger('revision');
            $table->jsonb('page');
            $table->uuid('published_by')->nullable();
            $table->timestampTz('published_at');

            $table->tenantKey();
            $table->index(['academy_id', 'published_at']);
        });
        DB::statement('alter table site_page_publishes add constraint site_page_publishes_by_fk foreign key (academy_id, published_by) references users (academy_id, id) on delete set null (published_by)');
        DB::statement("alter table site_page_publishes add constraint site_page_publishes_page check (jsonb_typeof(page) = 'object')");
        TenantTable::enableRls('site_page_publishes');

        Schema::create('site_media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->tenant();
            $table->string('kind', 8);                              // image | video
            $table->string('mime', 32);                             // from the file's bytes
            $table->string('path', 255);                            // public disk: site/{academy}/media/{id}.{ext}
            $table->unsignedBigInteger('bytes');
            $table->unsignedInteger('width')->nullable();           // images only
            $table->unsignedInteger('height')->nullable();
            $table->string('name', 120);                            // the original name, cleaned: the library shows it
            $table->jsonb('alt')->default('{}');                    // {ar, en}: the default alt text
            $table->uuid('uploaded_by')->nullable();
            $table->timestampsTz();
            $table->timestampTz('unused_since')->nullable();        // set by site:sweep while nothing uses it

            $table->tenantKey();
            $table->index(['academy_id', 'created_at']);
            $table->tenantForeign('uploaded_by', 'users');
        });
        DB::statement("alter table site_media add constraint site_media_kind check (kind in ('image', 'video'))");
        DB::statement("alter table site_media add constraint site_media_mime check (mime in ('image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm'))");
        DB::statement("alter table site_media add constraint site_media_alt check (jsonb_typeof(alt) = 'object')");
        TenantTable::enableRls('site_media');
    }

    public function down(): void
    {
        Schema::dropIfExists('site_media');
        Schema::dropIfExists('site_page_publishes');
        Schema::dropIfExists('site_pages');
    }
};
