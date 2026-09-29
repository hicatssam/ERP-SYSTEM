<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Location;
use App\Models\StockReceivingInvoice;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mpdf\Mpdf;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnifiedInvoiceDocumentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sales_invoice_has_the_same_branding_and_financial_lines_in_browser_and_pdf(): void
    {
        $this->setPrintBrand();

        $invoice = Invoice::make([
            'invoice_number' => 'INV-TEST-001',
            'invoice_type' => 'regular_order',
            'order_type' => 'order',
            'status' => 'active',
            'subtotal' => 42,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 42,
            'paid_amount' => 20,
            'remaining_amount' => 22,
            'issued_at' => '2026-09-29 11:00:00',
        ]);
        $invoice->setRelation('location', Location::make(['name' => 'الفرع الرئيسي']));
        $invoice->setRelation('customer', null);
        $invoice->setRelation('items', collect([
            InvoiceItem::make([
                'description' => 'منتج تجريبي',
                'quantity' => 2,
                'unit_price' => 21,
                'line_total' => 42,
            ]),
        ]));

        $browser = view('finance.invoices.print', ['invoice' => $invoice])->render();
        $pdf = view('finance.invoices.print', ['invoice' => $invoice, 'pdfMode' => true])->render();

        foreach ([$browser, $pdf] as $document) {
            $this->assertStringContainsString('مخبز الريحان', $document);
            $this->assertStringContainsString('#123456', $document);
            $this->assertStringContainsString('INV-TEST-001', $document);
            $this->assertStringContainsString('منتج تجريبي', $document);
            $this->assertStringContainsString('22.00', $document);
            $this->assertStringContainsString('د.ع', $document);
            $this->assertStringContainsString('class="invoice-party-table"', $document);
        }

        $this->assertStringContainsString('<div class="print-screen-toolbar">', $browser);
        $this->assertStringNotContainsString('<div class="print-screen-toolbar">', $pdf);

        foreach (['customer-menu.invoice', 'documents.invoice-template', 'pdf.invoices.template'] as $legacyView) {
            $this->assertStringContainsString(
                'INV-TEST-001',
                view($legacyView, ['invoice' => $invoice])->render()
            );
        }

        $renderer = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            'default_font' => 'dejavusans',
        ]);
        $renderer->WriteHTML($pdf);
        $this->assertStringStartsWith('%PDF-', $renderer->Output('', 'S'));
    }

    #[Test]
    public function receiving_invoice_uses_the_same_configurable_document_brand_in_both_modes(): void
    {
        $this->setPrintBrand();

        $invoice = StockReceivingInvoice::make([
            'invoice_number' => 'STR-TEST-001',
            'total_items_ordered' => 5,
            'total_items_received' => 4,
            'total_items_damaged' => 1,
            'issued_at' => '2026-09-29 11:00:00',
        ]);
        $transfer = StockTransfer::make(['transfer_number' => 'TR-TEST-001']);
        $transfer->setRelation('items', collect());
        $invoice->setRelation('stockTransfer', $transfer);
        $invoice->setRelation('sendingLocation', Location::make(['name' => 'المصنع']));
        $invoice->setRelation('receivingLocation', Location::make(['name' => 'الفرع الرئيسي']));
        $invoice->setRelation('receivedBy', null);

        foreach ([false, true] as $pdfMode) {
            $document = view('inventory.stock-receiving-invoices.print', [
                'stockReceivingInvoice' => $invoice,
                'pdfMode' => $pdfMode,
            ])->render();

            $this->assertStringContainsString('مخبز الريحان', $document);
            $this->assertStringContainsString('#123456', $document);
            $this->assertStringContainsString('STR-TEST-001', $document);
            $this->assertStringContainsString('TR-TEST-001', $document);
            $this->assertStringContainsString('class="print-table"', $document);
            $this->assertStringNotContainsString('حلويات دهب', $document);
            if ($pdfMode) {
                $this->assertCanGeneratePdf($document);
            }
        }
    }

    #[Test]
    public function supplier_and_customer_statements_use_the_same_brand_and_currency(): void
    {
        $this->setPrintBrand();

        $currency = Currency::query()->where('is_base', true)->firstOrFail();
        $supplierDocument = view('procurement.suppliers.statement-print', [
            'supplier' => Supplier::make(['name' => 'مورد التجربة', 'supplier_code' => 'SUP-001']),
            'selectedCurrency' => $currency,
            'from' => null,
            'to' => null,
            'rows' => [],
            'totalDebit' => 12,
            'totalCredit' => 3,
            'finalBalance' => 9,
        ])->render();

        $customerDocument = view('pdf.customers.statement', [
            'customer' => Customer::make(['name' => 'عميل التجربة', 'customer_type' => 'individual']),
            'selectedLocation' => null,
            'statement' => [
                'opening_balance' => 0,
                'period_debit' => 12,
                'period_credit' => 3,
                'closing_balance' => 9,
                'rows' => [],
            ],
            'summary' => ['overdue' => 0],
        ])->render();

        foreach ([$supplierDocument, $customerDocument] as $document) {
            $this->assertStringContainsString('مخبز الريحان', $document);
            $this->assertStringContainsString('#123456', $document);
            $this->assertStringContainsString('د.ع', $document);
            $this->assertStringNotContainsString('حلويات دهب', $document);
            $this->assertStringContainsString('class="print-table"', $document);
        }

        $this->assertCanGeneratePdf($customerDocument, 'A4-L');
    }

    private function assertCanGeneratePdf(string $html, string $format = 'A4'): void
    {
        $renderer = new Mpdf([
            'mode' => 'utf-8',
            'format' => $format,
            'directionality' => 'rtl',
            'default_font' => 'dejavusans',
        ]);
        $renderer->WriteHTML($html);

        $this->assertStringStartsWith('%PDF-', $renderer->Output('', 'S'));
    }

    private function setPrintBrand(): void
    {
        Currency::query()->create([
            'code' => 'IQD',
            'name' => 'Iraqi Dinar',
            'symbol' => 'د.ع',
            'is_base' => true,
            'is_active' => true,
        ]);
        SystemSetting::set('business_legal_name', 'مخبز الريحان');
        SystemSetting::set('print_primary_color', '#123456');
        SystemSetting::flushCache();
    }
}
