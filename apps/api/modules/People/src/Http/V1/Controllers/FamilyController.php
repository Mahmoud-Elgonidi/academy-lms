<?php

namespace Modules\People\Http\V1\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\People\Actions\Family\AddOwnChild;
use Modules\People\Consent\ConsentRules;
use Modules\People\Contracts\DTOs\Learner;
use Modules\People\Contracts\FamilyView;
use Modules\People\Contracts\PeopleApi;

final class FamilyController
{
    /**
     * My learners.
     *
     * The student themselves first, then their children; pass one's id as learner_id to the family endpoints.
     * Each learner's stage is the look of their own area (kids, middle, teen, classic); viewer_stage is the
     * look of the person signed in (classic for every guardian).
     */
    public function learners(Request $request, FamilyView $family, PeopleApi $people): JsonResponse
    {
        $viewer = (string) $request->user()?->getAuthIdentifier();

        return response()->json([
            'data' => array_map(fn (Learner $l) => [
                'id' => $l->id,
                'name' => $l->name,
                /** The person signed in is this student. */
                'is_self' => $l->isSelf,
                'stage' => $people->studentProfile($l->id)->stage->value,
            ], $family->learners($viewer)),
            'viewer_stage' => $family->stage($viewer)->value,
        ]);
    }

    /**
     * Add a child.
     *
     * A guardian adds their own child (a managed minor under them, with no sign-in of their own) and goes straight into
     * the start flow (GET family/start/options?learner_id={id}). birth_date (Y-m-d), else birth_year; gender optional.
     * agree with the consent wording's version (GET family/consents gives the text; age is when it is needed): the
     * guardian's consent is recorded at once. 403 for an account that is not a guardian; 422 with why, in plain words
     * (the same name and year of birth as one of their children, too many children). Send an Idempotency-Key.
     */
    public function addChild(Request $request, AddOwnChild $add, PeopleApi $people, ConsentRules $rules): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'required_without:birth_year'],
            'birth_year' => ['nullable', 'integer', 'min:1990', 'max:2100', 'required_without:birth_date'],
            /** female | male */
            'gender' => ['nullable', 'string', 'in:female,male'],
            'agree' => ['accepted'],
            /** The consent wording's version the guardian read (GET family/consents). */
            'version' => ['required', 'integer'],
        ], ['agree.accepted' => __('people::consent.link.tick'), 'birth_year.required_without' => __('people::family.add_child.errors.birth')]);
        $id = $add->handle(
            (string) $request->user()->getAuthIdentifier(),
            (string) $data['name'],
            isset($data['birth_date']) ? CarbonImmutable::parse($data['birth_date']) : null,
            isset($data['birth_year']) ? (int) $data['birth_year'] : null,
            $data['gender'] ?? null,
            (int) $data['version'],
            $request->ip(),
            $request->userAgent(),
        );
        $state = $rules->state($id);
        $name = (string) $data['name'];

        return response()->json([
            'data' => [
                'id' => $id,
                'name' => $name,
                /** The look of the child's own area: kids, middle, teen or classic. */
                'stage' => $people->studentProfile($id)->stage->value,
                /** granted (the guardian agreed now) or not_needed (old enough). */
                'consent' => $state->granted ? 'granted' : 'not_needed',
                /** Where to go next: the start flow for this child. */
                'start' => ['learner_id' => $id],
            ],
            'message' => __('people::family.add_child.added', ['name' => $name]),
        ], 201);
    }
}
