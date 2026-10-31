<?php

namespace Modules\Quran\Actions\Hifz;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Quran\Hifz\HifzPages;
use Modules\Quran\Jobs\CheckHifzRecitationJob;
use Modules\Quran\Models\HifzRecitation;
use Modules\Quran\Support\Recordings;
use Modules\SharedKernel\Ai\AcademyAi;
use Modules\SharedKernel\Ai\AiFeature;
use Modules\SharedKernel\Files\CleanFile;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * A page recited for the daily review (the learner, or a guardian on their
 * screen), while the academy has the recitation check on: the recording
 * waits on the private disk to be heard (CheckHifzRecitation), which then
 * deletes it; its grade is a review of the page.
 */
final class ReciteHifzPage
{
    public function __construct(
        private readonly AcademyAi $ai,
        private readonly HifzPages $pages,
        private readonly TenantContext $tenants,
    ) {}

    public function handle(string $studentId, int $page, ?UploadedFile $file, string $actorId): HifzRecitation
    {
        $this->ai->require(AiFeature::RecitationCheck);
        Validator::make(['page' => $page, 'audio' => $file], [
            'page' => ['required', 'integer', 'min:1', 'max:'.max(1, $this->pages->count())],
            'audio' => ['required', 'file', 'max:'.Recordings::MAX_KB, 'mimetypes:'.implode(',', Recordings::AUDIO), new CleanFile],
        ], [], trans('quran::hifz.attributes'))->validate();

        return DB::transaction(function () use ($studentId, $page, $file, $actorId): HifzRecitation {
            $recitation = HifzRecitation::query()->create([
                'student_id' => $studentId, 'page' => $page, 'recited_by' => $actorId, 'mime' => (string) $file->getMimeType(),
            ]);
            $path = $file->storeAs('academies/'.$this->tenants->require()->id.'/hifz', $recitation->id.'.'.($file->extension() ?: 'bin'), Recordings::DISK);
            $recitation->update(['file_path' => $path]);
            DB::afterCommit(fn () => CheckHifzRecitationJob::dispatch($recitation->id));

            return $recitation;
        });
    }
}
