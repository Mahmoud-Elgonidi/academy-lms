<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Models\SiteMedia;
use Modules\Site\Queries\MediaInUse;
use Modules\Site\Support\PageWords;

/**
 * The owner or an admin deletes a file from the page's library. Refused while
 * the draft, the published page or a kept publish uses it, saying where. The
 * row goes now, the file once that is committed.
 */
final class DeleteSiteMedia
{
    /** @throws ValidationException */
    public function handle(string $id): void
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }
        $media = Str::isUuid($id) ? SiteMedia::query()->find($id) : null;
        if ($media === null) {
            throw ValidationException::withMessages(['media' => __('site::builder.media.missing')]);
        }
        $where = MediaInUse::live()[$media->id] ?? null;
        if ($where !== null) {
            $names = [];
            foreach ($where as $type) {
                $names[] = $type === 'seo' ? (string) __('site::builder.media.used_seo') : PageWords::type($type);
            }
            throw ValidationException::withMessages(['media' => __('site::builder.media.in_use', ['where' => implode(app()->getLocale() === 'ar' ? '، ' : ', ', $names)])]);
        }
        DB::transaction(function () use ($media) {
            $path = $media->path;
            $media->delete();
            DB::afterCommit(fn () => Storage::disk(SiteMedia::DISK)->delete($path));
        });
    }
}
