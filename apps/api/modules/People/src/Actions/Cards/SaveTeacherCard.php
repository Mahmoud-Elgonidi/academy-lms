<?php

namespace Modules\People\Actions\Cards;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\People\Models\TeacherProfile;
use Modules\People\Models\TeacherPublicCard;
use Modules\SharedKernel\Audit\AuditTrail;
use Modules\SharedKernel\Files\CleanFile;
use Modules\SharedKernel\Tenancy\TenantContext;
use Throwable;

/**
 * A teacher writes their own card for the academy's public page: the name
 * to show, a few words about themself, a photo if they like, and whether it
 * shows at all (theirs alone to switch: nobody else writes or shows it).
 * Plain words, trimmed. The photo is a PNG, JPEG or WebP by its bytes, up
 * to 1 MB, scanned, on the public disk under the academy's folder (anyone
 * who opens the page sees it); the one it replaces leaves after commit.
 * Audited without the words.
 */
final class SaveTeacherCard
{
    private const EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

    public function __construct(private readonly AuditTrail $audit, private readonly TenantContext $tenants) {}

    /**
     * @param  array<string, mixed>  $data  display_name, bio, shown
     *
     * @throws ValidationException
     */
    public function handle(string $teacherId, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): TeacherPublicCard
    {
        if ((string) auth()->id() !== $teacherId || ! TeacherProfile::query()->whereKey($teacherId)->exists()) {
            throw new AuthorizationException;
        }
        $l = 'people::cards.fields.';
        $input = [
            'display_name' => $this->text($data['display_name'] ?? null, false),
            'bio' => $this->text($data['bio'] ?? null, true),
            'shown' => filter_var($data['shown'] ?? false, FILTER_VALIDATE_BOOL),
            'photo' => $photo,
        ];
        Validator::make($input, [
            'display_name' => ['required', 'string', 'max:60'],
            'bio' => ['nullable', 'string', 'max:280'],
            'photo' => ['nullable', 'file', 'max:'.TeacherPublicCard::MAX_KB, 'mimetypes:'.implode(',', TeacherPublicCard::MIMES), new CleanFile],
        ], [], ['display_name' => __($l.'display_name'), 'bio' => __($l.'bio'), 'photo' => __($l.'photo')])->validate();

        $path = null;
        if ($photo !== null) {
            $extension = self::EXTENSIONS[(string) $photo->getMimeType()] ?? 'jpg';
            $path = $photo->storeAs(TeacherPublicCard::directory($this->tenants->require()->id), Str::uuid7().'.'.$extension, ['disk' => TeacherPublicCard::DISK]);
            if ($path === false) {
                throw ValidationException::withMessages(['photo' => __('people::cards.errors.photo')]);
            }
        }

        try {
            return DB::transaction(function () use ($teacherId, $input, $path, $removePhoto) {
                $card = TeacherPublicCard::query()->where('teacher_id', $teacherId)->lockForUpdate()->first() ?? new TeacherPublicCard(['teacher_id' => $teacherId]);
                $old = $card->photo_path;
                $card->fill([
                    'display_name' => $input['display_name'],
                    'bio' => $input['bio'],
                    'shown' => $input['shown'],
                    'photo_path' => $path ?? ($removePhoto ? null : $old),
                ])->save();
                if ($old !== null && $old !== $card->photo_path) {
                    DB::afterCommit(fn () => Storage::disk(TeacherPublicCard::DISK)->delete($old));
                }
                $this->audit->record('people.card_saved', 'user', $teacherId, [
                    'teacher' => (string) DB::table('users')->where('id', $teacherId)->value('name'), 'card' => $card->shown ? 'shown' : 'hidden',
                ]);

                return $card;
            });
        } catch (Throwable $e) {
            if (is_string($path)) {
                Storage::disk(TeacherPublicCard::DISK)->delete($path);
            }

            throw $e;
        }
    }

    /** Plain words, trimmed: a name on one line, a bio keeps its line breaks (two at most in a row). */
    private function text(mixed $value, bool $lines): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $text = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $text = (string) preg_replace('/[^\P{C}\n]/u', '', $lines ? $text : str_replace("\n", ' ', $text));
        $text = trim((string) preg_replace($lines ? "/\n{3,}/" : '/\s{2,}/u', $lines ? "\n\n" : ' ', $text));

        return $text === '' ? null : $text;
    }
}
