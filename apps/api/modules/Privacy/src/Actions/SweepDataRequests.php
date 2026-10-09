<?php

namespace Modules\Privacy\Actions;

use Illuminate\Support\Facades\Storage;
use Modules\Privacy\Models\DataRequest;

/**
 * Every quarter of an hour, in each academy (privacy:sweep): approved
 * erasures whose day has passed are done, and copies past their week are
 * removed from the disk.
 */
final class SweepDataRequests
{
    public function __construct(private readonly EraseAccount $erase) {}

    /** @return array{erased: int, expired: int} */
    public function handle(): array
    {
        $erased = 0;
        foreach (DataRequest::query()->where('kind', DataRequest::ERASURE)->where('status', 'approved')->where('erase_after', '<=', now())->get() as $request) {
            $erased += $this->erase->handle($request)->status === 'done' ? 1 : 0;
        }

        $expired = 0;
        foreach (DataRequest::query()->where('kind', DataRequest::EXPORT)->where('status', 'ready')->where('expires_at', '<=', now())->get() as $copy) {
            if ($copy->file_path !== null) {
                Storage::disk(DataRequest::DISK)->delete($copy->file_path);
            }
            $copy->forceFill(['status' => 'expired', 'file_path' => null])->save();
            $expired++;
        }

        return ['erased' => $erased, 'expired' => $expired];
    }
}
