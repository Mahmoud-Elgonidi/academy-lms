<?php

namespace Modules\Privacy\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Privacy\Actions\BuildDataExport;
use Modules\Privacy\Models\DataRequest;

/** Builds a person's copy of their data in the background (the academy travels with the job). */
final class BuildDataExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly string $requestId) {}

    public function handle(BuildDataExport $build): void
    {
        $request = DataRequest::query()->find($this->requestId);
        if ($request !== null) {
            $build->handle($request);
        }
    }
}
