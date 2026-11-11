<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\DayOff;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Public booking flow.
 */
class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function nextMonday(): string
    {
        return Carbon::now()->next(Carbon::MONDAY)->toDateString();
    }

    private function seedOpenDay(string $date, string $open = '09:00:00', string $close = '18:00:00'): BusinessHour
    {
        return BusinessHour::updateOrCreate(
            ['day_of_week' => Carbon::parse($date)->dayOfWeek],
            ['open_time' => $open, 'close_time' => $close, 'is_closed' => false]
        );
    }

    private function makeService(int $duration = 60, string $name = 'Hair Cut', bool $active = true): Service
    {
        $cat = ServiceCategory::firstOrCreate(['name' => 'Hair'], ['slug' => 'hair']);

        return Service::create([
            'service_category_id' => $cat->id,
            'name'                => $name,
            'slug'                => Str::slug($name . '-' . uniqid()),
            'price'               => 1500.00,
            'duration_minutes'    => $duration,
            'is_active'           => $active,
        ]);
    }

    private function makeStaff(string $name = 'Amaya'): Staff
    {
        return Staff::create(['name' => $name, 'is_active' => true]);
    }

    private function payload(Service $service, string $date, array $over = []): array
    {
        return array_merge([
            'name'       => 'Nadeesha Silva',
            'phone'      => '077 123 4567',
            'email'      => 'nadeesha@example.com',
            'service_id' => $service->id,
            'date'       => $date,
            'start_time' => '10:00',
            'staff_id'   => null,
        ], $over);
    }

    // ── Pages ─────────────────────────────────────────────────────────

    public function test_home_page_loads_successfully(): void
    {
        $this->get('/')->assertOk()->assertSee('Book now');
    }

    public function test_services_page_loads_successfully(): void
    {
        $this->makeService(30, 'Facial Glow');
        $this->get('/services')->assertOk()->assertSee('Facial Glow');
    }

    public function test_booking_page_shows_services(): void
    {
        $this->makeService(30, 'Facial');
        $this->get('/booking')->assertOk()->assertSee('Facial')->assertSee('Choose a service');
    }

    public function test_booking_page_hides_inactive_services(): void
    {
        $this->makeService(30, 'Retired Treatment', false);
        $this->get('/booking')->assertOk()->assertDontSee('Retired Treatment');
    }

    public function test_booking_page_preselects_service_from_url(): void
    {
        $service = $this->makeService(45, 'Manicure');
        $this->get("/booking?service={$service->id}")->assertOk()->assertSee('"preselect":' . $service->id, false);
    }

    public function test_booking_page_has_search_and_category_filter(): void
    {
        $skin = ServiceCategory::create(['name' => 'Skin Care', 'slug' => 'skin']);
        foreach (['Ladies Haircut', 'Blow-dry', 'Hair Spa'] as $n) {
            $this->makeService(30, $n);
        }
        foreach (['Gold Facial', 'Eyebrow Threading', 'Waxing'] as $n) {
            Service::create(['service_category_id' => $skin->id, 'name' => $n, 'slug' => Str::slug($n), 'price' => 1000, 'duration_minutes' => 30, 'is_active' => true]);
        }

        $this->get('/booking')->assertOk()
            ->assertSee('id="svcSearch"', false)
            ->assertSee('data-cat="all" aria-pressed="true"', false)
            ->assertSee('Skin Care <span class="count">3</span>', false)
            // Category name is searchable too ("skin" finds the facial).
            ->assertSee('data-search="gold facial  skin care"', false);
    }

    public function test_booking_page_opens_on_a_category_from_the_url(): void
    {
        $skin = ServiceCategory::create(['name' => 'Skin Care', 'slug' => 'skin']);
        foreach (['Cut 1', 'Cut 2', 'Cut 3', 'Cut 4', 'Cut 5'] as $n) {
            $this->makeService(30, $n);
        }
        Service::create(['service_category_id' => $skin->id, 'name' => 'Gold Facial', 'slug' => 'gold', 'price' => 1000, 'duration_minutes' => 30, 'is_active' => true]);
        $hairId = ServiceCategory::where('slug', 'hair')->value('id');

        $this->get('/booking?category=skin')->assertOk()
            ->assertSee('data-cat="' . $skin->id . '" aria-pressed="true"', false)
            ->assertSee('data-cat="all" aria-pressed="false"', false)
            ->assertSee('data-group="' . $hairId . '"  hidden', false);

        // Unknown category: everything shown.
        $this->get('/booking?category=nope')->assertOk()->assertSee('data-cat="all" aria-pressed="true"', false);
    }

    public function test_small_menus_skip_the_filter_and_never_hide_services(): void
    {
        $skin = ServiceCategory::create(['name' => 'Skin Care', 'slug' => 'skin']);
        $this->makeService(30, 'Only cut');
        Service::create(['service_category_id' => $skin->id, 'name' => 'Gold Facial', 'slug' => 'gold', 'price' => 1000, 'duration_minutes' => 30, 'is_active' => true]);

        // No filter for a short menu — and ?category= must not hide anything there.
        $this->get('/booking?category=skin')->assertOk()->assertDontSee('id="svcSearch"', false)->assertDontSee(' hidden >', false);
    }

    public function test_setup_route_no_longer_exists(): void
    {
        $this->get('/setup')->assertNotFound();
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    // ── Slots endpoint ───────────────────────────────────────────────

    public function test_slots_endpoint_returns_slots_for_open_day(): void
    {
        $date    = $this->nextMonday();
        $service = $this->makeService(60);
        $this->makeStaff();
        $this->seedOpenDay($date);

        $response = $this->getJson("/booking/slots?service_id={$service->id}&date={$date}");
        $response->assertOk()->assertJsonStructure([['start', 'end', 'stylists']]);

        $first = $response->json()[0];
        $this->assertEquals(3600, strtotime($first['end']) - strtotime($first['start']));
    }

    public function test_slots_endpoint_rejects_invalid_service_id(): void
    {
        $this->getJson('/booking/slots?service_id=99999&date=' . $this->nextMonday())->assertStatus(422);
    }

    public function test_slots_endpoint_rejects_missing_date(): void
    {
        $service = $this->makeService();
        $this->getJson("/booking/slots?service_id={$service->id}")->assertStatus(422);
    }

    public function test_slots_endpoint_returns_empty_on_closed_day(): void
    {
        $date    = Carbon::now()->next(Carbon::SUNDAY)->toDateString();
        $service = $this->makeService();
        BusinessHour::create(['day_of_week' => 0, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'is_closed' => true]);

        $this->getJson("/booking/slots?service_id={$service->id}&date={$date}")->assertOk()->assertExactJson([]);
    }

    public function test_customers_can_book_three_months_ahead(): void
    {
        $date = Carbon::now()->addDays(85)->toDateString();
        $service = $this->makeService(60);
        $this->seedOpenDay($date);

        $this->getJson("/booking/slots?service_id={$service->id}&date={$date}")->assertOk()->assertJsonCount(9);
        $this->postJson(route('booking.store'), $this->payload($service, $date))->assertOk();

        // Beyond the 90-day default window is rejected.
        $tooFar = Carbon::now()->addDays(120)->toDateString();
        $this->seedOpenDay($tooFar);
        $this->postJson(route('booking.store'), $this->payload($service, $tooFar, ['phone' => '0779999999']))->assertStatus(422);

        // The booking page lists every day in the window for the strip and calendar.
        $this->get('/booking')->assertSee('data-date="' . Carbon::now()->addDays(89)->toDateString() . '"', false);
    }

    public function test_slots_endpoint_returns_empty_for_past_dates(): void
    {
        $date    = Carbon::now()->subWeek()->toDateString();
        $service = $this->makeService();
        $this->seedOpenDay($date);

        $this->getJson("/booking/slots?service_id={$service->id}&date={$date}")->assertOk()->assertExactJson([]);
    }

    public function test_slots_endpoint_returns_empty_on_closure_day(): void
    {
        $date    = $this->nextMonday();
        $service = $this->makeService();
        $this->seedOpenDay($date);
        DayOff::create(['off_date' => $date, 'reason' => 'Holiday']);

        $this->getJson("/booking/slots?service_id={$service->id}&date={$date}")->assertOk()->assertExactJson([]);
    }

    // ── Booking ─────────────────────────────────────────────────────

    public function test_successful_booking_creates_appointment(): void
    {
        $service = $this->makeService(60);
        $date    = $this->nextMonday();
        $this->seedOpenDay($date);

        $response = $this->postJson(route('booking.store'), $this->payload($service, $date));

        $response->assertOk()->assertJson(['success' => true, 'status' => 'pending']);
        $this->assertNotNull($response->json('appointment_no'));

        $this->assertDatabaseHas('customers', ['phone' => '0771234567', 'name' => 'Nadeesha Silva']);
        $this->assertDatabaseHas('appointments', [
            'slot_start'       => '10:00:00',
            'slot_end'         => '11:00:00',
            'appointment_date' => $date,
            'status'           => 'pending',
            'type'             => 'online',
        ]);
    }

    public function test_end_time_is_computed_on_the_server(): void
    {
        $service = $this->makeService(30);
        $date    = $this->nextMonday();
        $this->seedOpenDay($date);

        // A tampered end_time must be ignored.
        $this->postJson(route('booking.store'), $this->payload($service, $date, ['end_time' => '17:00']))->assertOk();

        $this->assertDatabaseHas('appointments', ['slot_start' => '10:00:00', 'slot_end' => '10:30:00']);
    }

    public function test_booking_fails_when_name_is_missing(): void
    {
        $service = $this->makeService();
        $this->postJson(route('booking.store'), $this->payload($service, $this->nextMonday(), ['name' => '']))->assertStatus(422);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_booking_fails_when_phone_is_invalid(): void
    {
        $service = $this->makeService();
        $this->postJson(route('booking.store'), $this->payload($service, $this->nextMonday(), ['phone' => 'call me']))->assertStatus(422);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_booking_rejects_past_dates(): void
    {
        $service = $this->makeService();
        $date    = Carbon::now()->subDays(3)->toDateString();
        $this->seedOpenDay($date);

        $this->postJson(route('booking.store'), $this->payload($service, $date))->assertStatus(422);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_booking_rejects_times_outside_business_hours(): void
    {
        $service = $this->makeService(60);
        $date    = $this->nextMonday();
        $this->seedOpenDay($date, '09:00:00', '17:00:00');

        $this->postJson(route('booking.store'), $this->payload($service, $date, ['start_time' => '22:00']))->assertStatus(422);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_addon_only_service_cannot_be_booked_online(): void
    {
        $addon = $this->makeService(10, 'Nail art add-on');
        $addon->update(['is_addon' => true]);
        $date = $this->nextMonday();
        $this->seedOpenDay($date);

        $this->get('/booking')->assertDontSee('Nail art add-on');
        $this->getJson("/booking/slots?service_id={$addon->id}&date={$date}")->assertExactJson([]);
        $this->postJson(route('booking.store'), $this->payload($addon, $date))->assertStatus(422);
        $this->get('/services')->assertSee('Add-ons &amp; extras', false)->assertSee('Nail art add-on');
    }

    public function test_booking_rejects_inactive_service(): void
    {
        $service = $this->makeService(60, 'Hidden', false);
        $date    = $this->nextMonday();
        $this->seedOpenDay($date);

        $this->postJson(route('booking.store'), $this->payload($service, $date))->assertStatus(422);
    }

    public function test_booking_with_staff_assigns_staff_to_appointment(): void
    {
        $service = $this->makeService(60);
        $staff   = $this->makeStaff('Priya');
        $date    = $this->nextMonday();
        $this->seedOpenDay($date);

        $this->postJson(route('booking.store'), $this->payload($service, $date, ['start_time' => '09:00', 'staff_id' => $staff->id]))
            ->assertOk()->assertJson(['success' => true, 'stylist' => 'Priya']);

        $this->assertDatabaseHas('appointments', ['staff_id' => $staff->id, 'appointment_date' => $date]);
    }

    public function test_any_stylist_booking_is_auto_assigned(): void
    {
        $service = $this->makeService(60);
        $a = $this->makeStaff('Alpha');
        $b = $this->makeStaff('Beta');
        $date = $this->nextMonday();
        $this->seedOpenDay($date);

        $this->postJson(route('booking.store'), $this->payload($service, $date, ['phone' => '0770000001']))->assertOk();
        $this->postJson(route('booking.store'), $this->payload($service, $date, ['phone' => '0770000002']))->assertOk();

        // Both stylists get one booking each at 10:00 — no unassigned rows.
        $this->assertDatabaseMissing('appointments', ['staff_id' => null]);
        $this->assertEquals(1, Appointment::where('staff_id', $a->id)->count());
        $this->assertEquals(1, Appointment::where('staff_id', $b->id)->count());

        // Third booking at the same time must fail: both are busy.
        $this->postJson(route('booking.store'), $this->payload($service, $date, ['phone' => '0770000003']))->assertStatus(422);
    }

    public function test_double_booking_same_staff_returns_error(): void
    {
        $service = $this->makeService(60);
        $staff   = $this->makeStaff('Sara');
        $date    = $this->nextMonday();
        $this->seedOpenDay($date);

        $customer = Customer::create(['name' => 'First', 'phone' => '0760000001']);
        Appointment::create([
            'appointment_no'   => 'FIRST001',
            'customer_id'      => $customer->id,
            'service_id'       => $service->id,
            'staff_id'         => $staff->id,
            'appointment_date' => $date,
            'slot_start'       => '10:00:00',
            'slot_end'         => '11:00:00',
            'type'             => 'online',
            'status'           => 'confirmed',
        ]);

        $this->postJson(route('booking.store'), $this->payload($service, $date, ['phone' => '0760000002', 'staff_id' => $staff->id]))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Sorry, that stylist is no longer available at this time. Please choose another slot.');
    }

    public function test_customer_cannot_double_book_overlapping_times(): void
    {
        $service = $this->makeService(60);
        $this->makeStaff('Alpha');
        $this->makeStaff('Beta');
        $date = $this->nextMonday();
        $this->seedOpenDay($date);

        // Same phone, same time — a different stylist is free but the customer isn't.
        $this->postJson(route('booking.store'), $this->payload($service, $date))->assertOk();
        $this->postJson(route('booking.store'), $this->payload($service, $date, ['phone' => '0771234567']))
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'This customer already has an appointment from 10:00 AM to 11:00 AM on this day. Please choose a time that doesn’t overlap.']);

        $this->assertEquals(1, Appointment::count());
    }

    public function test_adjacent_slot_is_not_treated_as_conflict(): void
    {
        $service = $this->makeService(60);
        $staff   = $this->makeStaff('Sara');
        $date    = $this->nextMonday();
        $this->seedOpenDay($date);

        $this->postJson(route('booking.store'), $this->payload($service, $date, ['start_time' => '09:00', 'staff_id' => $staff->id]))->assertOk();
        $this->postJson(route('booking.store'), $this->payload($service, $date, ['phone' => '0779999999', 'start_time' => '10:00', 'staff_id' => $staff->id]))->assertOk();

        $this->assertEquals(2, Appointment::count());
    }

    // ── Manage booking ──────────────────────────────────────────────

    private function bookedAppointment(string $date, string $start = '10:00:00'): Appointment
    {
        $service  = $this->makeService(60);
        $customer = Customer::create(['name' => 'Kamal', 'phone' => '0771234567']);

        return Appointment::create([
            'appointment_no'   => 'MANAGE01',
            'customer_id'      => $customer->id,
            'service_id'       => $service->id,
            'appointment_date' => $date,
            'slot_start'       => $start,
            'slot_end'         => '11:00:00',
            'type'             => 'online',
            'status'           => 'pending',
        ]);
    }

    public function test_customer_can_look_up_booking_with_reference_and_phone(): void
    {
        $this->bookedAppointment($this->nextMonday());

        $this->post(route('booking.lookup'), ['reference' => 'manage01', 'phone' => '077 123 4567'])
            ->assertRedirect(route('booking.manage'));

        $this->get(route('booking.manage'))
            ->assertOk()->assertSee('MANAGE01')->assertSee('Cancel booking')->assertSee('Change booking');
    }

    public function test_manage_page_forgets_booking_on_request(): void
    {
        $this->bookedAppointment($this->nextMonday());
        $this->post(route('booking.lookup'), ['reference' => 'MANAGE01', 'phone' => '0771234567']);

        $this->post(route('booking.forget'))->assertRedirect(route('booking.manage'));
        $this->get(route('booking.manage'))->assertDontSee('MANAGE01')->assertSee('Find my booking');
    }

    public function test_lookup_with_wrong_phone_reveals_nothing(): void
    {
        $this->bookedAppointment($this->nextMonday());

        $this->post(route('booking.lookup'), ['reference' => 'MANAGE01', 'phone' => '0710000000'])
            ->assertRedirect()->assertSessionHasErrors('reference');
    }

    public function test_customer_can_cancel_future_booking(): void
    {
        $appt = $this->bookedAppointment($this->nextMonday());

        $this->post(route('booking.cancel'), ['reference' => 'MANAGE01', 'phone' => '0771234567'])
            ->assertRedirect(route('booking.manage'))->assertSessionHas('success');

        $this->assertEquals('cancelled', $appt->fresh()->status);
        $this->assertEquals('customer', $appt->fresh()->cancelled_by);
    }

    public function test_customer_cannot_cancel_inside_cutoff(): void
    {
        $soon = now()->addHour();
        $appt = $this->bookedAppointment($soon->toDateString(), $soon->format('H:i:00'));

        $this->post(route('booking.cancel'), ['reference' => 'MANAGE01', 'phone' => '0771234567'])
            ->assertRedirect(route('booking.manage'))->assertSessionHas('error');

        $this->assertEquals('pending', $appt->fresh()->status);
    }

    // ── Change booking ──────────────────────────────────────────────

    /** A verified, changeable booking with Amaya at 10:00 on an open Monday. */
    private function changeableBooking(): array
    {
        $date = $this->nextMonday();
        $this->seedOpenDay($date);
        $amaya = $this->makeStaff('Amaya');
        $priya = $this->makeStaff('Priya');
        $appt  = $this->bookedAppointment($date);
        $appt->update(['staff_id' => $amaya->id, 'status' => 'confirmed']);

        $this->post(route('booking.lookup'), ['reference' => 'MANAGE01', 'phone' => '0771234567']);

        return [$appt->fresh(), $date, $amaya, $priya];
    }

    public function test_change_page_opens_in_change_mode(): void
    {
        $this->changeableBooking();

        $this->get(route('booking.edit'))->assertOk()
            ->assertSee('Change your booking')->assertSee('"mode":"edit"', false)->assertSee('Confirm change');
    }

    public function test_change_page_requires_verified_booking(): void
    {
        $this->get(route('booking.edit'))->assertRedirect(route('booking.manage'));
        $this->postJson(route('booking.update'), [])->assertStatus(401);
    }

    public function test_customer_can_change_stylist_and_time(): void
    {
        [$appt, $date, , $priya] = $this->changeableBooking();

        $this->postJson(route('booking.update'), [
            'service_id' => $appt->service_id, 'date' => $date, 'start_time' => '13:00', 'staff_id' => $priya->id,
        ])->assertOk()->assertJson(['success' => true, 'stylist' => 'Priya', 'start' => '13:00', 'status' => 'pending']);

        $appt->refresh();
        $this->assertEquals($priya->id, $appt->staff_id);
        $this->assertEquals('13:00:00', $appt->slot_start);
        $this->assertEquals('14:00:00', $appt->slot_end);
        $this->assertEquals(1, $appt->reschedule_count);
        $this->assertEquals('customer', $appt->rescheduled_by);
        $this->assertEquals(1, Appointment::count());
    }

    public function test_customer_can_change_service_and_keeps_stylist(): void
    {
        [$appt, $date, $amaya] = $this->changeableBooking();
        $longer = $this->makeService(90, 'Colour');

        // 10:30–12:00 overlaps the booking's own 10:00–11:00, which must not block it.
        $this->postJson(route('booking.update'), [
            'service_id' => $longer->id, 'date' => $date, 'start_time' => '10:30', 'staff_id' => null,
        ])->assertOk();

        $appt->refresh();
        $this->assertEquals($longer->id, $appt->service_id);
        $this->assertEquals($amaya->id, $appt->staff_id);
        $this->assertEquals('12:00:00', $appt->slot_end);
    }

    public function test_current_slot_is_offered_only_when_editing(): void
    {
        [$appt, $date, $amaya] = $this->changeableBooking();
        $url = "/booking/slots?service_id={$appt->service_id}&date={$date}&staff_id={$amaya->id}";

        $starts = fn ($res) => array_column($res->json(), 'start');

        $this->assertNotContains('10:00', $starts($this->getJson($url)));
        $this->assertContains('10:00', $starts($this->getJson($url . '&editing=1')));
    }

    public function test_change_rejects_unchanged_booking(): void
    {
        [$appt, $date, $amaya] = $this->changeableBooking();

        $this->postJson(route('booking.update'), [
            'service_id' => $appt->service_id, 'date' => $date, 'start_time' => '10:00', 'staff_id' => $amaya->id,
        ])->assertStatus(422)->assertJsonFragment(['error' => 'Nothing changed — choose a different service, stylist or time.']);
    }

    public function test_change_cannot_take_a_booked_stylist_slot(): void
    {
        [$appt, $date, , $priya] = $this->changeableBooking();
        $other = Customer::create(['name' => 'Other', 'phone' => '0779990000']);
        Appointment::create([
            'appointment_no' => 'OTHER001', 'customer_id' => $other->id, 'service_id' => $appt->service_id, 'staff_id' => $priya->id,
            'appointment_date' => $date, 'slot_start' => '13:00:00', 'slot_end' => '14:00:00', 'type' => 'online', 'status' => 'confirmed',
        ]);

        $this->postJson(route('booking.update'), [
            'service_id' => $appt->service_id, 'date' => $date, 'start_time' => '13:00', 'staff_id' => $priya->id,
        ])->assertStatus(422);

        $this->assertEquals('10:00:00', $appt->fresh()->slot_start);
    }

    public function test_customer_change_limit_is_enforced(): void
    {
        [$appt, $date, , $priya] = $this->changeableBooking();
        $appt->update(['reschedule_count' => Appointment::MAX_CUSTOMER_CHANGES]);

        $this->get(route('booking.edit'))->assertRedirect(route('booking.manage'))->assertSessionHas('error');
        $this->postJson(route('booking.update'), [
            'service_id' => $appt->service_id, 'date' => $date, 'start_time' => '13:00', 'staff_id' => $priya->id,
        ])->assertStatus(422);
    }

    public function test_change_blocked_inside_cutoff(): void
    {
        $soon = now()->addHour();
        $appt = $this->bookedAppointment($soon->toDateString(), $soon->format('H:i:00'));
        $this->post(route('booking.lookup'), ['reference' => 'MANAGE01', 'phone' => '0771234567']);

        $this->get(route('booking.edit'))->assertRedirect(route('booking.manage'));
        $this->get(route('booking.manage'))->assertDontSee('Change booking');
        $this->assertEquals(0, $appt->fresh()->reschedule_count);
    }

    // ── Feedback ────────────────────────────────────────────────────

    private function completedVisit(): Appointment
    {
        $appt = $this->bookedAppointment(now()->subDays(2)->toDateString());
        $appt->update(['status' => 'completed', 'staff_id' => $this->makeStaff()->id]);
        $this->post(route('booking.lookup'), ['reference' => 'MANAGE01', 'phone' => '0771234567']);

        return $appt;
    }

    public function test_customer_can_leave_feedback_after_completed_visit(): void
    {
        $appt = $this->completedVisit();

        $this->get(route('booking.manage'))->assertSee('How was your visit?');

        $this->post(route('booking.feedback'), ['rating' => 5, 'comment' => 'Lovely cut!', 'allow_public' => '1'])
            ->assertRedirect(route('booking.manage'))->assertSessionHas('success');

        $fb = \App\Models\Feedback::first();
        $this->assertEquals(5, $fb->rating);
        $this->assertEquals($appt->staff_id, $fb->staff_id);
        $this->assertTrue($fb->allow_public);
        $this->assertFalse($fb->is_public); // needs admin approval

        $this->get(route('booking.manage'))->assertSee('Your review')->assertSee('Lovely cut!')->assertDontSee('How was your visit?');
    }

    public function test_feedback_only_once_per_visit(): void
    {
        $this->completedVisit();

        $this->post(route('booking.feedback'), ['rating' => 4]);
        $this->post(route('booking.feedback'), ['rating' => 1])->assertSessionHas('error');

        $this->assertEquals(1, \App\Models\Feedback::count());
        $this->assertEquals(4, \App\Models\Feedback::first()->rating);
    }

    public function test_feedback_requires_a_rating(): void
    {
        $this->completedVisit();

        $this->post(route('booking.feedback'), ['rating' => 9])->assertSessionHasErrors('rating', null, 'feedback');
        $this->post(route('booking.feedback'), ['comment' => 'No stars'])->assertSessionHasErrors('rating', null, 'feedback');
        $this->assertEquals(0, \App\Models\Feedback::count());
    }

    public function test_feedback_not_allowed_before_visit_is_completed(): void
    {
        $this->bookedAppointment($this->nextMonday());
        $this->post(route('booking.lookup'), ['reference' => 'MANAGE01', 'phone' => '0771234567']);

        $this->get(route('booking.manage'))->assertDontSee('How was your visit?');
        $this->post(route('booking.feedback'), ['rating' => 5])->assertSessionHas('error');
        $this->assertEquals(0, \App\Models\Feedback::count());
    }

    public function test_feedback_requires_verified_booking(): void
    {
        $this->completedVisit();
        $this->post(route('booking.forget'));

        $this->post(route('booking.feedback'), ['rating' => 5])->assertRedirect(route('booking.manage'));
        $this->assertEquals(0, \App\Models\Feedback::count());
    }

    public function test_home_page_shows_only_published_reviews(): void
    {
        $appt = $this->completedVisit();
        $base = ['appointment_id' => $appt->id, 'customer_id' => $appt->customer_id, 'service_id' => $appt->service_id, 'rating' => 5];

        \App\Models\Feedback::create($base + ['comment' => 'Shown review text', 'allow_public' => true, 'is_public' => true]);

        $other = $appt->replicate()->fill(['appointment_no' => 'MANAGE02', 'appointment_date' => now()->subDays(3)->toDateString()]);
        $other->save();
        \App\Models\Feedback::create(array_merge($base, ['appointment_id' => $other->id, 'comment' => 'Hidden review text', 'allow_public' => true, 'is_public' => false]));

        $this->get('/')->assertOk()->assertSee('What our clients say')->assertSee('Shown review text')
            ->assertSee('Kamal')->assertDontSee('Hidden review text');
    }
}
