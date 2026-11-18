<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Files\ScanVerdict;
use Modules\SharedKernel\Files\UploadScanner;
use Modules\SharedKernel\Plans\Limit;
use Modules\SharedKernel\Plans\UsageMeters;
use Modules\SharedKernel\Plans\UsagePeriod;
use Modules\Site\Actions\DeleteSiteMedia;
use Modules\Site\Actions\SaveSiteDraft;
use Modules\Site\Actions\UpdateSiteMedia;
use Modules\Site\Actions\UploadSiteMedia;
use Modules\Site\Models\SiteMedia;
use Modules\Site\Models\SitePage;
use Modules\Site\Models\SitePagePublish;
use Tests\Support\AcademySite;
use Tests\Support\SitePages;

uses(AcademySite::class, SitePages::class);

/*
 * The page's media library (docs/architecture/site-builder.md, 2): pictures
 * (JPEG, PNG, WebP) and videos (MP4, WebM) by their bytes, never their name;
 * never an SVG, a GIF, HTML or a PDF; held to their sizes and the academy's
 * 300 files; scanned; on the public disk under the academy's folder. A file
 * in use cannot be deleted, and site:sweep lets go of one unused for a week.
 * The bytes count in the storage meter.
 */

beforeEach(function () {
    $this->withoutDefer();
    Mail::fake();
    Storage::fake('public');
    $this->siteAcademy();
    $this->actAsTenant($this->academy);
    $this->actingAs($this->owner);
});

function upload(string $fixture, ?string $as = null, array $alt = []): SiteMedia
{
    return app(UploadSiteMedia::class)->handle(test()->siteFile($fixture, $as), $alt);
}

it('takes pictures and videos by their bytes, sized, on the public disk under the academy', function () {
    $webp = upload('hero.webp', 'Photo of us.webp', ['ar' => 'قوس', 'en' => 'An arch']);
    $png = upload('logo-1.png', 'logo.jpg');            // named wrong: the bytes decide
    $jpeg = upload('photo.jpg', 'photo');
    $mp4 = upload('clip.mp4', 'clip.mov');
    $webm = upload('clip.webm');

    expect([$webp->mime, $png->mime, $jpeg->mime, $mp4->mime, $webm->mime])->toBe(['image/webp', 'image/png', 'image/jpeg', 'video/mp4', 'video/webm'])
        ->and([$webp->kind, $mp4->kind])->toBe(['image', 'video'])
        ->and([$webp->width, $webp->height])->toBe([2400, 1600])
        ->and([$mp4->width, $mp4->height])->toBe([null, null])
        ->and($webp->path)->toBe("site/{$this->academy->id}/media/{$webp->id}.webp")
        ->and($png->path)->toEndWith('.png')
        ->and($webp->name)->toBe('Photo of us')
        ->and($webp->alt)->toBe(['ar' => 'قوس', 'en' => 'An arch'])
        ->and($webp->url())->toBe("/storage/site/{$this->academy->id}/media/{$webp->id}.webp");
    Storage::disk('public')->assertExists($webp->path);

    config(['site.media_absolute' => true]);
    expect($webp->url())->toBe(Storage::disk('public')->url($webp->path));

    // The storage meter counts them.
    expect(app(UsageMeters::class)->measure(Limit::StorageMb, UsagePeriod::monthOf(now()->toImmutable(), 'UTC')))->toBeGreaterThanOrEqual(1);
});

