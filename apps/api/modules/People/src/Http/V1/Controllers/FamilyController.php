<?php

namespace Modules\People\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
}
