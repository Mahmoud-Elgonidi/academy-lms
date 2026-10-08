<?php

namespace Modules\People\Queries;

use Illuminate\Support\Facades\DB;
use Modules\People\Consent\ConsentRules;
use Modules\People\Contracts\DTOs\ConsentState;
use Modules\People\Models\GuardianConsent;
use Modules\People\Models\GuardianStudent;
use Modules\People\Models\PeopleSettings;

/**
 * What a guardian sees of their children's consent (the family area, the
 * app and the portal alike): each child linked to them, whether they wait,
 * and who agreed, how and when; and the wording to agree to.
 */
final class FamilyConsents
{
    public function __construct(private readonly ConsentRules $rules) {}

    /** @return list<array{student: array{id: string, name: string}, state: ConsentState}> */
    public function children(string $guardianId): array
    {
        $ids = GuardianStudent::query()->where('guardian_id', $guardianId)->pluck('student_id')->all();
        if ($ids === []) {
            return [];
        }
        $names = DB::table('users')->whereIn('id', $ids)->where('status', 'active')->orderBy('name')->pluck('name', 'id');
        $states = $this->rules->states(array_keys($names->all()));

        return $names->map(fn (string $name, string $id) => ['student' => ['id' => $id, 'name' => $name], 'state' => $states[$id]])->values()->all();
    }

    /** @return list<string> names of the guardian's children waiting for consent */
    public function waiting(string $guardianId): array
    {
        return collect($this->children($guardianId))->filter(fn ($c) => $c['state']->waiting())->map(fn ($c) => $c['student']['name'])->values()->all();
    }

    /**
     * The wording a guardian agrees to, for one child.
     *
     * @return array{version: int, title: string, intro: string, sections: list<array{title: string, body: string}>, agree: string, age: int}
     */
    public function text(string $studentName, string $academyName): array
    {
        $p = ['student' => $studentName, 'academy' => $academyName];

        return [
            'version' => GuardianConsent::VERSION,
            'title' => __('people::consent.text.title', $p),
            'intro' => __('people::consent.text.intro', $p),
            'sections' => array_map(fn (string $key) => [
                'title' => __("people::consent.text.sections.{$key}.title"),
                'body' => __("people::consent.text.sections.{$key}.body", $p),
            ], array_keys((array) trans('people::consent.text.sections'))),
            'agree' => __('people::consent.text.agree', $p),
            'age' => PeopleSettings::current()->consent_age,
        ];
    }

    /** "You agreed on …", "Ahmed agreed on …", or the office recorded it. */
    public static function who(ConsentState $state, string $viewer, ?string $when): string
    {
        return match (true) {
            $state->guardianId === $viewer => __('people::consent.state.you_agreed', ['when' => $when]),
            $state->via === 'staff' && $state->guardianName === null => __('people::consent.state.office_recorded', ['when' => $when]),
            default => __('people::consent.state.granted_by', ['name' => (string) $state->guardianName, 'when' => $when]),
        };
    }
}
