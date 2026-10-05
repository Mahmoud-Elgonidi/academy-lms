<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\Enums\AcademyStatus;
use Modules\Platform\Models\Academy;

/** @extends Factory<Academy> */
final class AcademyFactory extends Factory
{
    protected $model = Academy::class;

    public function definition(): array
    {
        $slug = 'academy-'.fake()->unique()->numerify('#####');

        return [
            'slug' => $slug,
            'name_ar' => 'أكاديمية '.$slug,
            'name_en' => 'Academy '.$slug,
            'status' => AcademyStatus::Active,
            'default_timezone' => 'Africa/Cairo',
            'default_locale' => 'ar',
            'base_currency' => 'SAR',
            'country' => 'SA',
        ];
    }

    public function suspended(): self
    {
        return $this->state(['status' => AcademyStatus::Suspended]);
    }
}
