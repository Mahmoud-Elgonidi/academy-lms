<?php

namespace Modules\Billing\Actions;

use Carbon\CarbonImmutable;
use Modules\Billing\Queries\InvoiceCourses;
use Modules\Billing\Queries\RevenueBoard;
use Modules\Billing\Support\RevenuePeriod;
use Modules\SharedKernel\Money\Money;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The revenue screen's period as a spreadsheet for the accountant: the
 * totals and where the money came from, every payment that arrived, and
 * every invoice issued (cancelled ones left out). Amounts are numbers in the
 * currency's units, days and times on the academy's clock, and the sheets
 * read right to left in Arabic.
 */
final class ExportRevenue
{
    public function __construct(private readonly RevenueBoard $board) {}

    /** @return string the path of a temporary .xlsx file */
    public function handle(RevenuePeriod $period, string $currency): string
    {
        $path = tempnam(sys_get_temp_dir(), 'revenue-').'.xlsx';
        $tz = $period->timezone;
        $rtl = str_starts_with(app()->getLocale(), 'ar');
        $bold = (new Style)->setFontBold();
        $head = (new Style)->setFontBold()->setBackgroundColor('EEF2F7');
        $percent = (new Style)->setFormat('0%');
        $l = fn (string $key, array $r = []) => __('billing::revenue.export.'.$key, $r);
        $number = fn (int $minor) => $this->number(Money::of($minor, $currency));

        $writer = new Writer;
        $writer->openToFile($path);

        // 1. The totals, then each way of looking at where the money came from.
        $s = $this->board->summary($period, $currency);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName($l('summary'));
        $sheet->setSheetView((new SheetView)->setRightToLeft($rtl));
        $sheet->setColumnWidth(32, 1);
        $sheet->setColumnWidthForRange(16, 2, 3);
        $writer->addRow(Row::fromValues([$l('title', ['period' => $period->first->toDateString().' – '.$period->last->toDateString(), 'currency' => $currency])], $bold));
        $writer->addRow(Row::fromValues([$l('made', ['date' => CarbonImmutable::now()->setTimezone($tz)->format('Y-m-d H:i'), 'timezone' => $tz])]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([$l('item'), $l('value')], $head));
        foreach ([
            'collected' => $this->number($s['collected']), 'tax' => $this->number($s['tax']), 'net' => $this->number($s['net']),
            'payments_count' => $s['payments'], 'invoiced' => $this->number($s['invoiced']), 'invoices_count' => $s['invoices'],
            'discounts' => $this->number($s['discounts']), 'outstanding' => $this->number($s['outstanding']), 'overdue' => $this->number($s['overdue']),
        ] as $key => $value) {
            $writer->addRow(Row::fromValues([$l($key), $value]));
        }
        foreach ($this->board->breakdown($period, $currency) as $group => $rows) {
            $total = max(1, array_sum(array_column($rows, 'minor')));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([__('billing::revenue.by.'.$group), $l('amount'), $l('share')], $head));
            foreach ($rows as $row) {
                $writer->addRow(Row::fromValuesWithStyles([$row['label'], $number($row['minor']), $row['minor'] / $total], null, [2 => $percent]));
            }
        }

        // 2. Every payment that arrived in the period, oldest first.
        $payments = $this->board->payments($period, $currency)
            ->join('billing_accounts as a', fn ($j) => $j->on('a.academy_id', '=', 'i.academy_id')->on('a.id', '=', 'i.billing_account_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'a.academy_id')->on('u.id', '=', 'a.payer_id'))
            ->leftJoin('payment_checkouts as c', fn ($j) => $j->on('c.academy_id', '=', 'p.academy_id')->on('c.id', '=', 'p.checkout_id'))
            ->leftJoin('gateway_accounts as g', fn ($j) => $j->on('g.academy_id', '=', 'c.academy_id')->on('g.id', '=', 'c.gateway_account_id'))
            ->orderBy('p.received_at')->orderBy('p.created_at')
            ->get(['p.invoice_id', 'p.received_at', 'i.number', 'u.name as payer', 'p.method', 'g.gateway', 'p.reference', 'p.amount_minor', 'i.tax_minor', 'i.total_minor']);
        $courses = InvoiceCourses::of($payments->pluck('invoice_id')->map(fn ($id) => (string) $id)->unique()->values()->all());

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($l('payments'));
        $sheet->setSheetView((new SheetView)->setRightToLeft($rtl));
        $sheet->setColumnWidthForRange(13, 1, 3);
        $sheet->setColumnWidth(24, 4);
        $sheet->setColumnWidth(32, 5);
        $sheet->setColumnWidth(24, 6);
        $sheet->setColumnWidthForRange(16, 7, 10);
        $writer->addRow(Row::fromValues(array_map($l, ['date', 'time', 'number', 'payer', 'students', 'method', 'reference', 'amount', 'vat', 'currency']), $head));
        foreach ($payments as $p) {
            $at = CarbonImmutable::parse($p->received_at)->setTimezone($tz);
            $tax = (int) $p->total_minor > 0 ? (int) round((int) $p->amount_minor * (int) $p->tax_minor / (int) $p->total_minor) : 0;
            $writer->addRow(Row::fromValues([
                $at->format('Y-m-d'), $at->format('H:i'), (string) $p->number, (string) $p->payer,
                InvoiceCourses::label($courses[(string) $p->invoice_id] ?? []),
                $this->board->methodLabel((string) $p->method, $p->gateway), (string) $p->reference,
                $number((int) $p->amount_minor), $number($tax), $currency,
            ]));
        }

        // 3. Every invoice issued in the period and not cancelled.
        $invoices = $this->board->issued($period, $currency)
            ->join('billing_accounts as a', fn ($j) => $j->on('a.academy_id', '=', 'i.academy_id')->on('a.id', '=', 'i.billing_account_id'))
            ->join('users as u', fn ($j) => $j->on('u.academy_id', '=', 'a.academy_id')->on('u.id', '=', 'a.payer_id'))
            ->selectRaw("(select coalesce(-sum(l.amount_minor), 0) from invoice_lines l where l.academy_id = i.academy_id and l.invoice_id = i.id and l.kind = 'discount') as discount_minor")
            ->addSelect(['i.id', 'i.number', 'i.issued_at', 'i.due_at', 'u.name as payer', 'i.status', 'i.tax_minor', 'i.total_minor', 'i.paid_minor'])
            ->orderBy('i.issued_at')->orderBy('i.number')
            ->get();
        $courses = InvoiceCourses::of($invoices->pluck('id')->map(fn ($id) => (string) $id)->all());

        $sheet = $writer->addNewSheetAndMakeItCurrent();
        $sheet->setName($l('invoices'));
        $sheet->setSheetView((new SheetView)->setRightToLeft($rtl));
        $sheet->setColumnWidthForRange(13, 1, 3);
        $sheet->setColumnWidth(24, 4);
        $sheet->setColumnWidth(32, 5);
        $sheet->setColumnWidthForRange(14, 6, 12);
        $writer->addRow(Row::fromValues(array_map($l, ['number', 'issued', 'due', 'payer', 'students', 'status', 'discount', 'vat', 'total', 'paid', 'left', 'currency']), $head));
        foreach ($invoices as $i) {
            $writer->addRow(Row::fromValues([
                (string) $i->number,
                CarbonImmutable::parse($i->issued_at)->setTimezone($tz)->format('Y-m-d'),
                $i->due_at ? CarbonImmutable::parse($i->due_at)->setTimezone($tz)->format('Y-m-d') : '',
                (string) $i->payer,
                InvoiceCourses::label($courses[(string) $i->id] ?? []),
                $l('statuses.'.$i->status),
                $number((int) $i->discount_minor), $number((int) $i->tax_minor), $number((int) $i->total_minor),
                $number((int) $i->paid_minor), $number((int) $i->total_minor - (int) $i->paid_minor), $currency,
            ]));
        }

        $writer->close();

        return $path;
    }

    public function filename(RevenuePeriod $period, string $currency): string
    {
        return 'revenue-'.$period->first->toDateString().'-'.$period->last->toDateString().'-'.$currency.'.xlsx';
    }

    private function number(Money $money): float|int
    {
        $value = $money->decimal();

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }
}
