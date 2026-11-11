<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Staff;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Nobody — customer, admin or stylist — can book or move an appointment to
 * a time that has already passed. The clock is fixed to Monday 15:07 (salon time).
 */
class PastTimeTest extends TestCase
{
    use RefreshDatabase;

    private Service $cut;
    private Staff $amaya;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('next monday 15:07', config('app.timezone')));

        for ($d = 0; $d <= 6; $d++) {
            BusinessHour::create(['day_of_week' => $d, 'open_time' => '09:00:00', 'close_time' => '20:00:00', 'is_closed' => false]);
        }
        Admin::create(['is_owner' => true, 'name' => 'Owner', 'email' => 'o@s.lk', 'password' => Hash::make('secret123')]);
        $this->amaya = Staff::create(['name' => 'Amaya', 'is_active' => true]);
        $this->cut = Service::create(['name' => 'Hair Cut', 'slug' => 'hair-cut', 'price' => 1500, 'duration_minutes' => 30, 'is_active' => true]);
    }

    public function test_app_runs_on_salon_time(): void
    {
        $this->assertSame('Asia/Colombo', config('app.timezone'));
        $this->assertSame('Asia/Colombo', now()->getTimezone()->getName());
    }

    public function test_engine_rejects_past_start_times(): void
    {
        $today = now()->toDateString();

        BookingService::assertNotPast($today, '15:07');                 // this minute: fine
        BookingService::assertNotPast($today, '14:55', walkIn: true);   // walk-in grace: fine

        $this->expectException(RuntimeException::class);
        BookingService::assertNotPast($today, '15:00');                 // already started
    }

    public function test_walk_in_grace_is_limited(): void
    {
        $this->expectException(RuntimeException::class);
        BookingService::assertNotPast(now()->toDateString(), '14:30', walkIn: true);
    }

    public function test_customers_are_not_offered_or_allowed_past_times(): void
    {
        $today = now()->toDateString();
        $starts = array_column($this->getJson("/booking/slots?service_id={$this->cut->id}&date={$today}")->assertOk()->json(), 'start');

        foreach ($starts as $s) {
            $this->assertGreaterThanOrEqual('15:07', $s, "Slot $s is in the past");
        }

        $this->postJson(route('booking.store'), [
            'name' => 'Late Lara', 'phone' => '0771234567', 'email' => 'lara@example.com', 'service_id' => $this->cut->id, 'date' => $today, 'start_time' => '11:00',
        ])->assertStatus(422);
        $this->postJson(route('booking.store'), [
            'name' => 'Late Lara', 'phone' => '0771234567', 'email' => 'lara@example.com', 'service_id' => $this->cut->id, 'date' => now()->subDay()->toDateString(), 'start_time' => '16:00',
        ])->assertStatus(422);

        $this->assertSame(0, Appointment::count());
    }

    public function test_admin_cannot_book_a_past_time_today(): void
    {
        $this->actingAs(Admin::first(), 'admin');
        $today = now()->toDateString();

        $starts = array_column($this->getJson(route('admin.appointments.slots', ['service_id' => $this->cut->id, 'date' => $today]))->json(), 'start');
        $this->assertNotContains('15:00', $starts);   // started 7 minutes ago
        $this->assertContains('15:30', $starts);

        $this->post(route('admin.appointments.store'), [
            'name' => 'Phone Pam', 'phone' => '0712345678', 'email' => 'pam@example.com', 'service_id' => $this->cut->id, 'date' => $today,
            'start_time' => '15:00', 'type' => 'phone',
        ])->assertSessionHas('error');

        $this->assertSame(0, Appointment::count());
    }

    public function test_stylist_phone_booking_cannot_be_in_the_past(): void
    {
        $this->actingAs($this->amaya, 'staff')->postJson(route('stylist.walkin-store'), [
            'mode' => 'later', 'customer_name' => 'Past Pete', 'customer_phone' => '0723456789', 'customer_email' => 'pete@example.com', 'service_id' => $this->cut->id,
            'date' => now()->toDateString(), 'start_time' => '10:00', 'staff_id' => $this->amaya->id, 'type' => 'phone',
        ])->assertStatus(422);

        $this->assertSame(0, Appointment::count());
    }

    public function test_booking_cannot_be_moved_into_the_past(): void
    {
        $customer = Customer::create(['name' => 'Kamal', 'phone' => '0771111111']);
        $appt = Appointment::create([
            'appointment_no' => 'MOVE0001', 'customer_id' => $customer->id, 'service_id' => $this->cut->id, 'staff_id' => $this->amaya->id,
            'appointment_date' => now()->addDay()->toDateString(), 'slot_start' => '10:00:00', 'slot_end' => '10:30:00',
            'type' => 'online', 'status' => 'confirmed',
        ]);

        $this->expectException(RuntimeException::class);
        BookingService::reschedule($appt, $this->cut, now()->toDateString(), '12:00', $this->amaya->id, 'admin');
    }
}
