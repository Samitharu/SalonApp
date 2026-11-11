<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\SystemLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * System log: what gets recorded, who it's credited to, and that it never
 * leaks secrets or breaks the action being logged.
 */
class SystemLogTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::create(['is_owner' => true, 'name' => 'Owner', 'email' => 'owner@salon.com', 'password' => Hash::make('secret123')]);
    }

    private function service(int $duration = 60): Service
    {
        return Service::create(['name' => 'Haircut', 'slug' => Str::random(8), 'price' => 2000, 'duration_minutes' => $duration, 'is_active' => true]);
    }

    private function appointment(array $extra = []): Appointment
    {
        $customer = Customer::create(['name' => 'Nadeesha Silva', 'phone' => '0771234567']);

        return Appointment::create(array_merge([
            'appointment_no' => 'LOGTEST1', 'customer_id' => $customer->id, 'service_id' => $this->service()->id,
            'appointment_date' => now()->addDays(3)->toDateString(), 'slot_start' => '10:00:00', 'slot_end' => '11:00:00',
            'type' => 'online', 'status' => 'pending',
        ], $extra));
    }

    private function lastLog(string $action): ?SystemLog
    {
        return SystemLog::where('action', $action)->latest('id')->first();
    }

    // ── What gets recorded ─────────────────────────────────────────────

    public function test_online_booking_is_logged_and_credited_to_the_customer(): void
    {
        $date = Carbon::now()->next(Carbon::MONDAY)->toDateString();
        BusinessHour::create(['day_of_week' => 1, 'open_time' => '09:00:00', 'close_time' => '18:00:00', 'is_closed' => false]);
        $service = $this->service();

        $this->postJson(route('booking.store'), [
            'name' => 'Kamal Perera', 'phone' => '0779876543', 'email' => 'kamal@example.com', 'service_id' => $service->id, 'date' => $date, 'start_time' => '10:00',
        ])->assertOk();

        $log = $this->lastLog('appointment.created');
        $this->assertNotNull($log);
        $this->assertSame('appointment', $log->category);
        $this->assertSame('customer', $log->actor_type);
        $this->assertSame('Kamal Perera', $log->actor_name);
        $this->assertSame('Appointment', $log->subject_type);
        $this->assertSame(Appointment::first()->id, $log->subject_id);
        $this->assertStringContainsString('Haircut', $log->description);
        $this->assertSame('127.0.0.1', $log->ip_address);
    }

    public function test_admin_sign_in_success_and_failure_are_logged(): void
    {
        $this->admin();

        $this->post('/admin/login', ['email' => 'owner@salon.com', 'password' => 'wrong-pass']);
        $failed = $this->lastLog('auth.login_failed');
        $this->assertSame('warning', $failed->level);
        $this->assertSame('owner@salon.com', $failed->properties['email']);

        $this->post('/admin/login', ['email' => 'owner@salon.com', 'password' => 'secret123']);
        $ok = $this->lastLog('auth.login');
        $this->assertSame('admin', $ok->actor_type);
        $this->assertSame('Owner', $ok->actor_name);
    }

    public function test_stylist_sign_in_and_wrong_cashier_pin_are_logged(): void
    {
        Staff::create(['name' => 'Amaya', 'is_active' => true, 'login_username' => 'amaya', 'password' => Hash::make('secret123')]);
        Setting::create(['key' => 'cashier_pin_hash', 'value' => Hash::make('4321')]);

        $this->post(route('stylist.login.post'), ['username' => 'amaya', 'password' => 'secret123']);
        $this->assertSame('staff', $this->lastLog('auth.login')->actor_type);

        $this->post(route('cashier.unlock.post'), ['pin' => '1111']);
        $this->assertSame('Wrong PIN entered at the cashier station', $this->lastLog('auth.login_failed')->description);
    }

    public function test_admin_status_change_records_before_and_after(): void
    {
        $appt = $this->appointment();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.appointments.status', $appt), ['status' => 'confirmed']);

        $log = $this->lastLog('appointment.status_changed');
        $this->assertSame(['from' => 'pending', 'to' => 'confirmed'], $log->properties['status']);
        $this->assertSame('admin', $log->actor_type);
        $this->assertSame('Owner', $log->actor_name);
    }

    public function test_payment_and_extras_are_logged(): void
    {
        $appt = $this->appointment(['status' => 'completed', 'appointment_date' => now()->toDateString()]);
        $this->actingAs($this->admin(), 'admin');

        $this->postJson(route('cashier.items.store', $appt), ['name' => 'Toner', 'price' => 500, 'quantity' => 2])->assertOk();
        $this->postJson(route('cashier.pay', $appt), ['payment_method' => 'cash', 'cash_received' => 5000])->assertOk();

        $this->assertStringContainsString('2 × Toner', $this->lastLog('payment.extra_added')->description);
        $paid = $this->lastLog('payment.received');
        $this->assertEquals(3000, $paid->properties['amount']);
        $this->assertSame('cash', $paid->properties['lines'][0]['method']);
        $this->assertEquals(5000, $paid->properties['lines'][0]['cash_received']);
        $this->assertSame('payment', $paid->category);
    }

    public function test_customer_cancellation_is_credited_to_the_customer(): void
    {
        $appt = $this->appointment(['appointment_date' => now()->addDays(5)->toDateString()]);

        $this->post(route('booking.cancel'), ['reference' => 'LOGTEST1', 'phone' => '0771234567']);

        $log = $this->lastLog('appointment.cancelled');
        $this->assertSame('customer', $log->actor_type);
        $this->assertSame($appt->customer_id, $log->actor_id);
    }

    public function test_settings_log_only_what_changed(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $base = [
            'salon_name' => 'Negombo Salon', 'currency' => 'LKR', 'max_advance_days' => 90, 'min_notice_minutes' => 60,
            'cancellation_hours' => 2, 'slot_step' => 0, 'max_parallel_bookings' => 3, 'auto_confirm' => 0, 'log_retention_days' => 180,
        ];
        $this->put(route('admin.settings.update'), ['settings' => $base]);
        $this->put(route('admin.settings.update'), ['settings' => ['cancellation_hours' => 6] + $base]);

        $log = $this->lastLog('settings.updated');
        $this->assertSame(['cancellation_hours'], array_keys($log->properties));
        $this->assertEquals(['from' => '2', 'to' => '6'], $log->properties['cancellation_hours']);
    }

    // ── Never leak secrets ─────────────────────────────────────────────

    public function test_passwords_pins_and_tokens_are_never_logged(): void
    {
        $admin = $this->admin();
        $staff = Staff::create(['name' => 'Kasun', 'is_active' => true]);
        $this->actingAs($admin, 'admin');

        $this->put(route('admin.settings.cashier-pin'), ['pin' => '98765', 'pin_confirmation' => '98765']);
        $this->post(route('admin.employees.set-login', $staff), ['login_username' => 'kasun', 'password' => 'TopSecret99', 'password_confirmation' => 'TopSecret99']);
        $this->put(route('admin.account.update'), ['name' => 'Owner', 'email' => 'owner@salon.com', 'current_password' => 'secret123', 'password' => 'NewSecret42', 'password_confirmation' => 'NewSecret42']);

        $this->assertNotNull($this->lastLog('settings.cashier_pin_changed'));
        $this->assertNotNull($this->lastLog('staff.login_set'));
        $this->assertStringContainsString('password', $this->lastLog('auth.account_updated')->description);

        $dump = SystemLog::all()->toJson();
        foreach (['98765', 'TopSecret99', 'NewSecret42', 'secret123', '$2y$'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }
    }

    // ── Errors and safety ──────────────────────────────────────────────

    public function test_application_errors_are_stored_with_exception_details(): void
    {
        Log::error('Payment gateway timed out', ['exception' => new RuntimeException('Connection reset')]);
        Log::info('Routine message'); // below the database channel's level

        $log = SystemLog::where('category', 'system')->latest('id')->first();
        $this->assertSame('error', $log->level);
        $this->assertSame('Payment gateway timed out', $log->description);
        $this->assertSame(RuntimeException::class, $log->properties['exception']);
        $this->assertStringContainsString('SystemLogTest.php', $log->properties['file']);
        $this->assertSame(0, SystemLog::where('description', 'Routine message')->count());
    }

    public function test_a_broken_log_table_never_breaks_the_action(): void
    {
        Schema::drop('system_logs');
        $appt = $this->appointment();

        $this->actingAs($this->admin(), 'admin')
            ->post(route('admin.appointments.status', $appt), ['status' => 'confirmed'])
            ->assertSessionHas('success');

        $this->assertSame('confirmed', $appt->fresh()->status);
        $this->assertNull(SystemLog::record('system.test', 'should not throw'));
    }

    public function test_prune_command_removes_only_old_entries(): void
    {
        $old = SystemLog::record('system.test', 'old entry');
        $old->forceFill(['created_at' => now()->subDays(200)])->save();
        SystemLog::record('system.test', 'recent entry');

        $this->artisan('salon:prune-logs', ['--days' => 180])->assertSuccessful();

        $this->assertNull(SystemLog::find($old->id));
        $this->assertSame(1, SystemLog::where('description', 'recent entry')->count());
        $this->assertNotNull($this->lastLog('system.logs_pruned'));
    }

    // ── Admin page ─────────────────────────────────────────────────────

    public function test_admin_can_browse_filter_and_export_the_log(): void
    {
        $appt = $this->appointment();
        SystemLog::record('appointment.updated', 'Booking LOGTEST1: notes updated', $appt, ['notes' => ['from' => null, 'to' => 'VIP']]);
        SystemLog::record('auth.login_failed', 'Failed admin sign-in for x@y.z', level: 'warning');

        $this->actingAs($this->admin(), 'admin');

        $this->get(route('admin.logs.index'))->assertOk()
            ->assertSee('Booking LOGTEST1: notes updated')->assertSee('Failed admin sign-in')->assertSee('VIP');
        $this->get(route('admin.logs.index', ['level' => 'warning']))->assertOk()
            ->assertSee('Failed admin sign-in')->assertDontSee('notes updated');
        $this->get(route('admin.logs.index', ['subject' => 'Appointment', 'id' => $appt->id]))->assertOk()
            ->assertSee('notes updated')->assertDontSee('Failed admin sign-in');

        $csv = $this->get(route('admin.logs.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Booking LOGTEST1: notes updated', $csv);
        $this->assertNotNull($this->lastLog('system.log_exported'));

        $this->get(route('admin.appointments.show', $appt))->assertSee('Activity')->assertSee('notes updated');
    }

    public function test_log_requires_admin(): void
    {
        $this->get(route('admin.logs.index'))->assertRedirect('/admin/login');
        $this->get(route('admin.logs.export'))->assertRedirect('/admin/login');
    }
}
