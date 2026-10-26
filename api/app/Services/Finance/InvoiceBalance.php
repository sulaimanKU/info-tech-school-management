<?php

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;

final class InvoiceBalance
{
    public function many($invoices): array
    {
        $ids = $invoices->pluck('id');
        if ($ids->isEmpty()) {
            return [];
        }
        $adjustments = DB::table('invoice_adjustments')->whereIn('fee_invoice_id', $ids)->groupBy('fee_invoice_id')->selectRaw('fee_invoice_id,sum(amount_minor) as amount')->pluck('amount', 'fee_invoice_id');
        $paid = DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')->whereIn('a.fee_invoice_id', $ids)->where('p.status', 'verified')->groupBy('a.fee_invoice_id')->selectRaw('a.fee_invoice_id,sum(a.amount_minor) as amount')->pluck('amount', 'fee_invoice_id');
        $refunds = DB::table('refund_allocations as r')->join('payment_allocations as a', 'a.id', '=', 'r.payment_allocation_id')->whereIn('a.fee_invoice_id', $ids)->groupBy('a.fee_invoice_id')->selectRaw('a.fee_invoice_id,sum(r.amount_minor) as amount')->pluck('amount', 'fee_invoice_id');
        $result = [];
        foreach ($invoices as $i) {
            $result[$i->id] = $this->amounts($i, (int) $adjustments->get($i->id, 0), (int) $paid->get($i->id, 0), (int) $refunds->get($i->id, 0));
        }

return $result;
    }

    public function calculate(object $invoice): array
    {
        $adjustments = (int) DB::table('invoice_adjustments')->where('fee_invoice_id', $invoice->id)->sum('amount_minor');
        $paid = (int) DB::table('payment_allocations as a')->join('payments as p', 'p.id', '=', 'a.payment_id')->where('a.fee_invoice_id', $invoice->id)->where('p.status', 'verified')->sum('a.amount_minor');
        $refunded = (int) DB::table('refund_allocations as r')->join('payment_allocations as a', 'a.id', '=', 'r.payment_allocation_id')->where('a.fee_invoice_id', $invoice->id)->sum('r.amount_minor');

        return $this->amounts($invoice, $adjustments, $paid, $refunded);
    }

    private function amounts(object $invoice, int $adjustments, int $paid, int $refunded): array
    {
        $total = (int) $invoice->total_minor + $adjustments;
        $netPaid = $paid - $refunded;
        $balance = $total - $netPaid;
        $status = $total === 0 ? 'waived_adjusted' : ($balance === 0 ? 'paid' : ($netPaid > 0 ? 'partially_paid' : ($invoice->due_on < now()->toDateString() ? 'overdue' : 'unpaid')));

        return ['net_total_minor' => $total, 'paid_minor' => $netPaid, 'refunded_minor' => $refunded, 'balance_minor' => $balance, 'status' => $status];
    }
}
