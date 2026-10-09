<?php

namespace Modules\People\Contracts\DTOs;

/** What the academy knows of a student beyond their age: who they are and who should teach them. */
final readonly class StudentDetails
{
    /**
     * @param  ?string  $gender  female | male
     * @param  ?string  $country  ISO 3166-1 alpha-2
     * @param  ?string  $schoolGrade  kg1, kg2, g1…g12, university
     * @param  ?string  $teacherGender  female_only | male_only | prefer_female | prefer_male; null: either
     */
    public function __construct(
        public ?string $gender = null,
        public ?string $country = null,
        public ?string $schoolGrade = null,
        public ?string $teacherGender = null,
    ) {}
}
