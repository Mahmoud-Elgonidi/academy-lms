<?php

namespace Modules\SharedKernel\Privacy;

use Illuminate\Contracts\Container\Container;

/**
 * The modules' personal data sources, registered in their providers
 * (`app(PersonalData::class)->add(MySource::class)`). The account itself
 * goes last when erasing, so every other part still finds the person.
 */
final class PersonalData
{
    public const ACCOUNT = 'account';

    /** @var list<class-string<PersonalDataSource>> */
    private array $sources = [];

    public function __construct(private readonly Container $container) {}

    /** @param  class-string<PersonalDataSource>  $source */
    public function add(string $source): void
    {
        if (! in_array($source, $this->sources, true)) {
            $this->sources[] = $source;
        }
    }

    /** @return list<PersonalDataSource> the account's own part first in a copy, last when erasing ($forErasure) */
    public function sources(bool $forErasure = false): array
    {
        $all = array_map(fn (string $class) => $this->container->make($class), $this->sources);
        usort($all, fn (PersonalDataSource $a, PersonalDataSource $b) => ($forErasure ? 1 : -1) * (($a->key() === self::ACCOUNT) <=> ($b->key() === self::ACCOUNT)));

        return $all;
    }
}
