<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\Platform\Games\GamePage;
use Modules\Platform\Models\Games\GameDraft;

/** A draft's page, for the studio's preview only (signed-in platform team, never cached). */
final class GameDraftPageController
{
    public function __invoke(string $draft): Response
    {
        abort_unless(Str::isUuid($draft), 404);
        $html = GameDraft::query()->whereKey($draft)->value('html');
        abort_if($html === null, 404);

        return GamePage::response($html, published: false);
    }
}
