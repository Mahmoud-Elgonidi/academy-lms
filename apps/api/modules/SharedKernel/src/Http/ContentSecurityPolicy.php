<?php

namespace Modules\SharedKernel\Http;

use Illuminate\Support\Facades\Vite;

/**
 * Which scripts a page may run (docs/architecture/03, section 11): files
 * from its own origin and the scripts that carry this answer's nonce. A
 * script that found its way into the page another way (an inline <script>,
 * an onclick=, a javascript: link in text that reached the HTML) does not
 * run. Every <script> tag a Blade template writes takes the nonce when the
 * template is compiled (nonceScripts()), so the templates' own scripts run,
 * ours and the packages', and text from people never gets one; Livewire's,
 * Filament's and Vite's tags read the same nonce from Vite. Alpine and
 * Livewire evaluate the panels' expressions and @script blocks with eval,
 * hence 'unsafe-eval'.
 */
final class ContentSecurityPolicy
{
    /** What every answer carries: no foreign <base>, no plugins, framed only by our own pages. */
    public const BASE = "base-uri 'self'; object-src 'none'; frame-ancestors 'self'";

    /** A fresh nonce for this request (SecurityHeaders, before anything renders). */
    public static function start(): string
    {
        return Vite::useCspNonce();
    }

    /** A page's policy: the base one, and its scripts. */
    public static function forPage(string $nonce): string
    {
        return self::BASE."; script-src 'self' 'nonce-{$nonce}' 'unsafe-eval'";
    }

    /** ` nonce="…"` for a <script> tag; nothing outside a request (a mail, a command). */
    public static function attribute(): string
    {
        $nonce = Vite::cspNonce();

        return $nonce === null ? '' : ' nonce="'.e($nonce).'"';
    }

    /**
     * The Blade precompiler: each <script> tag in a template, unless it names
     * a nonce already, prints the request's one. It works on the template's
     * source, before anything is filled in, so only what a template itself
     * writes gets it.
     */
    public static function nonceScripts(string $template): string
    {
        return preg_replace_callback(
            '/<script\b(?![^>]*\bnonce\s*=)/i',
            fn (): string => '<script{!! \\'.self::class.'::attribute() !!}',
            $template,
        ) ?? $template;
    }
}
