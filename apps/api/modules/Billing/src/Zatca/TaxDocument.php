<?php

namespace Modules\Billing\Zatca;

use Carbon\CarbonImmutable;
use Modules\Billing\Models\BillingSettings;
use Modules\SharedKernel\Money\Money;
use Modules\SharedKernel\Tenancy\TenantContext;

/**
 * What an invoice or credit note says about its seller when it is issued:
 * the registered name and VAT number, and, for a Saudi academy on ZATCA,
 * that it is a simplified tax invoice with its QR code. The QR is drawn for
 * documents in riyals; another currency would need the VAT in riyals as
 * well, which phase 1 here does not do yet.
 */
final class TaxDocument
{
    public const SIMPLIFIED = 'simplified';

    public function __construct(private readonly TenantContext $tenants) {}

    /** @return array{seller_name: string, seller_tax_number: ?string, tax_document: ?string} */
    public function stamp(?BillingSettings $settings = null): array
    {
        $settings ??= BillingSettings::current();
        $zatca = $settings->zatca && ZatcaQr::validVatNumber($settings->tax_number);

        return [
            'seller_name' => $settings->seller_name ?: $this->tenants->require()->nameAr,
            'seller_tax_number' => $settings->tax_number ?: null,
            'tax_document' => $zatca ? self::SIMPLIFIED : null,
        ];
    }

    /** The QR code's text for a document stamped as a simplified tax invoice, in riyals; null otherwise. */
    public static function qr(?string $taxDocument, ?string $sellerName, ?string $vatNumber, CarbonImmutable $issuedAt, Money $total, Money $tax): ?string
    {
        if ($taxDocument !== self::SIMPLIFIED || $total->currency !== 'SAR' || $sellerName === null || ! ZatcaQr::validVatNumber($vatNumber)) {
            return null;
        }

        return ZatcaQr::encode(mb_strcut($sellerName, 0, 255, 'UTF-8'), (string) $vatNumber, $issuedAt, $total, $tax);
    }
}
