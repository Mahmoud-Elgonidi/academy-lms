<?php

namespace Modules\Platform\Directory;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Platform\Contracts\AccountDirectory;
use Modules\Platform\Contracts\DTOs\DiscoveredAccount;
use Modules\Platform\Enums\AcademyStatus;

final class PlatformAccountDirectory implements AccountDirectory
{
    public function withEmail(string $email): array
    {
        return $this->find('email', $email);
    }

    public function withPhone(string $phoneE164): array
    {
        return $this->find('phone_e164', $phoneE164);
    }

    /** @return list<DiscoveredAccount> */
    private function find(string $column, string $value): array
    {
        $rows = DB::connection('pgsql_platform')->table('users as u')
            ->join('academies as a', 'a.id', '=', 'u.academy_id')
            ->leftJoin('academy_branding as b', 'b.academy_id', '=', 'a.id')
            ->where("u.{$column}", $value)
            ->where('u.status', 'active')
            ->where('a.status', AcademyStatus::Active->value)
            ->orderBy('a.name_en')->orderBy('u.name')
            ->get(['u.id', 'u.name', 'u.academy_id', 'a.slug', 'a.name_ar', 'a.name_en', 'b.logo_path', 'b.tokens']);

        $roles = DB::connection('pgsql_platform')->table('user_roles')
            ->whereIn('user_id', $rows->pluck('id'))
            ->orderBy('role')
            ->get(['user_id', 'role'])
            ->groupBy('user_id');

        return $rows->map(fn (object $row) => new DiscoveredAccount(
            academyId: $row->academy_id,
            academySlug: $row->slug,
            academyNameAr: $row->name_ar,
            academyNameEn: $row->name_en,
            logoUrl: $row->logo_path ? Storage::disk('public')->url($row->logo_path) : null,
            colour: $row->tokens ? (json_decode($row->tokens, true)['primary'] ?? null) : null,
            userId: $row->id,
            userName: $row->name,
            roles: ($roles[$row->id] ?? collect())->pluck('role')->values()->all(),
        ))->values()->all();
    }
}
