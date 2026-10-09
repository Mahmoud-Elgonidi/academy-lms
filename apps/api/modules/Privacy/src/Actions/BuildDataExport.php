<?php

namespace Modules\Privacy\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Privacy\Contracts\Events\DataExportReady;
use Modules\Privacy\Models\DataRequest;
use Modules\Privacy\Subjects;
use Modules\SharedKernel\Privacy\PersonalData;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;
use ZipArchive;

/**
 * Builds a person's copy: one ZIP on the private disk with
 * personal-data.json (every module's part, plain values), a README in
 * Arabic and English saying what each part is and what the academy keeps,
 * and the files they gave (homework, receipts, attachments). There a week
 * (privacy:sweep removes it); whoever asked hears it is ready.
 */
final class BuildDataExport
{
    public function __construct(
        private readonly PersonalData $data,
        private readonly BrandingApi $branding,
        private readonly TenantContext $tenants,
    ) {}

    public function handle(DataRequest $request): DataRequest
    {
        if ($request->kind !== DataRequest::EXPORT || $request->status !== 'pending') {
            return $request;
        }
        $tmp = tempnam(sys_get_temp_dir(), 'lms-copy-');
        try {
            $parts = [];
            $files = [];
            foreach ($this->data->sources() as $source) {
                $part = $source->export($request->subject_id);
                if (! $part->isEmpty()) {
                    $parts[$source->key()] = $part->data;
                    $files += $part->files;
                }
            }

            $zip = new ZipArchive;
            if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Cannot write the archive.');
            }
            $zip->addFromString('personal-data.json', (string) json_encode([
                'academy' => $this->branding->current()->name(),
                'person' => Subjects::name($request->subject_id),
                'generated_at' => now()->toIso8601String(),
                'parts' => $parts,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE));
            $zip->addFromString('README.txt', $this->readme(array_keys($parts)));
            $disk = Storage::disk(DataRequest::DISK);
            foreach ($files as $path => $name) {
                if ($disk->exists($path)) {
                    $zip->addFile($disk->path($path), 'files/'.$name);
                }
            }
            $zip->close();

            $stored = 'academies/'.$this->tenants->require()->id.'/privacy/'.$request->id.'.zip';
            $stream = fopen($tmp, 'rb');
            $disk->put($stored, $stream);
            is_resource($stream) && fclose($stream);

            DB::transaction(function () use ($request, $stored, $tmp, $parts, $files) {
                $request->forceFill([
                    'status' => 'ready', 'file_path' => $stored, 'file_size' => (int) filesize($tmp),
                    'expires_at' => now()->addDays(DataRequest::KEEP_DAYS),
                    'summary' => ['parts' => array_keys($parts), 'files' => count($files)],
                ])->save();
                DB::afterCommit(fn () => event(new DataExportReady($request->id)));
            });
        } catch (Throwable $e) {
            Log::error('privacy: a copy of personal data could not be built: '.$e->getMessage(), ['request' => $request->id]);
            $request->forceFill(['status' => 'failed'])->save();
            report($e);
        } finally {
            @unlink($tmp);
        }

        return $request->refresh();
    }

    /** @param  list<string>  $parts */
    private function readme(array $parts): string
    {
        $text = '';
        foreach (['ar', 'en'] as $locale) {
            $text .= trans('privacy::export.readme.title', [], $locale)."\n\n".trans('privacy::export.readme.intro', [], $locale)."\n\n";
            foreach ($parts as $part) {
                $text .= '- '.$part.': '.trans("privacy::export.parts.{$part}", [], $locale)."\n";
            }
            $text .= "\n".trans('privacy::export.readme.kept', [], $locale)."\n\n----\n\n";
        }

        return $text;
    }
}