it('refuses an SVG, HTML, a GIF or a PDF whatever its name, and files too large or too small', function () {
    foreach ([['svg.png', 'logo.png'], ['page.mp4', 'clip.mp4'], ['anim.gif', 'anim.gif'], ['doc.pdf', 'doc.jpg']] as [$fixture, $as]) {
        expect(fn () => upload($fixture, $as))->toThrow(ValidationException::class);
    }
    expect(fn () => upload('tiny.png'))->toThrow(ValidationException::class)
        ->and(fn () => app(UploadSiteMedia::class)->handle($this->bigSiteFile('photo.jpg', 5200, 'big.jpg')))->toThrow(ValidationException::class)
        ->and(fn () => app(UploadSiteMedia::class)->handle($this->bigSiteFile('clip.mp4', 51300, 'big.mp4')))->toThrow(ValidationException::class)
        ->and(SiteMedia::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('scans every file, holds the academy to 300 files, and lets only the owner and admins upload', function () {
    $this->app->instance(UploadScanner::class, new class implements UploadScanner
    {
        public function scan(string $path): ScanVerdict
        {
            return ScanVerdict::infected('Test-Signature');
        }

        public function ping(): ?string
        {
            return null;
        }

        public function version(): ?string
        {
            return 'test';
        }
    });
    expect(fn () => upload('hero.webp'))->toThrow(ValidationException::class);
    $this->app->forgetInstance(UploadScanner::class);
    $this->app->bind(UploadScanner::class, fn () => new class implements UploadScanner
    {
        public function scan(string $path): ScanVerdict
        {
            return ScanVerdict::clean();
        }

        public function ping(): ?string
        {
            return null;
        }

        public function version(): ?string
        {
            return 'test';
        }
    });

    $one = upload('hero.webp');
    $rows = [];
    for ($i = 1; $i < SiteMedia::MAX_FILES; $i++) {
        $rows[] = ['id' => (string) Str::uuid7(), 'academy_id' => $this->academy->id, 'kind' => 'image', 'mime' => 'image/png', 'path' => "x/{$i}.png", 'bytes' => 10, 'name' => "f{$i}", 'alt' => '{}', 'created_at' => now(), 'updated_at' => now()];
    }
    DB::table('site_media')->insert($rows);
    expect(fn () => upload('logo-1.png'))->toThrow(ValidationException::class);

    $this->actingAs($this->sales);
    expect(fn () => app(UploadSiteMedia::class)->handle($this->siteFile('hero.webp')))->toThrow(AuthorizationException::class)
        ->and(fn () => app(DeleteSiteMedia::class)->handle($one->id))->toThrow(AuthorizationException::class)
        ->and(fn () => app(UpdateSiteMedia::class)->handle($one->id, []))->toThrow(AuthorizationException::class);
});

it('keeps a file the draft, the page or a kept version uses, saying where; deletes it once unused', function () {
    $hero = upload('hero.webp');
    $logo = upload('logo-1.png');
    $this->publishPage([['type' => 'hero', 'variant' => 'split', 'data' => ['title' => ['ar' => 'x'], 'media' => $hero->id]], ['type' => 'form']]);
    app(SaveSiteDraft::class)->handle(['settings' => ['seo' => ['image' => $logo->id]], 'blocks' => [['type' => 'form']]], SitePage::current()->draft_revision);

    try {
        app(DeleteSiteMedia::class)->handle($hero->id);
        $this->fail('deleted');
    } catch (ValidationException $e) {
        expect(collect($e->errors())->flatten()->first())->toContain(__('site::builder.types.hero.name'));
    }
    expect(fn () => app(DeleteSiteMedia::class)->handle($logo->id))->toThrow(ValidationException::class);

    app(UpdateSiteMedia::class)->handle($hero->id, ['ar' => 'قوس', 'en' => str_repeat('x', 10)]);
    expect($hero->refresh()->alt)->toBe(['ar' => 'قوس', 'en' => 'xxxxxxxxxx'])
        ->and(fn () => app(UpdateSiteMedia::class)->handle($hero->id, ['ar' => str_repeat('x', 201)]))->toThrow(ValidationException::class);

    $clip = upload('clip.mp4');
    app(DeleteSiteMedia::class)->handle($clip->id);
    expect(SiteMedia::query()->find($clip->id))->toBeNull();
    Storage::disk('public')->assertMissing($clip->path);
});

it('lets site:sweep mark unused files, unmark used ones, and delete what stayed unused a week; it keeps 20 versions', function () {
    $used = upload('hero.webp');
    $unused = upload('logo-1.png');
    $this->publishPage([['type' => 'hero', 'variant' => 'split', 'data' => ['title' => ['ar' => 'x'], 'media' => $used->id]], ['type' => 'form']]);
    for ($i = 0; $i < 21; $i++) {
        $this->travel(1)->minutes();
        $this->publishPage([['type' => 'hero', 'variant' => 'split', 'data' => ['title' => ['ar' => "v{$i}"], 'media' => $used->id]], ['type' => 'form']]);
    }
    $this->clearTenant();

    $this->artisan('site:sweep')->assertSuccessful();
    $this->actAsTenant($this->academy);
    expect($unused->refresh()->unused_since)->not->toBeNull()
        ->and($used->refresh()->unused_since)->toBeNull()
        ->and(SitePagePublish::query()->count())->toBe(20);

    $this->travel(8)->days();
    $this->clearTenant();
    $this->artisan('site:sweep')->assertSuccessful();
    $this->actAsTenant($this->academy);
    expect(SiteMedia::query()->pluck('id')->all())->toBe([$used->id]);
    Storage::disk('public')->assertMissing($unused->path);
});
