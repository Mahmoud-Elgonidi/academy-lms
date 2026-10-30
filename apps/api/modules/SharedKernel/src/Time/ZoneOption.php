<?php

namespace Modules\SharedKernel\Time;

/** One zone in a picker (Timezones::catalog), written for the reader. */
final readonly class ZoneOption
{
    public function __construct(
        /** The IANA name: "Africa/Cairo". */
        public string $id,
        /** Its city in the reader's language: "القاهرة". */
        public string $city,
        /** How far it is from UTC now: "UTC+2", "UTC+5:30", "UTC−3". */
        public string $offset,
        /** The two as one: "القاهرة (UTC+2)". */
        public string $label,
        /** The region it is listed under, in the reader's language: "أفريقيا". */
        public string $region,
    ) {}
}
