<?php

namespace Modules\Platform\Games\Designer;

use Illuminate\Support\Str;
use Modules\Platform\Games\StarterCatalog;

/**
 * A stand-in for the designer where no Claude key is set and the app is not
 * in production (GAME_DESIGNER=demo): it answers with one of the platform's
 * own games for the draft's first item kind, so the studio's whole round
 * (design, try, ask for changes, publish) can be walked locally and in the
 * demo. It changes nothing on a revision; it says so.
 */
final class DemoGameDesigner implements GameDesigner
{
    private const BY_KIND = ['order' => 'bird-gates', 'choice' => 'bird-balloons', 'pair' => 'bird-twins'];

    public function design(DesignBrief $brief): DesignedGame
    {
        $kind = $brief->kinds[0] ?? 'choice';
        $key = $kind === 'order' && $brief->island['key'] === 'turtles' ? 'turtle-bridge' : (self::BY_KIND[$kind] ?? 'bird-balloons');
        [, $ar, $en, $sumAr, $sumEn, $howAr, $howEn, , , $min, $max] = StarterCatalog::GAMES[$key];
        $words = Str::words(trim(preg_replace('/\s+/u', ' ', $brief->story) ?? ''), 3, '');

        return new DesignedGame(
            titleAr: mb_substr($ar.' · '.$words, 0, 80),
            titleEn: $en.' (demo)',
            summaryAr: $sumAr,
            summaryEn: $sumEn,
            howToPlayAr: $howAr,
            howToPlayEn: $howEn,
            stages: $brief->stages,
            kinds: [$kind],
            minItems: $min,
            maxItems: $max,
            message: $brief->isRevision()
                ? 'هذا هو المصمم التجريبي (دون Claude): وصلني طلبك لكنني لا أغيّر في اللعبة. ضع ANTHROPIC_API_KEY ليعدّل Claude فعلًا.'
                : 'هذا هو المصمم التجريبي (دون Claude): أعدت لك «'.$ar.'» من ألعاب المنصة لتجرّب الدورة كاملة. ضع ANTHROPIC_API_KEY ليبني Claude اللعبة من قصتك.',
            html: (string) file_get_contents(StarterCatalog::starterPath($key)),
            servedModel: 'demo',
        );
    }
}
