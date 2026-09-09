<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockReceivingInvoice;
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

        $html = view('inventory.stock-receiving-invoices.print', compact('stockReceivingInvoice'))->render();

        $mpdf = new Mpdf([
            'mode'           => 'utf-8',
            'format'         => 'A4',
            'margin_top'     => 15,
            'margin_bottom'  => 15,
            'margin_left'    => 15,
            'margin_right'   => 15,
            'directionality' => 'rtl',
        ]);

        $mpdf->SetTitle('فاتورة استلام ' . $stockReceivingInvoice->invoice_number);
        $mpdf->WriteHTML($html);

        $filename = $stockReceivingInvoice->invoice_number . '.pdf';
        return response($mpdf->Output($filename, 'S'))
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
