<?php

namespace Modules\Curriculum\Actions\Resources;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Curriculum\Models\Node;
use Modules\Curriculum\Models\NodeResource;
use Throwable;

/**
 * Puts a file or a link on a lesson (docs/architecture/01, section 11): a
 * PDF, a recording, a short video or a picture on the private disk under
 * the academy's folder, or a link to a page or a video elsewhere. At most
 * twelve on a lesson; each one for everyone or for its teachers only.
 */
final class AddNodeResource
{
    public const MAX_PER_LESSON = 12;

    /** What a file may be, by type, and how big (KB). A long video goes on YouTube or Drive, and its link here. */
    public const FILES = [
        'pdf' => ['mimes' => ['application/pdf'], 'max_kb' => 20 * 1024],
        'image' => ['mimes' => ['image/jpeg', 'image/png', 'image/webp'], 'max_kb' => 10 * 1024],
        'audio' => ['mimes' => ['audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/x-m4a', 'audio/webm'], 'max_kb' => 40 * 1024],
        'video' => ['mimes' => ['video/mp4', 'video/webm', 'video/quicktime'], 'max_kb' => 50 * 1024],
    ];

    /** @param  array{kind?: string, title_ar?: ?string, title_en?: ?string, audience?: ?string, url?: ?string}  $data */
    public function handle(Node $node, array $data, ?UploadedFile $file, ?string $actorId): NodeResource
    {
        if ($node->kind !== 'lesson') {
            throw ValidationException::withMessages(['node' => __('curriculum::curriculum.resources.errors.lesson_only')]);
        }
        $kind = ($data['kind'] ?? ($file !== null ? 'file' : 'link')) === 'file' ? 'file' : 'link';
        $fields = ResourceRules::details($data + ['title_fallback' => $file ? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) : null]);
        if ($kind === 'link') {
            $fields['url'] = ResourceRules::url($data['url'] ?? null);
        } else {
            $mime = self::mimeOf($file);
            $type = collect(self::FILES)->search(fn (array $t) => in_array($mime, $t['mimes'], true));
            if ($type === false) {
                throw ValidationException::withMessages(['file' => __('curriculum::curriculum.resources.errors.file_type')]);
            }
            if ($file->getSize() > self::FILES[$type]['max_kb'] * 1024) {
                throw ValidationException::withMessages(['file' => __('curriculum::curriculum.resources.errors.file_size', ['mb' => self::FILES[$type]['max_kb'] / 1024])]);
            }
        }

        $stored = null;
        try {
            return DB::transaction(function () use ($node, $kind, $fields, $file, $actorId, &$stored) {
                DB::select('select pg_advisory_xact_lock(hashtext(?))', ['node_resources:'.$node->id]);
                $count = NodeResource::query()->where('node_id', $node->id)->count();
                if ($count >= self::MAX_PER_LESSON) {
                    throw ValidationException::withMessages(['node' => __('curriculum::curriculum.resources.errors.too_many', ['max' => self::MAX_PER_LESSON])]);
                }
                if ($kind === 'file') {
                    $stored = $file->storeAs("academies/{$node->academy_id}/curriculum/{$node->id}", Str::uuid7().'.'.($file->guessExtension() ?: 'bin'), ['disk' => NodeResource::DISK]);
                    if ($stored === false) {
                        throw ValidationException::withMessages(['file' => __('curriculum::curriculum.resources.errors.stored')]);
                    }
                    $fields += [
                        'file_path' => $stored,
                        'file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                        'mime' => self::mimeOf($file),
                        'size' => $file->getSize(),
                    ];
                }

                return NodeResource::query()->create($fields + [
                    'node_id' => $node->id,
                    'kind' => $kind,
                    'ordinal' => (int) NodeResource::query()->where('node_id', $node->id)->max('ordinal') + 1,
                    'created_by' => $actorId,
                ]);
            });
        } catch (Throwable $e) {
            if (is_string($stored)) {
                Storage::disk(NodeResource::DISK)->delete($stored);
            }
            throw $e;
        }
    }

    /** The file's type from its bytes; a WAV some browsers send as x-wav, an M4A as mp4 audio. */
    private static function mimeOf(?UploadedFile $file): string
    {
        if ($file === null) {
            throw ValidationException::withMessages(['file' => __('curriculum::curriculum.resources.errors.file_missing')]);
        }

        return (string) ($file->getMimeType() ?: $file->getClientMimeType());
    }
}
