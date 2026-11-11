<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\SlotService;
use App\Models\BusinessHour;
use App\Models\Staff;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * ╔══════════════════════════════════════════════════════╗
 * ║          SlotService — Unit Test Suite               ║
 * ╚══════════════════════════════════════════════════════╝
 *
 * Scenarios covered:
 *  TC-U01  Returns empty array when salon is closed that day
 *  TC-U02  Returns empty array when no business hours row exists
 *  TC-U03  Slots step by service duration (not fixed 15-min)
 *  TC-U04  Slot fits exactly inside business hours
 *  TC-U05  Slot that would overrun closing time is excluded
 *  TC-U06  Stylist with custom work hours: slot outside window excluded
 *  TC-U07  Stylist conflict removes that slot
 *  TC-U08  Two stylists — conflict on one still shows slot (other is free)
 *  TC-U09  All stylists busy → slot not returned
 *  TC-U10  Cancelled appointments do NOT block slots
 *  TC-U11  No staff → falls back to max_parallel_bookings setting
 */
class SlotServiceTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeBusinessHour(int $dayOfWeek, string $open = '09:00:00', string $close = '17:00:00', bool $closed = false): BusinessHour
    {
        return BusinessHour::create([
            'day_of_week' => $dayOfWeek,
            'open_time'   => $open,
            'close_time'  => $close,
            'is_closed'   => $closed,
        ]);
    }

    private function makeStaff(string $name = 'Alice', ?string $workStart = null, ?string $workEnd = null): Staff
    {
        return Staff::create([
            'name'       => $name,
            'is_active'  => true,
            'work_start' => $workStart,
            'work_end'   => $workEnd,
        ]);
    }

    private function makeAppointment(string $date, string $start, string $end, ?int $staffId = null, string $status = 'confirmed'): Appointment
    {
        $category = ServiceCategory::firstOrCreate(['name' => 'Test Cat'], ['slug' => 'test-cat']);
        $service  = Service::firstOrCreate(['name' => 'Test Svc'], [
            'service_category_id' => $category->id,
            'slug'                => 'test-svc',
            'price'               => 100,
            'duration_minutes'    => 30,
            'is_active'           => true,
        ]);
        $customer = Customer::firstOrCreate(['phone' => '0700000001'], ['name' => 'Test Customer']);

        return Appointment::create([
            'appointment_no'   => strtoupper(uniqid()),
            'customer_id'      => $customer->id,
            'service_id'       => $service->id,
            'staff_id'         => $staffId,
            'appointment_date' => $date,
            'slot_start'       => $start,
            'slot_end'         => $end,
            'type'             => 'online',
            'status'           => $status,
        ]);
    }

    // ── TC-U01: Closed day returns [] ─────────────────────────────────────
    public function test_returns_empty_when_salon_is_closed(): void
    {
        $date = '2026-06-08'; // Monday = 1
        $this->makeBusinessHour(1, '09:00:00', '17:00:00', true); // is_closed = true

        $slots = SlotService::availableSlots($date, 30);
        $this->assertEmpty($slots, 'TC-U01: Expected no slots on closed day');
    }

    // ── TC-U02: Missing business hour row returns [] ──────────────────────
    public function test_returns_empty_when_no_business_hours_row(): void
    {
        $date  = '2026-06-08'; // Monday, no row created
        $slots = SlotService::availableSlots($date, 30);
        $this->assertEmpty($slots, 'TC-U02: Expected no slots when business hours row is missing');
    }

    // ── TC-U03: Slots step by service duration ───────────────────────────
    public function test_slots_step_by_service_duration(): void
    {
        $date = '2026-06-08'; // Monday
        $this->makeBusinessHour(1, '09:00:00', '11:00:00');
        $this->makeStaff('Bob');

        // 60-min service: expect 9:00 and 10:00 only
        $slots = SlotService::availableSlots($date, 60);

        $startTimes = array_column($slots, 'start');
        $this->assertContains('09:00', $startTimes, 'TC-U03: Missing 09:00 slot');
        $this->assertContains('10:00', $startTimes, 'TC-U03: Missing 10:00 slot');
        $this->assertNotContains('09:15', $startTimes, 'TC-U03: Should NOT have 09:15 (wrong step)');
        $this->assertNotContains('09:30', $startTimes, 'TC-U03: Should NOT have 09:30 (wrong step)');
    }

    // ── TC-U04: Slot exactly filling hours window is included ─────────────
    public function test_slot_exactly_filling_window_is_included(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '09:30:00');
        $this->makeStaff('Carol');

        $slots = SlotService::availableSlots($date, 30);
        $this->assertCount(1, $slots, 'TC-U04: Exactly one 30-min slot in 30-min window');
        $this->assertEquals('09:00', $slots[0]['start']);
        $this->assertEquals('09:30', $slots[0]['end']);
    }

    // ── TC-U05: Slot overrunning closing time is excluded ─────────────────
    public function test_slot_overrunning_close_is_excluded(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '09:29:00');
        $this->makeStaff('Dave');

        // 30-min service in 29-min window → no valid slot
        $slots = SlotService::availableSlots($date, 30);
        $this->assertEmpty($slots, 'TC-U05: No slot should fit a 30-min service in 29-min window');
    }

    // ── TC-U06: Stylist custom hours — slot outside window excluded ────────
    public function test_slot_outside_stylist_custom_hours_is_excluded(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '17:00:00');
        // Stylist works 12:00–14:00 only
        $stylist = $this->makeStaff('Eve', '12:00:00', '14:00:00');

        $slots = SlotService::availableSlots($date, 60, $stylist->id);

        $startTimes = array_column($slots, 'start');
        $this->assertContains('12:00', $startTimes, 'TC-U06: 12:00 slot should be available');
        $this->assertContains('13:00', $startTimes, 'TC-U06: 13:00 slot should be available');
        $this->assertNotContains('09:00', $startTimes, 'TC-U06: 09:00 is outside stylist hours');
        $this->assertNotContains('14:00', $startTimes, 'TC-U06: 14:00 overruns stylist end');
    }

    // ── TC-U07: Existing appointment blocks that slot for stylist ─────────
    public function test_existing_appointment_blocks_slot_for_specific_stylist(): void
    {
        $date    = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '11:00:00');
        $stylist = $this->makeStaff('Frank');

        // Stylist booked 09:00–10:00
        $this->makeAppointment($date, '09:00:00', '10:00:00', $stylist->id);

        $slots = SlotService::availableSlots($date, 60, $stylist->id);

        $startTimes = array_column($slots, 'start');
        $this->assertNotContains('09:00', $startTimes, 'TC-U07: 09:00 should be blocked');
        $this->assertContains('10:00', $startTimes, 'TC-U07: 10:00 should be free');
    }

    // ── TC-U08: One stylist busy → slot still returned (other is free) ────
    public function test_slot_still_available_if_one_stylist_is_busy(): void
    {
        $date     = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '11:00:00');
        $stylist1 = $this->makeStaff('Grace');
        $stylist2 = $this->makeStaff('Heidi');

        // Only stylist1 is busy at 09:00
        $this->makeAppointment($date, '09:00:00', '10:00:00', $stylist1->id);

        // "Any stylist" mode — stylist2 is free so slot should be returned
        $slots = SlotService::availableSlots($date, 60);

        $startTimes = array_column($slots, 'start');
        $this->assertContains('09:00', $startTimes, 'TC-U08: 09:00 slot should still exist (stylist2 is free)');

        // Check stylist2 is listed as free in that slot
        $nineSlot = array_values(array_filter($slots, fn($s) => $s['start'] === '09:00'))[0] ?? null;
        $this->assertNotNull($nineSlot);
        $freeIds = array_column($nineSlot['stylists'], 'id');
        $this->assertContains($stylist2->id, $freeIds);
        $this->assertNotContains($stylist1->id, $freeIds);
    }

    // ── TC-U09: All stylists busy → slot not returned ─────────────────────
    public function test_slot_not_returned_when_all_stylists_busy(): void
    {
        $date     = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '11:00:00');
        $stylist1 = $this->makeStaff('Ivan');
        $stylist2 = $this->makeStaff('Judy');

        $this->makeAppointment($date, '09:00:00', '10:00:00', $stylist1->id);
        $this->makeAppointment($date, '09:00:00', '10:00:00', $stylist2->id);

        $slots = SlotService::availableSlots($date, 60);

        $startTimes = array_column($slots, 'start');
        $this->assertNotContains('09:00', $startTimes, 'TC-U09: No stylists free at 09:00');
        $this->assertContains('10:00', $startTimes, 'TC-U09: 10:00 should be free');
    }

    // ── TC-U10: Cancelled appointments do NOT block slots ─────────────────
    public function test_cancelled_appointments_do_not_block_slots(): void
    {
        $date    = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '11:00:00');
        $stylist = $this->makeStaff('Karl');

        // Appointment is CANCELLED — should not block the slot
        $this->makeAppointment($date, '09:00:00', '10:00:00', $stylist->id, 'cancelled');

        $slots = SlotService::availableSlots($date, 60, $stylist->id);

        $startTimes = array_column($slots, 'start');
        $this->assertContains('09:00', $startTimes, 'TC-U10: Cancelled appointment should not block slot');
    }

    // ── TC-U11: No staff → max_parallel_bookings fallback ─────────────────
    public function test_fallback_to_max_parallel_bookings_when_no_staff(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '10:00:00');

        $slots = SlotService::availableSlots($date, 30);
        $this->assertCount(2, $slots, 'TC-U11: 2 slots in 60-min window with 30-min duration');
    }

    // ── TC-U13: Closure (day off) returns no slots ─────────────────────────
    public function test_returns_empty_on_closure_day(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1);
        $this->makeStaff('Lena');
        \App\Models\DayOff::create(['off_date' => $date, 'reason' => 'Holiday']);

        $this->assertEmpty(SlotService::availableSlots($date, 30));
    }

    // ── TC-U14: not_before skips earlier slots ────────────────────────────
    public function test_not_before_option_skips_earlier_slots(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '12:00:00');
        $this->makeStaff('Mia');

        $slots = SlotService::availableSlots($date, 60, null, ['not_before' => Carbon::parse("$date 10:00")]);

        $this->assertEquals(['10:00', '11:00'], array_column($slots, 'start'));
    }

    // ── TC-U15: step option overrides duration stepping ───────────────────
    public function test_step_option_controls_slot_spacing(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '10:30:00');
        $this->makeStaff('Noor');

        $slots = SlotService::availableSlots($date, 60, null, ['step' => 15]);

        $this->assertEquals(['09:00', '09:15', '09:30'], array_column($slots, 'start'));
    }

    // ── TC-U16: no-show appointments free the slot ────────────────────────
    public function test_no_show_appointments_do_not_block_slots(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '11:00:00');
        $stylist = $this->makeStaff('Omar');
        $this->makeAppointment($date, '09:00:00', '10:00:00', $stylist->id, 'no_show');

        $this->assertContains('09:00', array_column(SlotService::availableSlots($date, 60, $stylist->id), 'start'));
    }

    // ── TC-U17: cleanup buffer keeps time free after appointments ──────────
    public function test_buffer_minutes_block_time_after_appointments(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '12:00:00');
        $stylist = $this->makeStaff('Pia');
        $this->makeAppointment($date, '09:00:00', '10:00:00', $stylist->id);
        \App\Models\Setting::create(['key' => 'buffer_minutes', 'value' => '15']);
        setting('__flush');

        $starts = array_column(SlotService::availableSlots($date, 60, $stylist->id, ['step' => 15]), 'start');

        $this->assertNotContains('10:00', $starts, 'TC-U17: 10:00 is inside the 15-minute cleanup');
        $this->assertContains('10:15', $starts, 'TC-U17: free once cleanup ends');
        // A new booking also needs its own cleanup before the next one: 08:00 slot ending 09:00 would clash.
        setting('__flush');
    }

    // ── TC-U18: a job that started late is treated as finishing late ───────
    public function test_late_started_job_pushes_out_availability(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '13:00:00');
        $stylist = $this->makeStaff('Quinn');
        $appt = $this->makeAppointment($date, '09:00:00', '10:00:00', $stylist->id, 'in_progress');
        $appt->update(['started_at' => \Carbon\Carbon::parse("$date 09:30:00")]); // started 30 min late → ends 10:30

        $starts = array_column(SlotService::availableSlots($date, 30, $stylist->id, ['step' => 30]), 'start');

        $this->assertNotContains('10:00', $starts, 'TC-U18: still busy until 10:30');
        $this->assertContains('10:30', $starts);
    }

    // ── TC-U19: customer can't overlap themselves ────────────────────────
    public function test_customer_conflict_detects_overlap_with_any_stylist(): void
    {
        $date = '2026-06-08';
        $a = $this->makeStaff('Rae');
        $appt = $this->makeAppointment($date, '10:00:00', '11:00:00', $a->id);

        $this->assertNotNull(SlotService::customerConflict($appt->customer_id, $date, '10:30', '11:30'));
        $this->assertNull(SlotService::customerConflict($appt->customer_id, $date, '11:00', '12:00'), 'Back-to-back is fine');
    }

    // ── TC-U20: salon lunch break is never bookable ─────────────────────
    public function test_salon_lunch_break_blocks_slots(): void
    {
        $date = '2026-06-08';
        $hours = $this->makeBusinessHour(1, '09:00:00', '17:00:00');
        $hours->update(['break_start' => '13:00:00', 'break_end' => '14:00:00']);
        $this->makeStaff('Sam');

        $starts = array_column(SlotService::availableSlots($date, 60, null, ['step' => 30]), 'start');

        $this->assertContains('12:00', $starts, 'ends exactly when lunch starts');
        $this->assertNotContains('12:30', $starts, 'would run into lunch');
        $this->assertNotContains('13:00', $starts, 'during lunch');
        $this->assertNotContains('13:30', $starts, 'during lunch');
        $this->assertContains('14:00', $starts, 'right after lunch');
    }

    // ── TC-U21: a stylist's own break only blocks that stylist ───────────
    public function test_stylist_break_only_blocks_that_stylist(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '17:00:00');
        $onBreak = $this->makeStaff('Tara');
        $onBreak->update(['break_start' => '12:00:00', 'break_end' => '13:00:00']);
        $working = $this->makeStaff('Uma');

        $noon = collect(SlotService::availableSlots($date, 60))->firstWhere('start', '12:00');

        $this->assertNotNull($noon);
        $ids = array_column($noon['stylists'], 'id');
        $this->assertContains($working->id, $ids);
        $this->assertNotContains($onBreak->id, $ids);
        $this->assertEquals('break', SlotService::stylistConflict($onBreak->id, $date, '12:30', '13:00')['reason']);
    }

    // ── TC-U22: per-service cleanup overrides the salon default ─────────
    public function test_service_cleanup_time_is_kept_after_its_appointments(): void
    {
        $date = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '12:00:00');
        $stylist = $this->makeStaff('Vee');
        $appt = $this->makeAppointment($date, '09:00:00', '10:00:00', $stylist->id);
        $appt->service->update(['buffer_minutes' => 30]); // e.g. colour needs 30 min cleanup

        $starts = array_column(SlotService::availableSlots($date, 30, $stylist->id, ['step' => 15]), 'start');

        $this->assertNotContains('10:15', $starts);
        $this->assertContains('10:30', $starts);
    }

    // ── TC-U12: NULL staff_id appointment blocks ALL stylists ──────────────
    public function test_null_staff_appointment_blocks_all_stylists(): void
    {
        $date    = '2026-06-08';
        $this->makeBusinessHour(1, '09:00:00', '11:00:00');
        $stylist1 = $this->makeStaff('Nimal');
        $stylist2 = $this->makeStaff('Kamala');

        // Appointment with NO staff assigned (null) — should block everyone
        $this->makeAppointment($date, '09:00:00', '10:00:00', null);

        // "Any stylist" mode
        $slots = SlotService::availableSlots($date, 60);
        $startTimes = array_column($slots, 'start');

        $this->assertNotContains('09:00', $startTimes,
            'TC-U12: Unassigned appointment must block 09:00 for ALL stylists');
        $this->assertContains('10:00', $startTimes,
            'TC-U12: 10:00 should be free after the unassigned appointment ends');
    }
}
