<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Authorization\Ability;
use Modules\SharedKernel\Files\CleanFile;
use Modules\SharedKernel\Tenancy\TenantContext;
use Modules\Site\Domain\Page\Clean;
use Modules\Site\Domain\Page\PageDoc;
use Modules\Site\Domain\Page\Problems;
use Modules\Site\Models\SiteMedia;
use Modules\Site\Models\SitePage;
use Throwable;

/**
 * The owner or an admin adds an image or a video to the page's library
 * (site-builder.md, 2): a JPEG, PNG or WebP up to 5 MB and at least 200 px
 * on each side (the browser shrinks it to 2560 px first), or an MP4 or WebM
 * up to 50 MB; by the file's bytes, never its name, and scanned
 * (CleanFile). Never an SVG, a GIF or anything else. An academy keeps 300
 * files and 1 GB at most. The file goes on the public disk under
 * site/{academy}/media/{id}.{ext} (a name that never changes); if its row
 * cannot be written, the file goes too. Not audited (the page is public).
 */
final class UploadSiteMedia
{
    public function __construct(private readonly TenantContext $tenants) {}

    /**
     * @param  array<string, mixed>  $alt  {ar, en}: the alt text images show with by default
     *
     * @throws ValidationException
     */
    public function handle(UploadedFile $file, array $alt = []): SiteMedia
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }
        $mime = (string) $file->getMimeType();
        $video = isset(SiteMedia::VIDEOS[$mime]);
        $label = ['file' => __('site::builder.media.file')];
        Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'mimetypes:'.implode(',', [...array_keys(SiteMedia::IMAGES), ...array_keys(SiteMedia::VIDEOS)]),
                'max:'.($video ? SiteMedia::MAX_VIDEO_KB : SiteMedia::MAX_IMAGE_KB), new CleanFile],
        ], ['file.mimetypes' => __('site::builder.media.wrong_type'), 'file.max' => __('site::builder.media.too_big', ['mb' => ($video ? SiteMedia::MAX_VIDEO_KB : SiteMedia::MAX_IMAGE_KB) / 1024])], $label)->validate();

        $width = null;
        $height = null;
        if (! $video) {
            $size = @getimagesize((string) $file->getRealPath());
            if ($size === false || ! isset(SiteMedia::IMAGES[$size['mime'] ?? '']) || $size['mime'] !== $mime) {
                throw ValidationException::withMessages(['file' => __('site::builder.media.wrong_type')]);
            }
            [$width, $height] = [(int) $size[0], (int) $size[1]];
            if ($width < SiteMedia::MIN_IMAGE_SIDE || $height < SiteMedia::MIN_IMAGE_SIDE) {
                throw ValidationException::withMessages(['file' => __('site::builder.media.too_small', ['px' => SiteMedia::MIN_IMAGE_SIDE])]);
            }
        }
        $altText = PageDoc::text($alt, 200, 'alt', $problems = new Problems);
        if (! $problems->none()) {
            throw ValidationException::withMessages(['alt' => __('site::builder.errors.too_long', ['max' => 200, 'field' => __('site::builder.media.alt')])]);
        }
        $name = mb_substr(Clean::text(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME)), 0, 120);
        $bytes = (int) $file->getSize();
        $academy = $this->tenants->require()->id;
        $id = (string) Str::uuid7();
        $extension = SiteMedia::IMAGES[$mime] ?? SiteMedia::VIDEOS[$mime];

        return DB::transaction(function () use ($file, $mime, $video, $width, $height, $altText, $name, $bytes, $academy, $id, $extension) {
            // One upload at a time per academy, so two at once cannot pass the limits together.
            $page = SitePage::lockedForUpdate();
            if (! $page->exists) {
                $page->save();
            }
            $count = SiteMedia::query()->count();
            $total = (int) SiteMedia::query()->sum('bytes');
            if ($count >= SiteMedia::MAX_FILES || $total + $bytes > SiteMedia::MAX_BYTES) {
                throw ValidationException::withMessages(['file' => __('site::builder.media.full')]);
            }
            $path = $file->storeAs(SiteMedia::directory($academy), "{$id}.{$extension}", ['disk' => SiteMedia::DISK]);
            if (! is_string($path)) {
                throw ValidationException::withMessages(['file' => __('site::builder.media.not_stored')]);
            }
            try {
                $media = new SiteMedia;
                $media->forceFill([
                    'id' => $id, 'kind' => $video ? 'video' : 'image', 'mime' => $mime, 'path' => $path, 'bytes' => $bytes,
                    'width' => $width, 'height' => $height, 'name' => $name !== '' ? $name : $id, 'alt' => $altText, 'uploaded_by' => auth()->id(),
                ])->save();
            } catch (Throwable $e) {
                Storage::disk(SiteMedia::DISK)->delete($path);
                throw $e;
            }

            return $media;
        });
    }
}
