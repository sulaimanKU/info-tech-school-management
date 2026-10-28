<?php

namespace App\Http\Controllers;

use App\Services\SchoolAccess;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    public function trialBalance(Request $r, SchoolAccess $access)
    {
        $access->require($r->user(), 'accounting.read');
        // Omitted dates report the complete ledger; an explicit range must be complete and valid.
        $v = $r->validate(['from' => 'required_with:to|date', 'to' => 'required_with:from|date|after_or_equal:from']);
        $lines = DB::table('journal_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')->join('accounts as a', 'a.id', '=', 'l.account_id')->when(isset($v['from']), fn ($q) => $q->whereBetween('e.date', [$v['from'], $v['to']]))->groupBy('a.id', 'a.code', 'a.name', 'a.type')->get(['a.id', 'a.code', 'a.name', 'a.type', DB::raw('SUM(l.debit_minor) AS debit_minor'), DB::raw('SUM(l.credit_minor) AS credit_minor')]);
        $debit = (int) $lines->sum('debit_minor');
        $credit = (int) $lines->sum('credit_minor');

        return Domain::response(['items' => $lines, 'debit_minor' => $debit, 'credit_minor' => $credit, 'balanced' => $debit === $credit, 'report_type' => 'trial_balance', 'currency' => 'PKR']);
    }

    public function journals(Request $r, SchoolAccess $access)
    {
        $access->require($r->user(), 'accounting.read');

        return Domain::response(DB::table('journal_entries')->orderByDesc('date')->paginate(30, ['id', 'number', 'date', 'description', 'reference_type', 'reference_id']));
    }
}
