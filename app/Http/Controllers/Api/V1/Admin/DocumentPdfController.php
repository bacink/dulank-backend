<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Payslip;
use App\Models\Purchase;
use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Dokumen cetak PDF (invoice, quotation, delivery note, payslip, purchase).
 */
class DocumentPdfController extends Controller
{
    public function invoice(string $invoiceNo): Response
    {
        $invoice = Invoice::query()
            ->where('invoice_no', $invoiceNo)
            ->with(['order.items', 'payments'])
            ->firstOrFail();

        $order = $invoice->order;

        return $this->render('Invoice '.$invoice->invoice_no, [
            'Order' => $order?->number ?? '-',
            'Customer' => $order?->contact['name'] ?? '-',
            'Tanggal Invoice' => $invoice->invoice_date?->format('d/m/Y'),
            'Jatuh Tempo' => $invoice->due_date?->format('d/m/Y'),
        ], ['Produk', 'Qty', 'Harga', 'Total'], $order?->items->map(fn ($item) => [
            $item->name,
            rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ','),
            number_format((int) $item->price, 0, ',', '.'),
            number_format((int) $item->total, 0, ',', '.'),
        ])->all() ?? [], [
            'Subtotal' => number_format((int) ($order?->sub_total ?? 0), 0, ',', '.'),
            'PPN' => number_format((int) ($order?->tax ?? 0), 0, ',', '.'),
            'Total' => number_format((int) $invoice->amount, 0, ',', '.'),
            'Dibayar' => number_format((int) $invoice->paid, 0, ',', '.'),
            'Sisa' => number_format((int) $invoice->amount_due, 0, ',', '.'),
        ], 'invoice-'.$invoice->invoice_no);
    }

    public function quotation(string $noQuotation): Response
    {
        $quotation = Quotation::query()->where('no_quotation', $noQuotation)->with('items')->firstOrFail();

        return $this->render('Penawaran '.$quotation->no_quotation, [
            'Customer' => $quotation->customer_name ?? '-',
            'Email' => $quotation->customer_email ?? '-',
            'Tanggal' => $quotation->date?->format('d/m/Y'),
            'Berlaku Hingga' => $quotation->valid_until?->format('d/m/Y'),
            'Status' => $quotation->status,
        ], ['Produk', 'Qty', 'Harga', 'Total'], $quotation->items->map(fn ($item) => [
            $item->product_name,
            rtrim(rtrim(number_format((float) $item->order_qty, 2, ',', '.'), '0'), ','),
            number_format((int) $item->unit_price, 0, ',', '.'),
            number_format((int) $item->amount, 0, ',', '.'),
        ])->all(), [
            'Subtotal' => number_format((int) $quotation->sub_total, 0, ',', '.'),
            'PPN' => number_format((int) $quotation->tax, 0, ',', '.'),
            'Ongkir' => number_format((int) $quotation->shipping_cost, 0, ',', '.'),
            'Total' => number_format((int) $quotation->total, 0, ',', '.'),
        ], 'quotation-'.$quotation->no_quotation);
    }

    public function deliveryNote(string $dnNo): Response
    {
        $note = DeliveryNote::query()->where('dn_no', $dnNo)->with('items')->firstOrFail();

        return $this->render('Surat Jalan '.$note->dn_no, [
            'Customer' => $note->customer ?? '-',
            'No Sales' => $note->no_sales ?? '-',
            'Tanggal' => $note->date?->format('d/m/Y'),
            'Alamat' => $note->shipping_address ?? '-',
            'Status' => $note->status,
        ], ['Deskripsi', 'Qty', 'Satuan', 'Packing', 'Berat'], $note->items->map(fn ($item) => [
            $item->description,
            rtrim(rtrim(number_format((float) $item->qty, 2, ',', '.'), '0'), ','),
            $item->unit,
            $item->packing_qty,
            $item->weight,
        ])->all(), [], 'surat-jalan-'.$note->dn_no);
    }

    public function payslip(string $slipNo): Response
    {
        $payslip = Payslip::query()->where('slip_no', $slipNo)->with(['employee', 'items'])->firstOrFail();

        return $this->render('Slip Gaji '.$payslip->slip_no, [
            'Karyawan' => $payslip->employee?->name ?? '-',
            'Periode' => $payslip->period,
            'Hari Kerja' => rtrim(rtrim(number_format((float) $payslip->day_worked, 2, ',', '.'), '0'), ',').' hari',
            'Status' => $payslip->status,
        ], ['Komponen', 'Qty', 'Rate', 'Jumlah'], $payslip->items->map(fn ($item) => [
            $item->label,
            rtrim(rtrim(number_format((float) $item->qty, 2, ',', '.'), '0'), ','),
            number_format((int) $item->rate, 0, ',', '.'),
            number_format((int) $item->amount, 0, ',', '.'),
        ])->all(), [
            'Total' => number_format((int) $payslip->total, 0, ',', '.'),
        ], 'slip-gaji-'.$payslip->slip_no);
    }

    public function purchase(string $noPurchase): Response
    {
        $purchase = Purchase::query()->where('no_purchase', $noPurchase)->with(['supplier', 'lines'])->firstOrFail();

        return $this->render('Pembelian '.$purchase->no_purchase, [
            'Supplier' => $purchase->supplier?->name ?? '-',
            'Tanggal' => $purchase->date?->format('d/m/Y'),
            'Status' => $purchase->status,
            'Pembayaran' => $purchase->payment_status,
        ], ['Item', 'Qty', 'Satuan', 'Harga', 'Total'], $purchase->lines->map(fn ($line) => [
            $line->name,
            rtrim(rtrim(number_format((float) $line->qty, 2, ',', '.'), '0'), ','),
            $line->unit,
            number_format((int) $line->price, 0, ',', '.'),
            number_format((int) $line->total, 0, ',', '.'),
        ])->all(), [
            'Ongkir' => number_format((int) $purchase->shipping_cost, 0, ',', '.'),
            'Pajak' => number_format((int) $purchase->tax, 0, ',', '.'),
            'Total' => number_format((int) $purchase->amount, 0, ',', '.'),
            'Dibayar' => number_format((int) $purchase->paid, 0, ',', '.'),
            'Sisa' => number_format((int) $purchase->due, 0, ',', '.'),
        ], 'purchase-'.$purchase->no_purchase);
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, string>  $columns
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<string, mixed>  $totals
     */
    private function render(string $title, array $meta, array $columns, array $rows, array $totals, string $fileName): Response
    {
        $pdf = Pdf::loadView('pdf.document', [
            'title' => $title,
            'meta' => $meta,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
        ])->setPaper('a4');

        return $pdf->download($fileName.'.pdf');
    }
}
