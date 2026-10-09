<?php

namespace Modules\Privacy;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** The people requests are about, read from the accounts (names, and whether one is already erased). */
final class Subjects
{
    public static function name(string $userId): string
    {
        return (string) DB::table('users')->where('id', $userId)->value('name');
    }

    public static function isErased(string $userId): bool
    {
        return DB::table('users')->where('id', $userId)->value('status') === 'erased';
    }

    public static function ensureNotErased(string $userId): void
    {
        $status = DB::table('users')->where('id', $userId)->value('status');
        if ($status === null || $status === 'erased') {
            throw ValidationException::withMessages(['subject' => __('privacy::requests.errors.erased')]);
        }
    }

    /** @param  list<string>  $ids  @return array<string, string> names by id */
    public static function names(array $ids): array
    {
        return $ids === [] ? [] : DB::table('users')->whereIn('id', array_values(array_unique($ids)))->pluck('name', 'id')->all();
    }
}
