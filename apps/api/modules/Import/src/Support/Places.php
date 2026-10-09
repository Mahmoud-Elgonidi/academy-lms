<?php

namespace Modules\Import\Support;

use Modules\SharedKernel\Contact\Phone;
use Modules\SharedKernel\Time\Timezones;

/**
 * Time zones as academies write them in a sheet: an IANA name, or a country
 * or city ("مصر", "الرياض", "UK", "Toronto"). Null when it is neither.
 */
final class Places
{
    private const ZONES = [
        'Africa/Cairo' => ['مصر', 'القاهره', 'الاسكندريه', 'egypt', 'cairo', 'alexandria', 'eg'],
        'Asia/Riyadh' => ['السعوديه', 'المملكه', 'الرياض', 'جده', 'مكه', 'المدينه', 'الدمام', 'saudi', 'saudiarabia', 'ksa', 'riyadh', 'jeddah', 'makkah', 'mecca', 'sa'],
        'Asia/Dubai' => ['الامارات', 'دبي', 'ابوظبي', 'الشارقه', 'uae', 'emirates', 'dubai', 'abudhabi', 'sharjah', 'ae'],
        'Asia/Kuwait' => ['الكويت', 'kuwait', 'kw'],
        'Asia/Qatar' => ['قطر', 'الدوحه', 'qatar', 'doha', 'qa'],
        'Asia/Bahrain' => ['البحرين', 'المنامه', 'bahrain', 'manama', 'bh'],
        'Asia/Muscat' => ['عمان', 'سلطنهعمان', 'مسقط', 'oman', 'muscat', 'om'],
        'Asia/Amman' => ['الاردن', 'jordan', 'amman', 'jo'],
        'Asia/Baghdad' => ['العراق', 'بغداد', 'iraq', 'baghdad', 'iq'],
        'Asia/Damascus' => ['سوريا', 'دمشق', 'syria', 'damascus'],
        'Asia/Beirut' => ['لبنان', 'بيروت', 'lebanon', 'beirut'],
        'Asia/Hebron' => ['فلسطين', 'palestine'],
        'Asia/Aden' => ['اليمن', 'صنعاء', 'yemen', 'sanaa'],
        'Africa/Khartoum' => ['السودان', 'الخرطوم', 'sudan', 'khartoum'],
        'Africa/Tripoli' => ['ليبيا', 'طرابلس', 'libya', 'tripoli'],
        'Africa/Tunis' => ['تونس', 'tunisia', 'tunis'],
        'Africa/Algiers' => ['الجزائر', 'algeria', 'algiers'],
        'Africa/Casablanca' => ['المغرب', 'الدارالبيضاء', 'الرباط', 'morocco', 'casablanca', 'rabat'],
        'Europe/Istanbul' => ['تركيا', 'اسطنبول', 'turkey', 'turkiye', 'istanbul'],
        'Europe/London' => ['بريطانيا', 'انجلترا', 'لندن', 'المملكهالمتحده', 'uk', 'unitedkingdom', 'england', 'london', 'gb'],
        'Europe/Dublin' => ['ايرلندا', 'ireland', 'dublin'],
        'Europe/Paris' => ['فرنسا', 'باريس', 'france', 'paris'],
        'Europe/Berlin' => ['المانيا', 'برلين', 'germany', 'berlin'],
        'Europe/Amsterdam' => ['هولندا', 'netherlands', 'amsterdam'],
        'Europe/Brussels' => ['بلجيكا', 'belgium', 'brussels'],
        'Europe/Stockholm' => ['السويد', 'sweden', 'stockholm'],
        'Europe/Oslo' => ['النرويج', 'norway', 'oslo'],
        'Europe/Copenhagen' => ['الدنمارك', 'denmark', 'copenhagen'],
        'Europe/Rome' => ['ايطاليا', 'italy', 'rome'],
        'Europe/Madrid' => ['اسبانيا', 'spain', 'madrid'],
        'Asia/Kuala_Lumpur' => ['ماليزيا', 'كوالالمبور', 'malaysia', 'kualalumpur'],
        'Asia/Jakarta' => ['اندونيسيا', 'جاكرتا', 'indonesia', 'jakarta'],
        'Asia/Karachi' => ['باكستان', 'كراتشي', 'pakistan', 'karachi'],
        'Asia/Kolkata' => ['الهند', 'india'],
        'America/Toronto' => ['كندا', 'تورونتو', 'canada', 'toronto'],
        'America/New_York' => ['امريكا', 'نيويورك', 'usa', 'us', 'unitedstates', 'america', 'newyork'],
        'America/Chicago' => ['شيكاغو', 'chicago', 'texas', 'houston', 'تكساس'],
        'America/Los_Angeles' => ['كاليفورنيا', 'لوسانجلوس', 'california', 'losangeles'],
        'Australia/Sydney' => ['استراليا', 'سيدني', 'australia', 'sydney'],
    ];

    public static function timezone(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (Timezones::isValid($text)) {
            return $text;
        }
        $key = Text::key($text);
        foreach (self::ZONES as $zone => $names) {
            if (in_array($key, array_map(fn (string $n) => Text::key($n), $names), true)) {
                return $zone;
            }
        }

        return null;
    }

    /** The calling code most numbers in a sheet for this academy will lack: from its time zone. */
    public static function callingCode(string $timezone): string
    {
        return Phone::callingCodeFor($timezone) ?? '20';
    }
}
