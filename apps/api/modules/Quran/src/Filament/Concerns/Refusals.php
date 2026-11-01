<?php

namespace Modules\Quran\Filament\Concerns;

use Illuminate\Validation\ValidationException;

/** A refusal about one of a form's fields shows under it; the rest show as a notification (SharedKernel's ShowRefusals). */
final class Refusals
{
    /**
     * @template T
     *
     * @param  callable(): T  $do  the Action
     * @param  array<int|string, string>  $fields  the form's fields; a key names the refusal's field shown under another
     * @param  string  $path  where the form's fields live (data, mountedActions.0.data; empty: the page's own properties)
     * @return T
     */
    public static function under(callable $do, array $fields, string $path = 'mountedActions.0.data'): mixed
    {
        try {
            return $do();
        } catch (ValidationException $e) {
            $map = [];
            foreach ($fields as $from => $to) {
                $map[is_int($from) ? $to : $from] = $to;
            }
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(function (array $messages, string $key) use ($map, $path) {
                $field = explode('.', $key)[0];

                return [isset($map[$field]) ? ($path === '' ? '' : $path.'.').$map[$field].substr($key, strlen($field)) : $key => $messages];
            })->all());
        }
    }
}
