<?php

namespace Modules\Privacy\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\People\Contracts\PeopleApi;
use Modules\Privacy\Actions\CorrectDetails;
use Modules\Privacy\PrivacyRules;
use Modules\Privacy\Queries\EditableDetails;

/**
 * Putting details right on the spot, from the apps and the portal (the
 * same as "Correct" on My data): a person's own name, and a student's gender,
 * country, school grade and who should teach them, by the student (an adult)
 * or a guardian for a learner they follow. Anything else is a correction
 * request (POST me/data-requests, kind correction).
 */
final class DetailsController
{
    /**
     * Correct details.
     *
     * Send only what changes; fields this person may not correct for the subject (see `details` on GET
     * me/data-requests) are ignored. A choice's empty value clears it (teacher_gender: no preference). Answers what
     * changed and the details as they are now.
     */
    public function update(Request $request, string $subject, CorrectDetails $correct, EditableDetails $editable, PeopleApi $people, PrivacyRules $rules): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            /** One of `choices.gender` on GET me/data-requests. */
            'gender' => ['sometimes', 'nullable', 'string', 'max:40'],
            /** One of `choices.country`: an ISO country code ("SA"). */
            'country' => ['sometimes', 'nullable', 'string', 'max:40'],
            /** One of `choices.school_grade`. */
            'school_grade' => ['sometimes', 'nullable', 'string', 'max:40'],
            /** One of `choices.teacher_gender`; empty: no preference. */
            'teacher_gender' => ['sometimes', 'nullable', 'string', 'max:40'],
        ]);
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $subject) === 1, 404);
        $choices = $people->studentDetailChoices();
        foreach (EditableDetails::STUDENT_FIELDS as $field) {
            $value = $request->input($field);
            if ($value !== null && $value !== '' && ! array_key_exists((string) $value, $choices[$field] ?? [])) {
                throw ValidationException::withMessages([$field => __('validation.in', ['attribute' => __("privacy::screens.mine.fix.fields.{$field}")])]);
            }
        }
        $me = $request->user();
        $side = $rules->ownSide();

        $changed = $correct->handle($subject, (string) $me?->getAuthIdentifier(), $side, $request->only(['name', ...EditableDetails::STUDENT_FIELDS]));

        $now = null;
        foreach ($editable->forViewer((string) $me?->getAuthIdentifier(), (string) $me?->name, $side) as $d) {
            if ($d['id'] === $subject) {
                $now = DataRequestsController::details($d, $choices);
            }
        }

        return response()->json(['data' => [
            /** The fields that changed (empty: nothing did). */
            'changed' => array_values(array_map('strval', $changed)),
            /** The details as they are now, like `details` on GET me/data-requests. */
            'details' => $now ?? [],
        ]]);
    }
}
