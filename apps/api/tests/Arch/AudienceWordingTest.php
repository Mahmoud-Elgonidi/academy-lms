<?php

use Illuminate\Support\Arr;

/*
 * An adults academy reads a module's lang/{locale}/adults/{file}.php first
 * (SharedKernel\Tenancy\AudienceTranslator), key by key over the base file.
 * A variant only rewords: every key in it must exist in the base file of the
 * same module and language, with the same shape (a line for a line, a group
 * for a group), so a renamed or removed key never leaves words behind that
 * nothing reads.
 */
it('rewords only keys the base files have', function () {
    $root = dirname(__DIR__, 2);
    $orphans = [];
    foreach (glob($root.'/modules/*/lang/*/adults/*.php') ?: [] as $variant) {
        $name = substr($variant, strlen($root) + 1);
        $base = dirname($variant, 2).'/'.basename($variant);
        if (! is_file($base)) {
            $orphans[] = "{$name}: no base file ".substr($base, strlen($root) + 1);

            continue;
        }
        $lines = require $variant;
        $baseLines = require $base;
        expect($lines)->toBeArray("{$name} returns an array");
        foreach (Arr::dot($lines) as $key => $line) {
            if (! Arr::has($baseLines, (string) $key)) {
                $orphans[] = "{$name}: {$key} is not in the base file";
            } elseif (is_array(Arr::get($baseLines, (string) $key)) !== is_array($line)) {
                $orphans[] = "{$name}: {$key} is a ".(is_array($line) ? 'group' : 'line').' there and not in the base file';
            }
        }
    }

    expect($orphans)->toBe([]);
});
