<?php

namespace Modules\Identity\Http\V1\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\SharedKernel\Time\ZoneOption;

/** @property ZoneOption $resource */
final class TimezoneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            /** The IANA name, what PATCH me takes as timezone: "Africa/Cairo". */
            'id' => $this->resource->id,
            /** Its city in the reader's language: "القاهرة". */
            'city' => $this->resource->city,
            /** How far it is from UTC today: "UTC+2", "UTC+5:30", "UTC−3". */
            'offset' => $this->resource->offset,
            /** The two as one, to show as is: "القاهرة (UTC+2)". */
            'label' => $this->resource->label,
            /** The region it is listed under, in the reader's language: "أفريقيا". */
            'region' => $this->resource->region,
        ];
    }
}
