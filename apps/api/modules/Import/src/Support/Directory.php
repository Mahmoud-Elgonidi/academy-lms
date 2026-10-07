<?php

namespace Modules\Import\Support;

use Illuminate\Support\Facades\DB;
use Modules\SharedKernel\Contact\Phone;

/** Finding the people a row means, among the academy's accounts (reads may join across modules). */
final class Directory
{
    /** @return object{id: string, name: string, roles: list<string>}|null */
    public function byContact(?string $email, ?string $phone): ?object
    {
        $user = null;
        if ($email !== null && $email !== '') {
            $user = DB::table('users')->whereRaw('lower(email) = ?', [mb_strtolower($email)])->first(['id', 'name']);
        }
        if ($user === null && $phone !== null && $phone !== '') {
            $user = DB::table('users')->where('phone_e164', $phone)->first(['id', 'name']);
        }

        return $user === null ? null : (object) ['id' => $user->id, 'name' => $user->name, 'roles' => $this->roles($user->id)];
    }

    /** A teacher by email, number or exact name (one only). */
    public function teacher(string $text, string $callingCode): ?string
    {
        $text = trim($text);
        $found = match (true) {
            str_contains($text, '@') => $this->byContact($text, null),
            strlen((string) preg_replace('/\D/', '', Text::digits($text))) >= 7 => $this->byContact(null, Phone::normalize($text, $callingCode)),
            default => null,
        };
        if ($found !== null) {
            return in_array('teacher', $found->roles, true) ? $found->id : null;
        }
        $named = DB::table('users as u')->join('user_roles as r', fn ($j) => $j->on('r.academy_id', '=', 'u.academy_id')->on('r.user_id', '=', 'u.id'))
            ->where('r.role', 'teacher')->get(['u.id', 'u.name'])
            ->filter(fn ($u) => Text::key($u->name) === Text::key($text) || Text::key(preg_replace('/^(ا|أ)\.\s*/u', '', $u->name) ?? '') === Text::key($text));

        return $named->count() === 1 ? $named->first()->id : null;
    }

    /** A guardian's child by name. */
    public function childNamed(string $guardianId, string $name): ?string
    {
        $children = DB::table('guardian_student as g')->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'g.academy_id')->on('u.id', '=', 'g.student_id'))
            ->where('g.guardian_id', $guardianId)->get(['u.id', 'u.name'])
            ->filter(fn ($u) => Text::key($u->name) === Text::key($name));

        return $children->count() === 1 ? $children->first()->id : null;
    }

    /** A subject by its Arabic or English name. */
    public function subject(string $name): ?string
    {
        return DB::table('subjects')->get(['id', 'name_ar', 'name_en'])
            ->first(fn ($s) => in_array(Text::key($name), [Text::key($s->name_ar), Text::key($s->name_en)], true))?->id;
    }

    public function timezoneOf(string $userId): ?string
    {
        return DB::table('users')->where('id', $userId)->value('timezone');
    }

    /** @return list<string> */
    public function roles(string $userId): array
    {
        return DB::table('user_roles')->where('user_id', $userId)->pluck('role')->all();
    }
}
