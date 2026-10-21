<?php

namespace Modules\Site\Contracts\DTOs;

/** A request from the academy's page, as the CRM makes a lead of it. */
final readonly class JoinRequestData
{
    /**
     * @param  string  $kind  trial | teach
     * @param  string  $phone  E.164
     * @param  ?string  $country  ISO 3166-1 alpha-2, as the visitor chose it
     * @param  ?string  $timezone  IANA, as the visitor's browser said it
     * @param  string  $locale  ar | en: the page's language when they asked
     * @param  list<string>  $subjectIds  for a teacher: the subjects they teach
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $name,
        public string $phone,
        public ?string $email,
        public ?string $country,
        public ?string $timezone,
        public string $locale,
        public ?string $subjectId,
        public bool $forChild,
        public ?string $childName,
        public ?int $childBirthYear,
        public array $subjectIds,
        public ?int $yearsTeaching,
        public ?string $message,
        public ?string $utmSource,
        public ?string $utmMedium,
        public ?string $utmCampaign,
        public string $status,
        public ?string $leadId,
    ) {}
}
