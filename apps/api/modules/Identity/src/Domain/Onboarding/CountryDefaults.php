<?php

namespace Modules\Identity\Domain\Onboarding;

/**
 * What a new academy most likely uses in its country (the console's new
 * academy fills them in, and each stays editable): the currency it charges
 * in (one the billing engine handles; USD or EUR where the country's own is
 * not one yet), the clock most of the country keeps, the language its
 * screens start in, and the country's calling code for the owner's mobile.
 * Pure: codes only (ISO 3166-1 alpha-2, the same countries as
 * SharedKernel\Geo\Countries).
 */
final class CountryDefaults
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> country => [currency, time zone, language, calling code] */
    public const MAP = [
        'EG' => ['EGP', 'Africa/Cairo', 'ar', '20'],
        'SA' => ['SAR', 'Asia/Riyadh', 'ar', '966'],
        'AE' => ['AED', 'Asia/Dubai', 'ar', '971'],
        'KW' => ['KWD', 'Asia/Kuwait', 'ar', '965'],
        'QA' => ['QAR', 'Asia/Qatar', 'ar', '974'],
        'BH' => ['BHD', 'Asia/Bahrain', 'ar', '973'],
        'OM' => ['OMR', 'Asia/Muscat', 'ar', '968'],
        'JO' => ['JOD', 'Asia/Amman', 'ar', '962'],
        'LB' => ['USD', 'Asia/Beirut', 'ar', '961'],
        'SY' => ['USD', 'Asia/Damascus', 'ar', '963'],
        'IQ' => ['USD', 'Asia/Baghdad', 'ar', '964'],
        'PS' => ['USD', 'Asia/Hebron', 'ar', '970'],
        'YE' => ['USD', 'Asia/Aden', 'ar', '967'],
        'SD' => ['USD', 'Africa/Khartoum', 'ar', '249'],
        'LY' => ['USD', 'Africa/Tripoli', 'ar', '218'],
        'TN' => ['USD', 'Africa/Tunis', 'ar', '216'],
        'DZ' => ['USD', 'Africa/Algiers', 'ar', '213'],
        'MA' => ['USD', 'Africa/Casablanca', 'ar', '212'],
        'MR' => ['USD', 'Africa/Nouakchott', 'ar', '222'],
        'SO' => ['USD', 'Africa/Mogadishu', 'ar', '252'],
        'TR' => ['USD', 'Europe/Istanbul', 'en', '90'],
        'PK' => ['USD', 'Asia/Karachi', 'en', '92'],
        'IN' => ['USD', 'Asia/Kolkata', 'en', '91'],
        'BD' => ['USD', 'Asia/Dhaka', 'en', '880'],
        'ID' => ['USD', 'Asia/Jakarta', 'en', '62'],
        'MY' => ['USD', 'Asia/Kuala_Lumpur', 'en', '60'],
        'SG' => ['USD', 'Asia/Singapore', 'en', '65'],
        'GB' => ['GBP', 'Europe/London', 'en', '44'],
        'IE' => ['EUR', 'Europe/Dublin', 'en', '353'],
        'US' => ['USD', 'America/New_York', 'en', '1'],
        'CA' => ['USD', 'America/Toronto', 'en', '1'],
        'AU' => ['USD', 'Australia/Sydney', 'en', '61'],
        'NZ' => ['USD', 'Pacific/Auckland', 'en', '64'],
        'DE' => ['EUR', 'Europe/Berlin', 'en', '49'],
        'FR' => ['EUR', 'Europe/Paris', 'en', '33'],
        'NL' => ['EUR', 'Europe/Amsterdam', 'en', '31'],
        'BE' => ['EUR', 'Europe/Brussels', 'en', '32'],
        'SE' => ['EUR', 'Europe/Stockholm', 'en', '46'],
        'NO' => ['EUR', 'Europe/Oslo', 'en', '47'],
        'DK' => ['EUR', 'Europe/Copenhagen', 'en', '45'],
        'FI' => ['EUR', 'Europe/Helsinki', 'en', '358'],
        'CH' => ['EUR', 'Europe/Zurich', 'en', '41'],
        'AT' => ['EUR', 'Europe/Vienna', 'en', '43'],
        'IT' => ['EUR', 'Europe/Rome', 'en', '39'],
        'ES' => ['EUR', 'Europe/Madrid', 'en', '34'],
        'PT' => ['EUR', 'Europe/Lisbon', 'en', '351'],
        'GR' => ['EUR', 'Europe/Athens', 'en', '30'],
        'ZA' => ['USD', 'Africa/Johannesburg', 'en', '27'],
        'NG' => ['USD', 'Africa/Lagos', 'en', '234'],
        'KE' => ['USD', 'Africa/Nairobi', 'en', '254'],
    ];

    /** @return array{currency: string, timezone: string, locale: string, dial: string}|null */
    public static function of(?string $country): ?array
    {
        $row = self::MAP[strtoupper((string) $country)] ?? null;

        return $row === null ? null : ['currency' => $row[0], 'timezone' => $row[1], 'locale' => $row[2], 'dial' => $row[3]];
    }

    /** The calling code, digits only ("90"), or null for a country it does not know. */
    public static function dial(?string $country): ?string
    {
        return self::of($country)['dial'] ?? null;
    }
}
