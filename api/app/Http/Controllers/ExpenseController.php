<?php

namespace App\Http\Controllers;

use App\Services\Finance\ExpenseService;
use App\Services\SchoolAccess;
use App\Support\Audit;
use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function __construct(private SchoolAccess $access) {}

    public function index(Request $r)
    {
        $this->access->require($r->user(), 'finance.read');

        return Domain::response(DB::table('expenses as e')->join('expense_categories as c', 'c.id', '=', 'e.expense_category_id')->leftJoin('private_files as f', 'f.id', '=', 'e.proof_file_id')->orderByDesc('e.created_at')->paginate(30, ['e.*', 'c.name as category_name', 'f.mime as proof_mime']));
    }

    public function store(Request $r)
    {
        $this->access->require($r->user(), 'finance.write');
        $v = $r->validate(['expense_category_id' => 'required|uuid|exists:expense_categories,id', 'description' => 'required|string|max:500', 'amount_minor' => 'required|integer|min:1|max:1000000000', 'expense_on' => 'required|date|before_or_equal:today', 'proof_file_id' => 'nullable|uuid|exists:private_files,id']);
        if ($v['proof_file_id'] ?? null) {
            $f = DB::table('private_files')->find($v['proof_file_id']);
            abort_unless($f->purpose === 'expense_proof', 403);
        }$id = DB::transaction(function () use ($r, $v) {
            $id = Domain::insert('expenses', [...$v, 'requested_by' => $r->user()->id, 'status' => 'requested']);
            Audit::record('expense_requested', $r->user()->id, 'expenses', $id);

            return $id;
        });

        return Domain::response(['id' => $id], 201);
    }

    public function approve(Request $r, string $expense, ExpenseService $service)
    {
        $this->access->require($r->user(), 'finance.approve');
        $service->approve($r->user(), $expense);

        return Domain::response(['approved' => true]);
    }

    public function deny(Request $r, string $expense)
    {
        $this->access->require($r->user(), 'finance.approve');
        DB::transaction(function () use ($r, $expense) {
            $e = DB::table('expenses')->where('id', $expense)->lockForUpdate()->firstOrFail();
            abort_unless($e->status === 'requested', 409);
            DB::table('expenses')->where('id', $expense)->update(['status' => 'denied', 'updated_at' => now()]);
            Audit::record('expense_denied', $r->user()->id, 'expenses', $expense);
        });

        return Domain::response(['denied' => true]);
    }

    public function pay(Request $r, string $expense, ExpenseService $service)
    {
        $this->access->require($r->user(), 'finance.write');
        $v = $r->validate(['payment_method_id' => 'required|uuid|exists:payment_methods,id', 'paid_on' => 'required|date|before_or_equal:today', 'reference' => 'nullable|string|max:200']);
        $service->pay($r->user(), $expense, $v);

        return Domain::response(['paid' => true]);
    }
}
