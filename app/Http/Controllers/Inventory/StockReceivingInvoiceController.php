<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockReceivingInvoice;
use App\Services\PrintThemeService;
use Mpdf\Mpdf;

class StockReceivingInvoiceController extends Controller
{
    public function show(StockReceivingInvoice $stockReceivingInvoice)
    {
        $stockReceivingInvoice->load([
            'stockTransfer.items.product',
            'stockTransfer.discrepancies.product',
            'receivedBy',
            'receivingLocation',
            'sendingLocation',
        ]);
        return view('inventory.stock-receiving-invoices.show', compact('stockReceivingInvoice'));
    }

    public function print(StockReceivingInvoice $stockReceivingInvoice)
    {
        $stockReceivingInvoice->load([
            'stockTransfer.items.product',
            'stockTransfer.discrepancies',
            'receivedBy',
            'receivingLocation',
            'sendingLocation',
        ]);
        return view('inventory.stock-receiving-invoices.print', compact('stockReceivingInvoice'));
    }

    public function pdf(StockReceivingInvoice $stockReceivingInvoice)
    {
        $stockReceivingInvoice->load([
            'stockTransfer.items.product',
            'stockTransfer.discrepancies',
            'receivedBy',
            'receivingLocation',
            'sendingLocation',
        ]);

        $html = view('inventory.stock-receiving-invoices.print', [
            'stockReceivingInvoice' => $stockReceivingInvoice,
            'pdfMode' => true,
        ])->render();

        $printService = app(PrintThemeService::class);
        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => $printService->pdfFormat($printService->settings()),
            'margin_top'     => 0,
            'margin_bottom'  => 0,
            'margin_left'    => 0,
            'margin_right'   => 0,
            'directionality' => 'rtl',
            'default_font'   => 'dejavusans',
        ]);

        $mpdf->SetTitle('فاتورة استلام ' . $stockReceivingInvoice->invoice_number);
        $mpdf->WriteHTML($html);

        $filename = $stockReceivingInvoice->invoice_number . '.pdf';
        return response($mpdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
