<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\DayOff;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin panel, stylist portal and cashier station.
 */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $email = 'admin@salon.com', string $password = 'secret123'): Admin
    {
        return Admin::create(['is_owner' => true, 'name' => 'Test Admin', 'email' => $email, 'password' => Hash::make($password)]);
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs($this->makeAdmin(), 'admin');
    }

    private function makeCategory(string $name = 'Hair'): ServiceCategory
    {
        return ServiceCategory::create(['name' => $name, 'slug' => Str::slug($name . uniqid())]);
    }

    private function makeService(?ServiceCategory $cat = null, string $name = 'Haircut', int $duration = 30): Service
    {
        return Service::create([
            'service_category_id' => $cat?->id,
            'name'                => $name,
            'slug'                => Str::slug($name . uniqid()),
            'price'               => 1500,
            'duration_minutes'    => $duration,
            'is_active'           => true,
        ]);
    }

    private function makeStaff(string $name = 'Amaya', array $extra = []): Staff
    {
        return Staff::create(array_merge(['name' => $name, 'is_active' => true], $extra));
    }

    private function makeAppointment(Service $service, ?string $date = null, array $extra = []): Appointment
    {
        $customer = Customer::create(['name' => 'Walk-in Wendy', 'phone' => '0760000' . rand(100, 999)]);

        return Appointment::create(array_merge([
            'appointment_no'   => strtoupper(Str::random(8)),
            'customer_id'      => $customer->id,
            'service_id'       => $service->id,
            'appointment_date' => $date ?? now()->addDays(2)->toDateString(),
            'slot_start'       => '10:00:00',
            'slot_end'         => '10:30:00',
            'type'             => 'counter',
            'status'           => 'pending',
        ], $extra));
    }

    // ── Auth ──────────────────────────────────────────────────────────

    public function test_admin_login_page_loads(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('name="email"', false)->assertSee('name="password"', false);
    }

    public function test_admin_login_with_valid_credentials(): void
    {
        $this->makeAdmin('mgr@salon.com', 'pass1234');

        $this->post('/admin/login', ['email' => 'mgr@salon.com', 'password' => 'pass1234'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs(Admin::first(), 'admin');
    }

    public function test_admin_login_with_wrong_password_shows_error(): void
    {
        $this->makeAdmin('mgr@salon.com', 'correct1');

        $this->post('/admin/login', ['email' => 'mgr@salon.com', 'password' => 'wrong'])
            ->assertRedirect()->assertSessionHas('error');
        $this->assertGuest('admin');
    }

    public function test_admin_login_is_rate_limited(): void
    {
        $this->makeAdmin('mgr@salon.com', 'correct1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'mgr@salon.com', 'password' => 'wrong']);
        }

        $this->post('/admin/login', ['email' => 'mgr@salon.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_logout_requires_post_and_redirects(): void
    {
        $this->actingAsAdmin()->get('/admin/logout')->assertStatus(405);
        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest('admin');
    }

    // ── Pages ─────────────────────────────────────────────────────────

    public function test_admin_dashboard_loads(): void
    {
        $this->makeAppointment($this->makeService(), now()->toDateString());

        $this->actingAsAdmin()->get('/admin')->assertOk()->assertSee('Dashboard')->assertSee("Today's schedule", false);
    }

    public function test_admin_pages_render(): void
    {
        $service = $this->makeService($this->makeCategory());
        $appt = $this->makeAppointment($service);
        $staff = $this->makeStaff();

        $this->actingAsAdmin();

        foreach ([
            '/admin/schedule', '/admin/appointments', '/admin/appointments/create', "/admin/appointments/{$appt->id}",
            '/admin/customers', "/admin/customers/{$appt->customer_id}", "/admin/customers/{$appt->customer_id}/edit",
            '/admin/services', '/admin/services/create', "/admin/services/{$service->id}/edit",
            '/admin/categories', '/admin/categories/create',
            '/admin/employees', '/admin/employees/create', "/admin/employees/{$staff->id}/edit",
            '/admin/hours', '/admin/settings', '/admin/sliders', '/admin/sliders/create', '/admin/account',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_admin_can_view_appointments_list(): void
    {
        $this->makeAppointment($this->makeService());

        $this->actingAsAdmin()->get('/admin/appointments')->assertOk()->assertSee('Walk-in Wendy');
    }

    public function test_admin_can_view_customer_list(): void
    {
        Customer::create(['name' => 'Dilini Perera', 'phone' => '0771112222']);

        $this->actingAsAdmin()->get('/admin/customers')->assertOk()->assertSee('Dilini Perera');
    }

    public function test_appointments_export_returns_csv(): void
    {
        $this->makeAppointment($this->makeService());

        $response = $this->actingAsAdmin()->get('/admin/appointments/export?scope=all');
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Walk-in Wendy', $response->streamedContent());
    }

    // ── Catalog ───────────────────────────────────────────────────────

    public function test_admin_can_create_service_category(): void
    {
        $this->actingAsAdmin()->post('/admin/categories', ['name' => 'Nails'])->assertRedirect();
        $this->assertDatabaseHas('service_categories', ['name' => 'Nails']);
    }

    public function test_deleting_category_keeps_its_services(): void
    {
        $cat = $this->makeCategory('Skin');
        $service = $this->makeService($cat, 'Facial');

        $this->actingAsAdmin()->delete("/admin/categories/{$cat->id}")->assertRedirect();

        $this->assertDatabaseMissing('service_categories', ['id' => $cat->id]);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'service_category_id' => null]);
    }

    public function test_admin_can_create_service(): void
    {
        $cat = $this->makeCategory('Skin');

        $this->actingAsAdmin()->post('/admin/services', [
            'service_category_id' => $cat->id,
            'name'                => 'Facial Glow',
            'price'               => 2500,
            'duration_minutes'    => 45,
            'is_active'           => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('services', ['name' => 'Facial Glow', 'price' => 2500, 'is_active' => 1]);
    }

    public function test_services_with_same_name_get_unique_slugs(): void
    {
        $this->actingAsAdmin();
        $this->post('/admin/services', ['name' => 'Trim', 'price' => 100, 'duration_minutes' => 15, 'is_active' => 1]);
        $this->post('/admin/services', ['name' => 'Trim', 'price' => 200, 'duration_minutes' => 15, 'is_active' => 1]);

        $this->assertEquals(2, Service::where('name', 'Trim')->count());
    }

    public function test_admin_can_delete_unused_service(): void
    {
        $service = $this->makeService(null, 'Old Service');

        $this->actingAsAdmin()->delete("/admin/services/{$service->id}")->assertRedirect();
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_service_with_bookings_is_hidden_not_deleted(): void
    {
        $service = $this->makeService(null, 'Popular');
        $appt = $this->makeAppointment($service);

        $this->actingAsAdmin()->delete("/admin/services/{$service->id}")->assertRedirect();

        $this->assertDatabaseHas('services', ['id' => $service->id, 'is_active' => 0]);
        $this->assertDatabaseHas('appointments', ['id' => $appt->id]);
    }

    public function test_admin_cannot_create_service_with_invalid_price(): void
    {
        $this->actingAsAdmin()->post('/admin/services', [
            'name' => 'Bad Service', 'price' => -999, 'duration_minutes' => 30, 'is_active' => 1,
        ]);
        $this->assertDatabaseMissing('services', ['name' => 'Bad Service']);
    }

    // ── Staff ─────────────────────────────────────────────────────────

    public function test_admin_can_create_employee_with_work_hours(): void
    {
        $this->actingAsAdmin()->post('/admin/employees', [
            'name' => 'Sachini Fernando', 'phone' => '0779876543', 'email' => 'sachini@salon.com',
            'work_start' => '09:00', 'work_end' => '17:00', 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('staff', ['name' => 'Sachini Fernando', 'work_start' => '09:00:00', 'work_end' => '17:00:00']);
        $this->assertNotNull(Staff::where('name', 'Sachini Fernando')->value('table_qr_token'));
    }

    public function test_admin_can_update_employee_work_hours(): void
    {
        $staff = $this->makeStaff('Kasuni');

        $this->actingAsAdmin()->put("/admin/employees/{$staff->id}", [
            'name' => 'Kasuni', 'work_start' => '10:00', 'work_end' => '18:00', 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'work_start' => '10:00:00', 'work_end' => '18:00:00']);
    }

    public function test_employee_with_history_is_deactivated_not_deleted(): void
    {
        $staff = $this->makeStaff('Veteran');
        $this->makeAppointment($this->makeService(), null, ['staff_id' => $staff->id]);

        $this->actingAsAdmin()->delete("/admin/employees/{$staff->id}")->assertRedirect();

        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'is_active' => 0]);
    }

    public function test_admin_can_set_stylist_login(): void
    {
        $staff = $this->makeStaff('Nimal');

        $this->actingAsAdmin()->post("/admin/employees/{$staff->id}/set-login", [
            'login_username' => 'nimal.s', 'password' => 'longpass1', 'password_confirmation' => 'longpass1',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('longpass1', $staff->fresh()->password));
    }

    // ── Appointments ──────────────────────────────────────────────────

    public function test_admin_can_update_appointment_status(): void
    {
        $appointment = $this->makeAppointment($this->makeService());

        $this->actingAsAdmin()->post(route('admin.appointments.status', $appointment), ['status' => 'confirmed'])->assertRedirect();
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed']);
    }

    public function test_in_progress_status_can_be_saved(): void
    {
        $appointment = $this->makeAppointment($this->makeService());

        $this->actingAsAdmin()->post(route('admin.appointments.status', $appointment), ['status' => 'in_progress']);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'in_progress']);
        $this->assertNotNull($appointment->fresh()->started_at);
    }

    public function test_completing_appointment_generates_job_number(): void
    {
        $appointment = $this->makeAppointment($this->makeService());

        $this->actingAsAdmin()->post(route('admin.appointments.status', $appointment), ['status' => 'completed']);
        $this->assertNotNull($appointment->fresh()->job_number);
    }

    public function test_admin_can_create_phone_booking(): void
    {
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        BusinessHour::create(['day_of_week' => 1, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'is_closed' => false]);
        $service = $this->makeService(null, 'Colour', 60);
        $staff = $this->makeStaff('Priya');

        $this->actingAsAdmin()->post('/admin/appointments', [
            'name' => 'Phone Caller', 'phone' => '0712345678', 'email' => 'caller@example.com', 'service_id' => $service->id,
            'date' => $date, 'start_time' => '11:00', 'staff_id' => $staff->id, 'type' => 'phone',
        ])->assertRedirect();

        $this->assertDatabaseHas('appointments', [
            'staff_id' => $staff->id, 'slot_start' => '11:00:00', 'slot_end' => '12:00:00', 'status' => 'confirmed', 'type' => 'phone',
        ]);
    }

    public function test_reassigning_to_busy_stylist_is_blocked(): void
    {
        $service = $this->makeService();
        $busy = $this->makeStaff('Busy');
        $this->makeAppointment($service, null, ['staff_id' => $busy->id]);
        $other = $this->makeAppointment($service);

        $this->actingAsAdmin()->put("/admin/appointments/{$other->id}", ['staff_id' => $busy->id])->assertSessionHas('error');
        $this->assertNull($other->fresh()->staff_id);
    }

    public function test_reopening_cancelled_booking_blocked_if_time_was_rebooked(): void
    {
        $service = $this->makeService();
        $staff = $this->makeStaff('Busy');
        $cancelled = $this->makeAppointment($service, null, ['staff_id' => $staff->id, 'status' => 'cancelled']);
        $this->makeAppointment($service, null, ['staff_id' => $staff->id, 'status' => 'confirmed']); // took the slot

        $this->actingAsAdmin()->post(route('admin.appointments.status', $cancelled), ['status' => 'confirmed'])
            ->assertSessionHas('error');
        $this->assertEquals('cancelled', $cancelled->fresh()->status);
    }

    public function test_reopening_cancelled_booking_allowed_when_time_is_free(): void
    {
        $cancelled = $this->makeAppointment($this->makeService(), null, ['staff_id' => $this->makeStaff()->id, 'status' => 'cancelled', 'cancelled_at' => now()]);

        $this->actingAsAdmin()->post(route('admin.appointments.status', $cancelled), ['status' => 'confirmed'])->assertSessionHas('success');
        $this->assertEquals('confirmed', $cancelled->fresh()->status);
        $this->assertNull($cancelled->fresh()->cancelled_at);
    }

    public function test_stylist_cannot_take_unassigned_booking_that_clashes(): void
    {
        $me = $this->makeStaff('Me');
        $service = $this->makeService();
        $this->makeAppointment($service, now()->toDateString(), ['staff_id' => $me->id, 'status' => 'confirmed']);
        $open = $this->makeAppointment($service, now()->toDateString(), ['staff_id' => null, 'status' => 'confirmed']);

        $this->actingAs($me, 'staff')->postJson('/stylist/scan-customer', ['appointment_no' => $open->appointment_no])->assertStatus(422);
        $this->assertNull($open->fresh()->staff_id);
    }

    public function test_adding_closure_warns_about_existing_bookings(): void
    {
        $date = now()->addDays(2)->toDateString();
        $this->makeAppointment($this->makeService(), $date, ['status' => 'confirmed']);

        $this->actingAsAdmin()->post('/admin/hours/closures', ['off_date' => $date])->assertSessionHas('error');
        $this->assertTrue(DayOff::whereDate('off_date', $date)->exists());
    }

    // ── Business settings ─────────────────────────────────────────────

    public function test_admin_can_update_opening_hours(): void
    {
        $days = [];
        for ($d = 0; $d <= 6; $d++) {
            $days[$d] = ['open' => $d === 0 ? '0' : '1', 'from' => '08:00', 'to' => '16:00'];
        }

        $this->actingAsAdmin()->put('/admin/hours', ['days' => $days, 'buffer_minutes' => '0'])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('business_hours', ['day_of_week' => 2, 'open_time' => '08:00:00', 'is_closed' => 0]);
        $this->assertDatabaseHas('business_hours', ['day_of_week' => 0, 'is_closed' => 1]);
    }

    public function test_admin_can_set_lunch_break_and_cleanup_time(): void
    {
        $days = [];
        for ($d = 0; $d <= 6; $d++) {
            $days[$d] = ['open' => '1', 'from' => '09:00', 'to' => '18:00', 'break_from' => '13:00', 'break_to' => '14:00'];
        }

        $this->actingAsAdmin()->put('/admin/hours', ['days' => $days, 'buffer_minutes' => '10'])->assertSessionHas('success');

        $this->assertDatabaseHas('business_hours', ['day_of_week' => 3, 'break_start' => '13:00:00', 'break_end' => '14:00:00']);
        $this->assertDatabaseHas('settings', ['key' => 'buffer_minutes', 'value' => '10']);
    }

    public function test_lunch_break_outside_opening_hours_is_rejected(): void
    {
        $days = [];
        for ($d = 0; $d <= 6; $d++) {
            $days[$d] = ['open' => '1', 'from' => '09:00', 'to' => '18:00', 'break_from' => '19:00', 'break_to' => '20:00'];
        }

        $this->actingAsAdmin()->put('/admin/hours', ['days' => $days, 'buffer_minutes' => '0'])->assertSessionHasErrors();
        $this->assertDatabaseMissing('business_hours', ['break_start' => '19:00:00']);
    }

    public function test_staff_break_saved_and_validated(): void
    {
        $staff = $this->makeStaff('Kasuni');
        $this->actingAsAdmin();

        $this->put("/admin/employees/{$staff->id}", ['name' => 'Kasuni', 'is_active' => 1, 'break_start' => '12:00', 'break_end' => '12:30'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('staff', ['id' => $staff->id, 'break_start' => '12:00:00', 'break_end' => '12:30:00']);

        $this->put("/admin/employees/{$staff->id}", ['name' => 'Kasuni', 'is_active' => 1, 'break_start' => '12:00'])
            ->assertSessionHasErrors('break_end');
    }

    public function test_service_cleanup_time_saved(): void
    {
        $this->actingAsAdmin()->post('/admin/services', [
            'name' => 'Full colour', 'price' => 8500, 'duration_minutes' => 120, 'buffer_minutes' => 30, 'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('services', ['name' => 'Full colour', 'buffer_minutes' => 30]);
    }

    public function test_admin_can_add_closure(): void
    {
        $date = now()->addDays(5)->toDateString();
        $this->actingAsAdmin()->post('/admin/hours/closures', ['off_date' => $date, 'reason' => 'Holiday'])->assertRedirect();
        $this->assertTrue(DayOff::whereDate('off_date', $date)->exists());
    }

    public function test_settings_only_store_whitelisted_keys(): void
    {
        $this->actingAsAdmin()->put('/admin/settings', ['settings' => [
            'salon_name' => 'Glow Studio', 'currency' => 'LKR', 'max_advance_days' => 14, 'min_notice_minutes' => 60,
            'cancellation_hours' => 4, 'slot_step' => '15', 'max_parallel_bookings' => 2, 'auto_confirm' => '1',
            'evil_key' => 'should not be saved',
        ]])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('settings', ['key' => 'salon_name', 'value' => 'Glow Studio']);
        $this->assertDatabaseMissing('settings', ['key' => 'evil_key']);
    }

    public function test_slider_rejects_javascript_links(): void
    {
        $this->actingAsAdmin()->post('/admin/sliders', ['title' => 'Hi', 'button_link' => 'javascript:alert(1)', 'is_active' => 1])
            ->assertSessionHasErrors('button_link');
        $this->assertDatabaseMissing('sliders', ['title' => 'Hi']);
    }

    public function test_slider_can_be_created_without_image(): void
    {
        $this->actingAsAdmin()->post('/admin/sliders', ['title' => 'Summer offers', 'button_link' => '/booking', 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('sliders', ['title' => 'Summer offers']);
    }

    public function test_admin_can_change_password_with_current_password(): void
    {
        $admin = $this->makeAdmin('me@salon.com', 'oldpass12');

        $this->actingAs($admin, 'admin')->put('/admin/account', [
            'name' => 'Me', 'email' => 'me@salon.com', 'current_password' => 'oldpass12',
            'password' => 'newpass34', 'password_confirmation' => 'newpass34',
        ])->assertSessionHas('success');

        $this->assertTrue(Hash::check('newpass34', $admin->fresh()->password));
    }

    // ── Cashier ───────────────────────────────────────────────────────

    public function test_cashier_requires_admin_or_pin(): void
    {
        $this->get('/cashier')->assertRedirect('/cashier/unlock');
        $this->postJson('/cashier/lookup', ['ref' => 'X'])->assertStatus(401);
    }

    public function test_cashier_pin_unlocks_station(): void
    {
        Setting::create(['key' => 'cashier_pin_hash', 'value' => Hash::make('4321')]);

        $this->post('/cashier/unlock', ['pin' => '0000'])->assertSessionHas('error');
        $this->post('/cashier/unlock', ['pin' => '4321'])->assertRedirect('/cashier');
        $this->get('/cashier')->assertOk();
    }

    public function test_admin_can_take_payment(): void
    {
        $appt = $this->makeAppointment($this->makeService(), now()->toDateString(), ['status' => 'completed', 'job_number' => 'JABCDE']);

        $this->actingAsAdmin();
        $this->postJson('/cashier/lookup', ['ref' => 'jabcde'])->assertOk()->assertJsonPath('appointment.id', $appt->id);
        $this->postJson("/cashier/pay/{$appt->id}", ['payment_method' => 'card'])->assertOk();
        $this->postJson("/cashier/pay/{$appt->id}", ['payment_method' => 'card'])->assertStatus(422);

        $this->assertDatabaseHas('appointments', ['id' => $appt->id, 'payment_status' => 'paid', 'payment_method' => 'card']);
    }

    // ── Extras (add-ons) & walk-ins ───────────────────────────────────

    private function makeAddon(string $name = 'Long hair', float $price = 500): Service
    {
        return Service::create([
            'name' => $name, 'slug' => Str::slug($name . uniqid()), 'price' => $price,
            'duration_minutes' => 5, 'is_active' => true, 'is_addon' => true,
        ]);
    }

    public function test_admin_can_start_walk_in(): void
    {
        $today = now()->dayOfWeek;
        BusinessHour::create(['day_of_week' => $today, 'open_time' => '00:00:00', 'close_time' => '23:59:00', 'is_closed' => false]);
        $service = $this->makeService(null, 'Quick trim', 15);
        $staff = $this->makeStaff('Priya');

        $slot = \App\Services\SlotService::availableSlots(now()->toDateString(), 15, $staff->id, ['step' => 15, 'not_before' => now()->subMinutes(10)])[0] ?? null;
        $this->assertNotNull($slot, 'Expected a free walk-in slot today');

        $this->actingAsAdmin()->post('/admin/appointments', [
            'walk_in' => 1, 'name' => 'Walk In', 'phone' => '0711111111', 'service_id' => $service->id,
            'start_time' => $slot['start'], 'staff_id' => $staff->id, 'type' => 'phone',
        ])->assertRedirect()->assertSessionHas('success');

        $appt = Appointment::first();
        $this->assertTrue($appt->walk_in);
        $this->assertEquals('in_progress', $appt->status);
        $this->assertNotNull($appt->job_number);
        $this->assertEquals(now()->toDateString(), \Carbon\Carbon::parse($appt->appointment_date)->toDateString());
    }

    public function test_admin_can_add_and_remove_custom_extra(): void
    {
        $appt = $this->makeAppointment($this->makeService());

        $this->actingAsAdmin()->post("/admin/appointments/{$appt->id}/items", ['name' => 'Deep conditioning', 'price' => 750, 'quantity' => 2])
            ->assertSessionHas('success');

        $item = $appt->items()->first();
        $this->assertEquals('Deep conditioning', $item->name);
        $this->assertEquals(3000.0, $appt->fresh()->load('items', 'service')->total()); // 1500 + 2 × 750

        $this->delete("/admin/appointments/{$appt->id}/items/{$item->id}")->assertSessionHas('success');
        $this->assertDatabaseMissing('appointment_items', ['id' => $item->id]);
    }

    public function test_adding_same_addon_twice_increases_quantity(): void
    {
        $appt = $this->makeAppointment($this->makeService());
        $addon = $this->makeAddon();

        $this->actingAsAdmin();
        $this->post("/admin/appointments/{$appt->id}/items", ['service_id' => $addon->id]);
        $this->post("/admin/appointments/{$appt->id}/items", ['service_id' => $addon->id]);

        $this->assertEquals(1, $appt->items()->count());
        $this->assertEquals(2, $appt->items()->first()->quantity);
    }

    public function test_stylist_can_add_extra_to_own_job_only(): void
    {
        $me = $this->makeStaff('Me');
        $other = $this->makeStaff('Other');
        $service = $this->makeService();
        $mine = $this->makeAppointment($service, now()->toDateString(), ['staff_id' => $me->id, 'status' => 'in_progress']);
        $theirs = $this->makeAppointment($service, now()->toDateString(), ['staff_id' => $other->id, 'status' => 'in_progress']);
        $addon = $this->makeAddon('Nail art', 400);

        $this->actingAs($me, 'staff');
        $this->postJson("/stylist/appointments/{$mine->id}/items", ['service_id' => $addon->id])
            ->assertOk()->assertJsonPath('total', 1900);
        $this->postJson("/stylist/appointments/{$theirs->id}/items", ['service_id' => $addon->id])->assertStatus(403);
        $this->assertEquals(0, $theirs->items()->count());
    }

    public function test_cashier_bill_includes_extras_and_records_cash(): void
    {
        $appt = $this->makeAppointment($this->makeService(), now()->toDateString(), ['status' => 'completed', 'job_number' => 'JEXTRA']);
        $appt->items()->create(['name' => 'Long hair', 'price' => 500, 'quantity' => 1, 'added_by' => 'stylist']);

        $this->actingAsAdmin();
        $this->postJson('/cashier/lookup', ['ref' => 'JEXTRA'])
            ->assertOk()->assertJsonPath('appointment.total', 2000)->assertJsonCount(1, 'appointment.items');

        // Short cash is rejected.
        $this->postJson("/cashier/pay/{$appt->id}", ['payment_method' => 'cash', 'cash_received' => 1500, 'expected_total' => 2000])->assertStatus(422);

        $this->postJson("/cashier/pay/{$appt->id}", ['payment_method' => 'cash', 'cash_received' => 2500, 'expected_total' => 2000])
            ->assertOk()->assertJsonPath('appointment.change', 500);

        $this->assertDatabaseHas('appointments', ['id' => $appt->id, 'payment_status' => 'paid', 'amount_paid' => 2000, 'cash_received' => 2500]);
    }

    public function test_cashier_payment_stops_when_bill_changed(): void
    {
        $appt = $this->makeAppointment($this->makeService(), now()->toDateString(), ['status' => 'completed']);
        $appt->items()->create(['name' => 'Added meanwhile', 'price' => 300, 'quantity' => 1]);

        $this->actingAsAdmin()->postJson("/cashier/pay/{$appt->id}", ['payment_method' => 'card', 'expected_total' => 1500])
            ->assertStatus(409)->assertJsonPath('appointment.total', 1800);

        $this->assertEquals('unpaid', $appt->fresh()->payment_status);
    }

    public function test_extras_are_locked_after_payment(): void
    {
        $appt = $this->makeAppointment($this->makeService(), now()->toDateString(), ['status' => 'completed', 'payment_status' => 'paid', 'amount_paid' => 1500]);

        $this->actingAsAdmin()->postJson("/cashier/appointments/{$appt->id}/items", ['name' => 'Late extra', 'price' => 100])->assertStatus(422);
        $this->assertEquals(0, $appt->items()->count());
    }

    public function test_removing_item_from_another_appointment_is_404(): void
    {
        $service = $this->makeService();
        $a = $this->makeAppointment($service);
        $b = $this->makeAppointment($service);
        $item = $b->items()->create(['name' => 'X', 'price' => 10, 'quantity' => 1]);

        $this->actingAsAdmin()->delete("/admin/appointments/{$a->id}/items/{$item->id}")->assertNotFound();
        $this->assertDatabaseHas('appointment_items', ['id' => $item->id]);
    }

    // ── Stylist portal ────────────────────────────────────────────────

    public function test_stylist_cannot_complete_another_stylists_job(): void
    {
        $me = $this->makeStaff('Me');
        $other = $this->makeStaff('Other');
        $appt = $this->makeAppointment($this->makeService(), now()->toDateString(), ['staff_id' => $other->id, 'status' => 'in_progress']);

        $this->actingAs($me, 'staff')->postJson("/stylist/complete/{$appt->id}")->assertStatus(403);
        $this->assertEquals('in_progress', $appt->fresh()->status);
    }

    public function test_stylist_can_start_and_complete_own_job(): void
    {
        $me = $this->makeStaff('Me');
        $appt = $this->makeAppointment($this->makeService(), now()->toDateString(), ['staff_id' => $me->id, 'status' => 'confirmed']);

        $this->actingAs($me, 'staff');
        $this->postJson('/stylist/scan-customer', ['appointment_no' => strtolower($appt->appointment_no)])->assertOk();
        $this->assertEquals('in_progress', $appt->fresh()->status);

        $this->postJson("/stylist/complete/{$appt->id}")->assertOk()->assertJsonStructure(['job_number']);
        $this->assertEquals('completed', $appt->fresh()->status);
    }

    public function test_inactive_stylist_is_signed_out(): void
    {
        $me = $this->makeStaff('Gone', ['is_active' => false]);

        $this->actingAs($me, 'staff')->get('/stylist/dashboard')->assertRedirect('/stylist/login');
    }

    public function test_stylist_dashboard_loads(): void
    {
        $me = $this->makeStaff('Me');
        $this->makeAppointment($this->makeService(), now()->toDateString(), ['staff_id' => $me->id]);

        $this->actingAs($me, 'staff')->get('/stylist/dashboard')->assertOk()->assertSee('Walk-in Wendy');
        $this->actingAs($me, 'staff')->get('/stylist/walkin')->assertOk();
    }

    // ── Change booking (admin) ─────────────────────────────────────────

    public function test_admin_can_change_service_stylist_and_time(): void
    {
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        BusinessHour::create(['day_of_week' => 1, 'open_time' => '09:00:00', 'close_time' => '17:00:00', 'is_closed' => false]);
        $amaya = $this->makeStaff('Amaya');
        $priya = $this->makeStaff('Priya');
        $appt  = $this->makeAppointment($this->makeService(), $date, ['staff_id' => $amaya->id, 'status' => 'confirmed', 'reschedule_count' => 3]);
        $colour = $this->makeService(null, 'Colour', 60);

        $this->actingAsAdmin()->get(route('admin.appointments.show', $appt))->assertSee('Change service / time');
        $this->get(route('admin.appointments.reschedule', $appt))->assertOk()->assertSee('Save changes');

        // Its own current time is offered for the same stylist.
        $slots = $this->getJson(route('admin.appointments.slots', ['service_id' => $appt->service_id, 'date' => $date, 'staff_id' => $amaya->id, 'ignore' => $appt->id]))->json();
        $this->assertContains('10:00', array_column($slots, 'start'));

        $this->post(route('admin.appointments.reschedule.save', $appt), [
            'service_id' => $colour->id, 'date' => $date, 'start_time' => '14:00', 'staff_id' => $priya->id,
        ])->assertRedirect(route('admin.appointments.show', $appt))->assertSessionHas('success');

        $appt->refresh();
        $this->assertEquals($colour->id, $appt->service_id);
        $this->assertEquals($priya->id, $appt->staff_id);
        $this->assertEquals('15:00:00', $appt->slot_end);
        $this->assertEquals('confirmed', $appt->status);   // admin changes stay confirmed
        $this->assertEquals('admin', $appt->rescheduled_by);
        $this->assertEquals(3, $appt->reschedule_count);    // not counted against the customer
    }

    public function test_admin_cannot_change_paid_or_completed_booking(): void
    {
        $appt = $this->makeAppointment($this->makeService(), null, ['status' => 'completed', 'payment_status' => 'paid']);

        $this->actingAsAdmin()->get(route('admin.appointments.reschedule', $appt))
            ->assertRedirect(route('admin.appointments.show', $appt))->assertSessionHas('error');
        $this->post(route('admin.appointments.reschedule.save', $appt), [
            'service_id' => $appt->service_id, 'date' => now()->addDays(3)->toDateString(), 'start_time' => '11:00',
        ])->assertSessionHas('error');
        $this->assertEquals('10:00:00', $appt->fresh()->slot_start);
    }

    // ── Feedback (admin) ───────────────────────────────────────────────

    private function makeFeedback(bool $allowPublic, array $extra = []): \App\Models\Feedback
    {
        $staff = $this->makeStaff('Rated');
        $appt  = $this->makeAppointment($this->makeService(), now()->subDay()->toDateString(), ['status' => 'completed', 'staff_id' => $staff->id]);

        return \App\Models\Feedback::create(array_merge([
            'appointment_id' => $appt->id, 'customer_id' => $appt->customer_id, 'staff_id' => $staff->id,
            'service_id' => $appt->service_id, 'rating' => 4, 'comment' => 'Great service', 'allow_public' => $allowPublic,
        ], $extra));
    }

    public function test_feedback_page_lists_reviews_and_summary(): void
    {
        $fb = $this->makeFeedback(true);

        $this->actingAsAdmin()->get(route('admin.feedback.index'))->assertOk()
            ->assertSee('Great service')->assertSee('Can be published')->assertSee('4.0');
        $this->get(route('admin.feedback.index', ['rating' => 1]))->assertOk()->assertDontSee('Great service');
        $this->get(route('admin.employees.index'))->assertOk()->assertSee('(1)');
        $this->get(route('admin.appointments.show', $fb->appointment_id))->assertSee('Customer feedback');
    }

    public function test_admin_can_publish_only_permitted_reviews(): void
    {
        $ok = $this->makeFeedback(true);
        $private = $this->makeFeedback(false);

        $this->actingAsAdmin()->post(route('admin.feedback.publish', $ok))->assertSessionHas('success');
        $this->assertTrue($ok->fresh()->is_public);

        $this->post(route('admin.feedback.publish', $private))->assertSessionHas('error');
        $this->assertFalse($private->fresh()->is_public);

        $this->get('/')->assertSee('Great service');

        $this->post(route('admin.feedback.publish', $ok));
        $this->assertFalse($ok->fresh()->is_public);
    }

    public function test_admin_can_delete_feedback(): void
    {
        $fb = $this->makeFeedback(false);

        $this->actingAsAdmin()->delete(route('admin.feedback.destroy', $fb))->assertSessionHas('success');
        $this->assertNull($fb->fresh());
    }

    public function test_feedback_admin_requires_login(): void
    {
        $this->get(route('admin.feedback.index'))->assertRedirect('/admin/login');
    }
}
