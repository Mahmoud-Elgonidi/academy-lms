<?php

namespace Modules\Platform\Games;

use Illuminate\Http\Response;

/**
 * A game's page as the browser and the app get it: the platform's bridge
 * first in <head> (resources/games/bridge.js), and a policy of its own
 * (docs/architecture/03, section 11, "games"): the page runs sandboxed in a
 * unique origin (no cookies, no storage, no way to our pages), reaches no
 * network and loads nothing from outside; it may only run its own inline
 * code, which is what a game is. This is the one page whose scripts are not
 * held to the request's nonce: the sandbox and connect-src 'none' hold it
 * instead, and PackageCheck reads every page before it is published.
 */
final class GamePage
{
    public const POLICY = "default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data: blob:; media-src data: blob:; "
        ."font-src data:; connect-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'; sandbox allow-scripts";

    public static function html(string $page): string
    {
        $bridge = '<script>'.self::bridge().'</script>';
        if (preg_match('/<head\b[^>]*>/i', $page, $m, PREG_OFFSET_CAPTURE)) {
            $at = $m[0][1] + strlen($m[0][0]);

            return substr($page, 0, $at).$bridge.substr($page, $at);
        }

        return $bridge.$page;
    }

    /** The answer for a page: published versions never change, drafts are never kept. */
    public static function response(string $page, bool $published): Response
    {
        return new Response(self::html($page), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => self::POLICY,
            'Referrer-Policy' => 'no-referrer',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cache-Control' => $published ? 'public, max-age=31536000, immutable' : 'no-store',
        ]);
    }

    public static function bridge(): string
    {
        static $bridge = null;

        return $bridge ??= (string) file_get_contents(dirname(__DIR__, 2).'/resources/games/bridge.js');
    }
}
