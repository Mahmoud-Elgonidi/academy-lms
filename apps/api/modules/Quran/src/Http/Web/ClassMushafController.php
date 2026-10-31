<?php

namespace Modules\Quran\Http\Web;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Classroom\Contracts\ClassPage;
use Modules\Quran\Mushaf\ClassMushaf;

/**
 * The shared mushaf beside the room on a lesson's class page: its pages and
 * marked words, and the teacher's turns and marks. Whoever looks at the
 * class page (signed in, or by their signed link; Classroom's ClassPage).
 */
final class ClassMushafController
{
    public function __construct(
        private readonly ClassPage $page,
        private readonly ClassMushaf $mushaf,
    ) {}

    /** A page of the lesson's shared mushaf (the one asked for, else the teacher's, else where the lesson starts). */
    public function show(Request $request, string $session): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'between:1,604']]);
        $viewer = $this->page->viewer($request, $session) ?? abort(403);

        return response()->json(['data' => $this->mushaf->page($session, $viewer, $request->integer('page') ?: null) ?? abort(404)]);
    }

    /** The teacher turns the lesson's mushaf, marks a word or takes a mark off (change: turn, mark, unmark). */
    public function change(Request $request, string $session): JsonResponse
    {
        $request->validate(['change' => ['required', 'in:turn,mark,unmark']]);
        $viewer = $this->page->viewer($request, $session) ?? abort(403);
        try {
            $page = $this->mushaf->change($session, $viewer, (string) $request->input('change'), $request->except('change'));
        } catch (AuthorizationException) {
            abort(403);
        }

        return response()->json(['data' => $page ?? abort(404)]);
    }
}
