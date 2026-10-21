<?php

namespace Modules\People\Http\V1\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Modules\People\Actions\Cards\SaveTeacherCard;
use Modules\People\Models\TeacherProfile;
use Modules\People\Models\TeacherPublicCard;
use Modules\People\Queries\PublicTeachers;

/**
 * A teacher's own card on the academy's public page, in the apps (the same
 * as "My card" in /teach): what it says, whether it shows, and saving it.
 * Whether the academy took it off is said, never why. Lists are shaped with
 * plain loops: Scramble's inference of collection closures breaks other
 * endpoints.
 */
final class TeacherCardController
{
    /**
     * My card on the academy's page.
     *
     * The name to show, a few words and the photo, whether the teacher switched it on (`shown`), and where it
     * stands (`state`: on, off, hidden by the academy, none yet); the subjects beside it come from the teacher's
     * profile at the academy.
     */
    public function show(Request $request, PublicTeachers $teachers): JsonResponse
    {
        $me = $this->teacher($request);

        return response()->json(['data' => $this->card($me, TeacherPublicCard::query()->where('teacher_id', $me)->first(), $teachers, (string) $request->user()?->getAttribute('name'))]);
    }

    /**
     * Save my card.
     *
     * Multipart: `display_name` (60), `bio` (280, optional), `shown` (true or false), and optionally `photo` (PNG,
     * JPEG or WebP, 1 MB; anyone who opens the academy's page sees it) or `remove_photo` (true). Answers with the
     * card as kept.
     */
    public function update(Request $request, SaveTeacherCard $save, PublicTeachers $teachers): JsonResponse
    {
        $me = $this->teacher($request);
        $photo = $request->file('photo');
        $card = $save->handle(
            $me,
            $request->only(['display_name', 'bio', 'shown']),
            $photo instanceof UploadedFile ? $photo : null,
            $request->boolean('remove_photo'),
        );

        return response()->json(['data' => $this->card($me, $card, $teachers, '')]);
    }

    /** @return array<string, mixed> */
    private function card(string $teacherId, ?TeacherPublicCard $card, PublicTeachers $teachers, string $name): array
    {
        $subjects = [];
        foreach ($teachers->subjectsOf($teacherId) as $subject) {
            $subjects[] = [
                'name' => $subject->name,
                /** The first level they teach, or null (from the start). */
                'level_from' => $subject->levelFrom,
                /** The last level they teach, or null (to the end). */
                'level_to' => $subject->levelTo,
            ];
        }
        $state = match (true) {
            $card === null => 'none',
            $card->hidden_by_academy => 'hidden',
            $card->shown => 'on',
            default => 'off',
        };

        return [
            /** The name the page shows (the account's name until a card is saved). */
            'display_name' => $card?->display_name ?? $name,
            'bio' => $card?->bio,
            /** The photo's address, or null. */
            'photo_url' => $card?->photoUrl(),
            /** The teacher's own switch. */
            'shown' => $card?->shown ?? false,
            /** on | off | hidden (the academy took it off) | none (no card yet) */
            'state' => $state,
            /** What the state means, in words. */
            'state_label' => (string) __('people::cards.state.'.$state),
            'subjects' => $subjects,
        ];
    }

    private function teacher(Request $request): string
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        abort_unless(TeacherProfile::query()->whereKey($me)->exists(), 403);

        return $me;
    }
}
