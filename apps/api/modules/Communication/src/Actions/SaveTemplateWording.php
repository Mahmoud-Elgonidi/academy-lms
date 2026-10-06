<?php

namespace Modules\Communication\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Communication\Models\TemplateWording;
use Modules\Communication\Templates\Renderer;
use Modules\Communication\Templates\TemplateRegistry;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * The academy rewords a template in one language. Only the template's own
 * :placeholders may be used; an empty wording goes back to the default.
 */
final class SaveTemplateWording
{
    public function __construct(
        private readonly TemplateRegistry $templates,
        private readonly Renderer $renderer,
        private readonly TenantContext $tenant,
    ) {}

    public function handle(string $key, string $locale, ?string $title, ?string $body, ?string $actorId): void
    {
        $template = $this->templates->get($key);
        $title = trim((string) $title);
        $body = trim((string) $body);

        if ($title === '' && $body === '') {
            TemplateWording::query()->where('template_key', $key)->where('locale', $locale)->delete();
            $this->renderer->forget();

            return;
        }
        if ($title === '' || $body === '') {
            throw ValidationException::withMessages([$title === '' ? 'title' : 'body' => __('communication::communication.errors.both')]);
        }
        preg_match_all('/:([a-z_]+)/', $title.' '.$body, $used);
        $unknown = array_diff(array_unique($used[1]), $template->placeholders());
        if ($unknown !== []) {
            throw ValidationException::withMessages(['body' => __('communication::communication.errors.placeholders', [
                'unknown' => implode('، ', array_map(fn ($p) => ":{$p}", $unknown)),
                'allowed' => implode('، ', array_map(fn ($p) => ":{$p}", $template->placeholders())) ?: '—',
            ])]);
        }

        DB::table('notification_templates')->updateOrInsert(
            ['academy_id' => $this->tenant->require()->id, 'template_key' => $key, 'locale' => $locale],
            ['title' => $title, 'body' => $body, 'updated_by' => $actorId, 'updated_at' => CarbonImmutable::now(), 'created_at' => CarbonImmutable::now()],
        );
        $this->renderer->forget();
    }
}
