<?php

namespace Modules\Quran\Certificates;

use Illuminate\Support\Facades\DB;
use Modules\Platform\Contracts\BrandingApi;
use Modules\Quran\Exams\Stops;
use Modules\Quran\Models\QuranCertificate;
use Modules\SharedKernel\Time\LocalTimePresenter;

/**
 * A certificate as people read it (docs/architecture/02, section 26): its
 * title (the academy's at issue, else ours), the portion it is for, the
 * riwaya, grade, examiner and day; and what the public verification answers:
 * the certificate, the academy's name and the student's first name with
 * their last initial, whether it was revoked (never why), nothing else.
 */
final class Certificates
{
    public function __construct(
        private readonly Stops $stops,
        private readonly LocalTimePresenter $time,
        private readonly BrandingApi $branding,
    ) {}

    public function title(QuranCertificate $certificate): string
    {
        return $certificate->title ?? __('quran::certificates.title');
    }

    public function portion(QuranCertificate $certificate, ?string $locale = null): string
    {
        $portion = $certificate->portion;

        return $this->stops->label((string) $portion['kind'], isset($portion['number']) ? (int) $portion['number'] : null, (string) $portion['program'], $locale);
    }

    /** @return array<string, mixed> */
    public function public(QuranCertificate $certificate): array
    {
        return [
            'number' => $certificate->number,
            'title' => $this->title($certificate),
            /** juz, surah, program or khatma, and the part's or surah's number */
            'portion' => ['kind' => $certificate->portion['kind'], 'number' => $certificate->portion['number'] ?? null, 'label' => $this->portion($certificate)],
            'riwaya' => __('quran::settings.riwayat.'.$certificate->riwaya),
            /** excellent, very_good, good or acceptable */
            'grade' => $certificate->grade,
            'grade_label' => __('quran::programs.grades.'.$certificate->grade),
            'issued_at' => $this->time->present($certificate->issued_at),
            'academy' => $this->branding->current()->name(),
            /** The student's first name and the initial of their last. */
            'student' => self::shortName($this->name($certificate->student_id)),
            'revoked' => $certificate->isRevoked(),
            'revoked_at' => $certificate->revoked_at === null ? null : $this->time->present($certificate->revoked_at),
        ];
    }

    public function name(?string $userId): string
    {
        return $userId === null ? '' : (string) DB::table('users')->where('id', $userId)->value('name');
    }

    /** "Ahmad Ali Hassan" → "Ahmad H."; an Arabic last name's article is passed over ("الهاشمي" → "ه."). */
    public static function shortName(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($words) < 2) {
            return $words[0] ?? '';
        }
        $last = (string) end($words);
        if (mb_strlen($last) > 2 && mb_substr($last, 0, 2) === 'ال') {
            $last = mb_substr($last, 2);
        }

        return $words[0].' '.mb_substr($last, 0, 1).'.';
    }
}
