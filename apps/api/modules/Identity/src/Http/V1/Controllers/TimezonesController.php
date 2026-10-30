<?php

namespace Modules\Identity\Http\V1\Controllers;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Identity\Http\V1\Resources\TimezoneResource;
use Modules\SharedKernel\Time\Timezones;

/** The zones a person may choose for themselves (PATCH me's timezone), as the panels' zone chip lists them. */
final class TimezonesController
{
    /**
     * Time zones to choose from.
     *
     * Every IANA zone, sorted by region, then by how far it is from UTC, then by city; names in the reader's language.
     */
    public function index(): AnonymousResourceCollection
    {
        return TimezoneResource::collection(Timezones::catalog());
    }
}
