<?php

namespace Modules\Billing\Contracts\DTOs;

final readonly class NewSubscription
{
    public function __construct(
        public string $enrollmentId,
        public string $studentId,
        public string $creditAccountId,
        public string $planId,
        public string $payerId,
        public string $priceRegionId,
        public string $subjectNameAr,
        public string $subjectNameEn,
        public ?string $actorId = null,
        public ?string $couponCode = null,     // a coupon to put on it before its first invoice
    ) {}
}
