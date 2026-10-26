<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MobileTestCase;

class CommunicationTest extends MobileTestCase
{
    private function ticket(string $student): array
    {
        return ['student_id' => $student, 'category' => 'academic_progress', 'subject' => 'Fictional learning query', 'message' => 'Fictional parent question', 'attachments' => [], 'client_id' => (string) Str::uuid()];
    }

    public function test_ticket_authorization_internal_notes_reply_and_resolution(): void
    {
        $one = $this->student();
        $two = $this->student($this->otherSection);
        [, $p1] = $this->guardian($one);
        [, $p2] = $this->guardian($two, '0000000000002');
        $this->asUser($p1);
        $v = $this->ticket($one);
        $id = $this->postJson('/api/v1/tickets', $v)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/tickets', $v)->assertCreated()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('support_tickets', 1);
        $this->asUser($p2)->getJson('/api/v1/tickets/'.$id)->assertNotFound();
        $this->postJson('/api/v1/tickets/'.$id.'/messages', ['message' => 'IDOR', 'is_internal' => false, 'attachments' => [], 'client_id' => (string) Str::uuid()])->assertNotFound();
        $coordinator = $this->coordinator();
        $this->asUser($coordinator)->postJson('/api/v1/tickets/'.$id.'/workflow', ['version' => 1, 'status' => 'acknowledged', 'priority' => 'high', 'internal_note' => 'Fictional private staff note'])->assertOk();
        $this->postJson('/api/v1/tickets/'.$id.'/messages', ['message' => 'Fictional coordinator response', 'is_internal' => false, 'attachments' => [], 'client_id' => (string) Str::uuid()])->assertCreated();
        $this->asUser($p1)->getJson('/api/v1/tickets/'.$id)->assertOk()->assertDontSee('Fictional private staff note')->assertSee('Fictional coordinator response')->assertJsonPath('data.messages.0.sender_kind', 'parent')->assertJsonPath('data.messages.1.sender_kind', 'staff')->assertJsonMissingPath('data.messages.0.sender_user_id');
        $this->postJson('/api/v1/tickets/'.$id.'/messages', ['message' => 'Forbidden internal', 'is_internal' => true, 'attachments' => [], 'client_id' => (string) Str::uuid()])->assertForbidden();
        $this->asUser($coordinator)->postJson('/api/v1/tickets/'.$id.'/workflow', ['version' => 3, 'status' => 'in_progress', 'priority' => 'high'])->assertOk();
        $this->postJson('/api/v1/tickets/'.$id.'/workflow', ['version' => 4, 'status' => 'resolved', 'priority' => 'normal'])->assertOk();
        $this->postJson('/api/v1/tickets/'.$id.'/workflow', ['version' => 5, 'status' => 'closed', 'priority' => 'normal'])->assertOk();
        $this->asUser($p1)->postJson('/api/v1/tickets/'.$id.'/messages', ['message' => 'Closed', 'is_internal' => false, 'attachments' => [], 'client_id' => (string) Str::uuid()])->assertUnprocessable();
    }

    public function test_parent_home_multiple_children_without_fabricated_summaries(): void
    {
        $one = $this->student();
        $two = $this->student(roll: 2);
        [$g,$parent] = $this->guardian($one);
        $this->asUser($this->admin)->putJson('/api/v1/guardians/'.$g.'/links', ['student_id' => $two, 'relationship' => 'guardian', 'is_primary' => true])->assertOk();
        $this->asUser($parent)->getJson('/api/v1/parent/home')->assertOk()->assertJsonCount(2, 'data.children')->assertJsonPath('data.children.0.fee_status', 'no_vouchers')->assertJsonPath('data.children.0.attendance_percentage', null)->assertJsonPath('data.children.0.latest_academic', null)->assertDontSee('0000000000001')->assertDontSee('+923000000001');
    }

    public function test_announcement_targets_unread_state_and_notification_idor(): void
    {
        $one = $this->student();
        $two = $this->student($this->otherSection);
        [, $p1] = $this->guardian($one);
        [, $p2] = $this->guardian($two, '0000000000002');
        $this->asUser($this->coordinator());
        $draft = ['title' => 'Fictional PTM', 'body' => 'Fictional notice text', 'kind' => 'ptm', 'audience' => 'parent', 'target_user_id' => $p1->id, 'status' => 'draft', 'version' => 0];
        $id = $this->postJson('/api/v1/announcements', $draft)->assertCreated()->json('data.id');
        $this->asUser($p1)->getJson('/api/v1/announcements/'.$id)->assertNotFound();
        $this->asUser($this->admin)->putJson('/api/v1/announcements/'.$id, [...$draft, 'status' => 'published', 'version' => 1])->assertOk();
        $this->asUser($p1)->getJson('/api/v1/announcements')->assertJsonPath('data.data.0.read_at', null);
        $this->getJson('/api/v1/announcements/'.$id)->assertOk();
        $this->getJson('/api/v1/announcements')->assertJsonCount(1, 'data.data');
        $notice = DB::table('school_notifications')->where('user_id', $p1->id)->first();
        $this->assertNotNull($notice);
        $this->postJson('/api/v1/notifications/'.$notice->id.'/read')->assertOk();
        $this->asUser($p2)->getJson('/api/v1/announcements/'.$id)->assertNotFound();
        $this->postJson('/api/v1/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->getJson('/api/v1/notifications')->assertJsonCount(0, 'data.data');
    }
}
