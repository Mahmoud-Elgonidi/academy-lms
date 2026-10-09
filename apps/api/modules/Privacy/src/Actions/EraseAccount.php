<?php

namespace Modules\Privacy\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Privacy\Contracts\Events\AccountErased;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\Queries\ErasurePreview;
use Modules\SharedKernel\Audit\AuditActor;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Privacy\PersonalData;

/**
 * Erases an account whose erasure the academy approved, once its day has
 * passed (privacy:sweep): every module removes or blanks its part in one
 * transaction, the account itself last (anonymised: no name, no way in),
 * and the person's copies go too. What the law or the academy's records
 * need stays, without their name. If something came up meanwhile (a new
 * lesson, an invoice), the request goes back to the academy instead.
 */
final class EraseAccount
{
    public function __construct(
        private readonly PersonalData $data,
        private readonly ErasurePreview $preview,
        private readonly AuditTrail $audit,
    ) {}

    public function handle(DataRequest $request): DataRequest
    {
        return DB::transaction(function () use ($request) {
            $request = DataRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ($request->kind !== DataRequest::ERASURE || $request->status !== 'approved') {
                return $request;
            }
            $blockers = $this->preview->blockers($request->subject_id);
            if ($blockers !== []) {
                // Not now after all: back to the academy, which hears of it again.
                $request->forceFill(['status' => 'pending', 'erase_after' => null, 'decided_by' => null, 'decided_at' => null,
                    'summary' => ['held_back' => $blockers]])->save();
                $this->audit->record('privacy.erasure_held_back', 'user', $request->subject_id, ['request' => $request->id], new AuditActor('system', null, null));

                return $request;
            }

            $removed = [];
            foreach ($this->data->sources(forErasure: true) as $source) {
                $part = array_filter($source->erase($request->subject_id));
                if ($part !== []) {
                    $removed[$source->key()] = $part;
                }
            }
            // The person's copies of their data go with them.
            $copies = DataRequest::query()->where('subject_id', $request->subject_id)->where('kind', DataRequest::EXPORT)->whereNotNull('file_path')->get();
            foreach ($copies as $copy) {
                $path = $copy->file_path;
                $copy->forceFill(['status' => $copy->status === 'pending' ? 'failed' : 'expired', 'file_path' => null])->save();
                DB::afterCommit(fn () => Storage::disk(DataRequest::DISK)->delete($path));
            }

            $request->forceFill(['status' => 'done', 'done_at' => now(), 'summary' => ['removed' => $removed]])->save();
            // Counts only: no name or address of the person goes into the log.
            $this->audit->record('privacy.account_erased', 'user', $request->subject_id, ['request' => $request->id, 'removed' => $removed], new AuditActor('system', null, null));
            DB::afterCommit(fn () => event(new AccountErased($request->id)));

            return $request;
        });
    }
}
