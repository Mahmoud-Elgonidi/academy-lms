<?php

namespace Modules\Crm\Support;

/** The countries a lead is usually from, each with the clock most of it keeps (names in lang/{ar,en}/countries.php). */
final class Countries
{
    public const ZONES = [
        'SA' => 'Asia/Riyadh', 'EG' => 'Africa/Cairo', 'AE' => 'Asia/Dubai', 'KW' => 'Asia/Kuwait', 'QA' => 'Asia/Qatar',
        'BH' => 'Asia/Bahrain', 'OM' => 'Asia/Muscat', 'JO' => 'Asia/Amman', 'IQ' => 'Asia/Baghdad', 'LB' => 'Asia/Beirut',
        'PS' => 'Asia/Hebron', 'SY' => 'Asia/Damascus', 'YE' => 'Asia/Aden', 'SD' => 'Africa/Khartoum', 'LY' => 'Africa/Tripoli',
        'TN' => 'Africa/Tunis', 'DZ' => 'Africa/Algiers', 'MA' => 'Africa/Casablanca', 'TR' => 'Europe/Istanbul',
        'GB' => 'Europe/London', 'IE' => 'Europe/Dublin', 'DE' => 'Europe/Berlin', 'FR' => 'Europe/Paris', 'NL' => 'Europe/Amsterdam',
        'BE' => 'Europe/Brussels', 'SE' => 'Europe/Stockholm', 'IT' => 'Europe/Rome', 'ES' => 'Europe/Madrid',
        'US' => 'America/New_York', 'CA' => 'America/Toronto', 'AU' => 'Australia/Sydney', 'NZ' => 'Pacific/Auckland',
        'MY' => 'Asia/Kuala_Lumpur', 'ID' => 'Asia/Jakarta', 'PK' => 'Asia/Karachi', 'IN' => 'Asia/Kolkata', 'NG' => 'Africa/Lagos',
        'ZA' => 'Africa/Johannesburg',
    ];

    /** @return array<string, string> code => name, in this locale's alphabetical order */
    public static function options(): array
    {
        return collect(array_keys(self::ZONES))->mapWithKeys(fn (string $code) => [$code => __('crm::countries.'.$code)])->sort()->all();
    }
}
