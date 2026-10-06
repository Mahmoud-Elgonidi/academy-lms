<?php

namespace Modules\Crm\Enums;

/** Where a lead came from. */
enum LeadSource: string
{
    case WhatsApp = 'whatsapp';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Google = 'google';
    case TikTok = 'tiktok';
    case Website = 'website';
    case Referral = 'referral';
    case WalkIn = 'walk_in';
    case Other = 'other';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => __('crm::leads.sources.'.$s->value)])->all();
    }
}
