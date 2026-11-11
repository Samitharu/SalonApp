<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stylist portal bookings: walk-ins (today) and phone/later bookings for
 * themselves, a colleague or whoever is free. The clock is fixed to a
 * Monday at 10:00 so time-of-day rules are deterministic.
 */
class StaffBookingTest extends TestCase
{
    use RefreshDatabase;

    private Staff $me;
    private Staff $priya;
    private Service $cut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('next monday 10:00'));

        for ($d = 0; $d <= 6; $d++) {
            BusinessHour::create(['day_of_week' => $d, 'open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => $d === 0]);
        }
        $this->me = Staff::create(['name' => 'Amaya', 'is_active' => true]);
        $this->priya = Staff::create(['name' => 'Priya', 'is_active' => true]);
        $this->cut = Service::create(['name' => 'Hair Cut', 'slug' => 'hair-cut', 'price' => 1500, 'duration_minutes' => 30, 'is_active' => true]);
    }

    private function book(array $over = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->me, 'staff')->postJson(route('stylist.walkin-store'), array_merge([
            'mode' => 'later', 'customer_name' => 'Nadeesha Silva', 'customer_phone' => '0771234567', 'customer_email' => 'nadeesha@example.com',
            'service_id' => $this->cut->id, 'date' => now()->addDays(2)->toDateString(), 'start_time' => '14:00',
            'staff_id' => $this->me->id, 'type' => 'phone',
        ], $over));
    }

    // ── Phone / later bookings ─────────────────────────────────────────

    public function test_stylist_can_take_a_phone_booking_for_a_later_date(): void
    {
        $this->book(['notes' => 'Prefers a short trim'])->assertOk()
            ->assertJson(['success' => true, 'mode' => 'later', 'status' => 'confirmed', 'stylist_name' => 'Amaya', 'mine' => true]);

        $a = Appointment::first();
        $this->assertSame('phone', $a->type);
        $this->assertSame('confirmed', $a->status);
        $this->assertFalse($a->walk_in);
        $this->assertNull($a->job_number);
        $this->assertNull($a->started_at);
        $this->assertSame($this->me->id, $a->staff_id);
        $this->assertSame('14:00:00', $a->slot_start);
        $this->assertSame('Prefers a short trim', $a->notes);

        $log = SystemLog::where('action', 'appointment.created')->first();
        $this->assertSame('staff', $log->actor_type);
        $this->assertSame('Amaya', $log->actor_name);
        $this->assertStringContainsString('Phone booking', $log->description);
    }

    public function test_stylist_can_book_a_colleague_or_any_available(): void
    {
        $this->book(['staff_id' => $this->priya->id])->assertOk()->assertJson(['stylist_name' => 'Priya', 'mine' => false]);
        $this->assertSame($this->priya->id, Appointment::latest('id')->first()->staff_id);

        // "Any available" at the same time: Priya is busy, so it goes to Amaya.
        $this->book(['staff_id' => null, 'customer_phone' => '0779999999', 'type' => 'counter'])->assertOk();
        $any = Appointment::latest('id')->first();
        $this->assertSame($this->me->id, $any->staff_id);
        $this->assertSame('counter', $any->type);
    }

    public function test_later_slots_follow_date_and_stylist(): void
    {
        $date = now()->addDays(2)->toDateString();
        $this->book(['staff_id' => $this->priya->id]);   // Priya busy 14:00–14:30
        $this->actingAs($this->me, 'staff');

        $starts = fn (array $q) => array_column($this->getJson(route('stylist.walkin-slots', $q + ['service_id' => $this->cut->id]))->assertOk()->json(), 'start');

        $this->assertNotContains('14:00', $starts(['mode' => 'later', 'date' => $date, 'staff_id' => $this->priya->id]));
        $this->assertContains('14:00', $starts(['mode' => 'later', 'date' => $date, 'staff_id' => $this->me->id]));
        $this->assertContains('14:00', $starts(['mode' => 'later', 'date' => $date])); // any: Amaya is free

        // Today: past times are skipped. Sunday: closed. Outside the window: nothing.
        $this->assertNotContains('09:00', $starts(['mode' => 'later', 'date' => now()->toDateString()]));
        $this->assertSame([], $starts(['mode' => 'later', 'date' => now()->subDay()->toDateString()]));
        $this->assertSame([], $starts(['mode' => 'later', 'date' => now()->next(Carbon::SUNDAY)->toDateString()]));
        $this->assertSame([], $starts(['mode' => 'later', 'date' => now()->addDays(400)->toDateString()]));
    }

    public function test_phone_booking_respects_conflicts_and_rules(): void
    {
        $this->book(['staff_id' => $this->priya->id])->assertOk();

        // Same stylist, same time.
        $this->book(['staff_id' => $this->priya->id, 'customer_phone' => '0778888888'])->assertStatus(422);
        // Same customer, overlapping time with another stylist.
        $this->book(['staff_id' => $this->me->id])->assertStatus(422)->assertJsonFragment(['error' => 'This customer already has an appointment from 2:00 PM to 2:30 PM on this day. Please choose a time that doesn’t overlap.']);
        // Past date and too far ahead.
        $this->book(['date' => now()->subDay()->toDateString()])->assertStatus(422);
        $this->book(['date' => now()->addDays(400)->toDateString()])->assertStatus(422);
        // Inactive colleague.
        $gone = Staff::create(['name' => 'Gone', 'is_active' => false]);
        $this->book(['staff_id' => $gone->id, 'customer_phone' => '0777777777'])->assertStatus(422);
        // Required fields.
        $this->book(['type' => null])->assertStatus(422)->assertJsonValidationErrors('type');
        $this->book(['date' => null])->assertStatus(422)->assertJsonValidationErrors('date');

        $this->assertSame(1, Appointment::count());
    }

    // ── Walk-ins ───────────────────────────────────────────────────────

    public function test_walk_in_now_starts_immediately(): void
    {
        $this->actingAs($this->me, 'staff')->postJson(route('stylist.walkin-store'), [
            'customer_name' => 'Walk In', 'customer_phone' => '0711111111', 'service_id' => $this->cut->id, 'start_time' => '10:00',
        ])->assertOk()->assertJson(['status' => 'in_progress']);

        $a = Appointment::first();
        $this->assertTrue($a->walk_in);
        $this->assertSame('in_progress', $a->status);
        $this->assertNotNull($a->started_at);
        $this->assertNotNull($a->job_number);
        $this->assertSame($this->me->id, $a->staff_id);
    }

    public function test_walk_in_for_later_today_is_confirmed_not_started(): void
    {
        $this->actingAs($this->me, 'staff')->postJson(route('stylist.walkin-store'), [
            'customer_name' => 'Back Later', 'customer_phone' => '0712222222', 'service_id' => $this->cut->id, 'start_time' => '15:00',
        ])->assertOk()->assertJson(['status' => 'confirmed']);

        $a = Appointment::first();
        $this->assertSame('confirmed', $a->status);
        $this->assertNull($a->started_at);
        $this->assertTrue($a->walk_in);
    }

    // ── Pages & access ─────────────────────────────────────────────────

    public function test_pages_offer_both_modes(): void
    {
        $this->actingAs($this->me, 'staff');
        $this->get(route('stylist.dashboard'))->assertOk()->assertSee('Phone booking');
        $this->get(route('stylist.walkin'))->assertOk()->assertSee('Walk-in · today', false)->assertSee('Create &amp; start appointment', false);
        $this->get(route('stylist.walkin', ['mode' => 'later']))->assertOk()->assertSee('Confirm booking')->assertSee('Any available stylist')->assertSee('Priya');
    }

    public function test_requires_staff_login(): void
    {
        $this->postJson(route('stylist.walkin-store'), [])->assertStatus(401);
        $this->get(route('stylist.walkin'))->assertRedirect('/stylist/login');
    }
}
