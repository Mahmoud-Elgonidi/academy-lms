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
     * @param  list<int>  $preferredDays  ISO weekdays the family is free, on their clock
     * @param  ?int  $preferredFrom  hour of day (0–23), their clock
     * @param  ?int  $preferredTo  hour of day (1–24)
     * @param  ?string  $teacherGender  female | male: the teacher they would rather have
     * @param  ?string  $sessionId  the trial the family booked from it
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
        public array $preferredDays = [],
        public ?int $preferredFrom = null,
        public ?int $preferredTo = null,
        public ?string $teacherGender = null,
        public bool $helpChoose = false,
        public ?string $sessionId = null,
    ) {}
}
