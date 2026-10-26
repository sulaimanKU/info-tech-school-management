<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class TicketAccess
{
    public function __construct(private SchoolAccess $school) {}

    public function query(User $u)
    {
        $q = DB::table('support_tickets as t');
        if ($u->hasPermission('communication.manage')) {
            return $q->whereIn('t.student_id', $this->school->students($u)->select('students.id'));
        }
        if ($this->school->isParent($u)) {
            return $q->where('t.guardian_user_id', $u->id)->whereIn('t.student_id', $this->school->students($u)->select('students.id'));
        }

        return $q->whereIn('t.id', DB::table('support_ticket_forwards')->where('user_id', $u->id)->select('support_ticket_id'))->whereIn('t.student_id', $this->school->students($u)->select('students.id'));
    }

    public function ticket(User $u, string $id)
    {
        return $this->query($u)->where('t.id', $id)->firstOrFail();
    }
}
