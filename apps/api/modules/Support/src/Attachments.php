<?php

namespace Modules\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Support\Models\TicketMessage;

/**
 * One picture or PDF on a message (a screenshot of the problem, a receipt),
 * on the private disk under the academy's folder; served only to the
 * requester and the academy's support.
 */
final class Attachments
{
    /** @return array<string, mixed> the rule for the file field */
    public static function rule(): array
    {
        return ['nullable', 'file', 'max:'.TicketMessage::MAX_KB, 'mimetypes:'.implode(',', TicketMessage::MIMES)];
    }

    public static function validate(?UploadedFile $file): void
    {
        Validator::make(['file' => $file], ['file' => self::rule()], [], ['file' => __('support::tickets.attributes.file')])->validate();
    }

    /** @return array{file_path: string, file_name: string, mime: string, size: int}|array{} */
    public static function store(?UploadedFile $file, string $academyId, string $ticketId): array
    {
        if ($file === null) {
            return [];
        }
        $stored = $file->storeAs("academies/{$academyId}/tickets/{$ticketId}", Str::uuid7().'.'.($file->guessExtension() ?: 'bin'), ['disk' => TicketMessage::DISK]);
        if ($stored === false) {
            throw ValidationException::withMessages(['file' => __('support::tickets.errors.file')]);
        }

        return [
            'file_path' => $stored,
            'file_name' => Str::limit($file->getClientOriginalName() ?: basename($stored), 190, ''),
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
        ];
    }

    /** @param  array<string, mixed>  $stored */
    public static function forget(array $stored): void
    {
        if (isset($stored['file_path'])) {
            Storage::disk(TicketMessage::DISK)->delete($stored['file_path']);
        }
    }
}
