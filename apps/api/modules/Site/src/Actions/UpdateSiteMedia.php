<?php

namespace Modules\Site\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\SharedKernel\Authorization\Ability;
use Modules\Site\Domain\Page\PageDoc;
use Modules\Site\Domain\Page\Problems;
use Modules\Site\Models\SiteMedia;

/** The owner or an admin words a library file's alt text (what a screen reader says, and search engines read), in Arabic and English. */
final class UpdateSiteMedia
{
    /**
     * @param  array<string, mixed>  $alt  {ar, en}, each at most 200 characters
     *
     * @throws ValidationException
     */
    public function handle(string $id, array $alt): SiteMedia
    {
        if (! Gate::allows(Ability::ManageSite->value)) {
            throw new AuthorizationException;
        }
        $media = Str::isUuid($id) ? SiteMedia::query()->find($id) : null;
        if ($media === null) {
            throw ValidationException::withMessages(['media' => __('site::builder.media.missing')]);
        }
        $clean = PageDoc::text($alt, 200, 'alt', $problems = new Problems);
        if (! $problems->none()) {
            throw ValidationException::withMessages(['alt' => __('site::builder.errors.too_long', ['max' => 200, 'field' => __('site::builder.media.alt')])]);
        }
        $media->forceFill(['alt' => $clean])->save();

        return $media;
    }
}
