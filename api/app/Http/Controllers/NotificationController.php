<?php

namespace App\Http\Controllers;

use App\Support\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $r)
    {
        return Domain::response(DB::table('school_notifications')->where('user_id', $r->user()->id)->orderByDesc('created_at')->paginate(30, ['id', 'kind', 'target_type', 'target_id', 'student_id', 'read_at', 'created_at']));
    }

    public function read(Request $r, string $notification)
    {
        DB::table('school_notifications')->where('id', $notification)->where('user_id', $r->user()->id)->firstOrFail();
        DB::table('school_notifications')->where('id', $notification)->where('user_id', $r->user()->id)->update(['read_at' => now(), 'updated_at' => now()]);

        return Domain::response(['read' => true]);
    }
}
