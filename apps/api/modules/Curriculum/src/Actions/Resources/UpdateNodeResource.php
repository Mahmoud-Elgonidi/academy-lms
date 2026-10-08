<?php

namespace Modules\Curriculum\Actions\Resources;

use Modules\Curriculum\Models\NodeResource;

/** Changes a resource's words, who sees it, and a link's address. A file is changed by adding the new one and taking the old one off. */
final class UpdateNodeResource
{
    /** @param  array{title_ar?: ?string, title_en?: ?string, audience?: ?string, url?: ?string}  $data */
    public function handle(NodeResource $resource, array $data): NodeResource
    {
        $fields = ResourceRules::details($data + ['title_fallback' => $resource->file_name ? pathinfo($resource->file_name, PATHINFO_FILENAME) : null]);
        if ($resource->kind === 'link') {
            $fields['url'] = ResourceRules::url($data['url'] ?? $resource->url);
        }
        $resource->fill($fields)->save();

        return $resource;
    }
}
