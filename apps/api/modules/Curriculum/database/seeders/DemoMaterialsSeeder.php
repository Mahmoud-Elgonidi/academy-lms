<?php

namespace Modules\Curriculum\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Curriculum\Actions\Resources\AddNodeResource;
use Modules\Curriculum\Models\Node;

/**
 * Lesson materials in the demo academy, through the real action: a
 * follow-up sheet (a PDF drawn here), a recording (a tone made here), a
 * link to the surah, and a guide for teachers only, on the lessons the demo
 * students are at or have just done. Runs after the programs.
 */
final class DemoMaterialsSeeder extends Seeder
{
    /** [subject, lesson (English title), [[kind, title ar, title en, audience, link or sheet heading], …]] */
    private const MATERIALS = [
        ['Quran memorisation', 'Al-Fatiha', [
            ['pdf', 'ورقة متابعة الفاتحة', 'Al-Fatiha follow-up sheet', 'all', 'Juz Amma - Al-Fatiha - follow-up sheet'],
            ['audio', 'تلاوة الفاتحة ببطء', 'Al-Fatiha, read slowly', 'all', null],
            ['link', 'الفاتحة على quran.com', 'Al-Fatiha on quran.com', 'all', 'https://quran.com/1'],
            ['pdf', 'دليل المعلّم: المدود في الفاتحة', 'Teacher\'s guide: the madd in Al-Fatiha', 'teachers', 'Teacher guide - madd in Al-Fatiha'],
        ]],
        ['Quran memorisation', 'An-Nas and Al-Falaq', [
            ['pdf', 'ورقة متابعة الناس والفلق', 'An-Nas and Al-Falaq sheet', 'all', 'Juz Amma - An-Nas and Al-Falaq'],
            ['link', 'سورة الناس على quran.com', 'An-Nas on quran.com', 'all', 'https://quran.com/114'],
        ]],
        ['Quran memorisation', 'Al-Kawthar and Al-Ma\'un', [
            ['pdf', 'ورقة متابعة الكوثر والماعون', 'Al-Kawthar and Al-Ma\'un sheet', 'all', 'Juz Amma - Al-Kawthar and Al-Maun'],
            ['audio', 'تلاوة الكوثر', 'Al-Kawthar, read slowly', 'all', null],
            ['link', 'سورة الكوثر على quran.com', 'Al-Kawthar on quran.com', 'all', 'https://quran.com/108'],
        ]],
        ['Quran memorisation', 'Quraysh and Al-Fil', [
            ['pdf', 'ورقة متابعة قريش والفيل', 'Quraysh and Al-Fil sheet', 'all', 'Juz Amma - Quraysh and Al-Fil'],
            ['link', 'سورة قريش على quran.com', 'Quraysh on quran.com', 'all', 'https://quran.com/106'],
            ['pdf', 'دليل المعلّم: قصة أصحاب الفيل للأطفال', 'Teacher\'s guide: telling Al-Fil to children', 'teachers', 'Teacher guide - Al-Fil for children'],
        ]],
        ['Arabic grammar', 'The past tense', [
            ['pdf', 'جدول تصريف الماضي', 'Past tense table', 'all', 'Easy Arabic Grammar - the past tense table'],
        ]],
        ['Arabic grammar', 'The present tense', [
            ['pdf', 'تمارين الفعل المضارع', 'Present tense exercises', 'all', 'Easy Arabic Grammar - present tense exercises'],
            ['pdf', 'إجابات تمارين المضارع', 'Present tense answers', 'teachers', 'Answers - present tense exercises'],
        ]],
        ['Tajweed', 'Izhar', [
            ['pdf', 'حروف الإظهار الستة', 'The six letters of izhar', 'all', 'Nour Al-Bayan - the letters of izhar'],
            ['audio', 'أمثلة على الإظهار', 'Izhar, examples read aloud', 'all', null],
        ]],
        ['Tajweed', 'Idgham', [
            ['pdf', 'الإدغام بغنة وبغير غنة', 'Idgham with and without ghunna', 'all', 'Nour Al-Bayan - idgham'],
        ]],
    ];

    public function run(AddNodeResource $add): void
    {
        $owner = (string) DB::table('users')->where('email', 'owner@demo.test')->value('id');

        foreach (self::MATERIALS as [$subject, $lessonEn, $items]) {
            $lesson = Node::query()->where('kind', 'lesson')->where('title_en', $lessonEn)
                ->whereIn('version_id', DB::table('curriculum_versions as v')->join('programs as p', 'p.id', '=', 'v.program_id')
                    ->join('subjects as s', 's.id', '=', 'p.subject_id')->where('s.name_en', $subject)->where('v.status', 'published')->select('v.id'))
                ->first();
            if ($lesson === null) {
                continue;
            }
            foreach ($items as [$kind, $ar, $en, $audience, $extra]) {
                $data = ['title_ar' => $ar, 'title_en' => $en, 'audience' => $audience];
                match ($kind) {
                    'link' => $add->handle($lesson, $data + ['kind' => 'link', 'url' => $extra], null, $owner),
                    'pdf' => $add->handle($lesson, $data + ['kind' => 'file'], $this->sheet((string) $extra), $owner),
                    default => $add->handle($lesson, $data + ['kind' => 'file'], $this->recording($en), $owner),
                };
            }
        }
    }

    /** A one-page A4 sheet drawn here: a header band, the heading, and lines to write on. */
    private function sheet(string $heading): UploadedFile
    {
        $text = fn (float $x, float $y, int $size, string $s) => sprintf("BT /F1 %d Tf %.1F %.1F Td (%s) Tj ET\n", $size, $x, $y, str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s));
        $content = "0.11 0.37 0.31 rg 0 772 595 70 re f\n1 1 1 rg\n".$text(40, 800, 20, $heading)."0.2 0.2 0.2 rg\n"
            .$text(40, 735, 12, 'Name: ______________________    Date: ____________')
            .$text(40, 712, 12, 'Read each verse three times with your teacher, then tick it.');
        for ($i = 0; $i < 14; $i++) {
            $y = 670 - $i * 42;
            $content .= sprintf("0.85 0.85 0.85 RG 0.8 w 40 %d m 555 %d l S\n0.11 0.37 0.31 RG 1.2 w 520 %d 18 18 re S\n", $y, $y, $y + 6);
        }
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($content)." >>\nstream\n".$content.'endstream',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";

        return $this->file($pdf, preg_replace('/[^A-Za-z0-9]+/', '-', $heading).'.pdf', 'application/pdf');
    }

    /** Four seconds of soft rising tones made here, so the player has something to play. */
    private function recording(string $name): UploadedFile
    {
        $rate = 8000;
        $samples = '';
        foreach ([392, 440, 494, 523] as $note) {
            for ($i = 0; $i < $rate; $i++) {
                $fade = min(1, $i / 400, ($rate - $i) / 400);
                $samples .= pack('v', (int) (2500 * $fade * sin(2 * M_PI * $note * $i / $rate)) & 0xFFFF);
            }
        }
        $wav = 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, $rate, $rate * 2, 2, 16).'data'.pack('V', strlen($samples)).$samples;

        return $this->file($wav, preg_replace('/[^A-Za-z0-9]+/', '-', $name).'.wav', 'audio/wav');
    }

    private function file(string $bytes, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'demo');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, $name, $mime, null, true);
    }
}
