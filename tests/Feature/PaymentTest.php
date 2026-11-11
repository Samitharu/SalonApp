<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\SystemLog;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Payment methods (cash / card / bank transfer / QR), split payments and
 * advances for special packages. No gateway: staff record what was paid.
 */
class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private Service $bridal;
    private Service $cut;
    private Staff $amaya;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('next monday 10:00', config('app.timezone')));

        for ($d = 0; $d <= 6; $d++) {
            BusinessHour::create(['day_of_week' => $d, 'open_time' => '08:00:00', 'close_time' => '20:00:00', 'is_closed' => false]);
        }
        $this->amaya = Staff::create(['name' => 'Amaya', 'is_active' => true]);
        $this->bridal = Service::create(['name' => 'Bridal dressing', 'slug' => 'bridal', 'price' => 50000, 'duration_minutes' => 180,
            'is_active' => true, 'advance_type' => 'percent', 'advance_value' => 30]);
        $this->cut = Service::create(['name' => 'Hair Cut', 'slug' => 'cut', 'price' => 2000, 'duration_minutes' => 30, 'is_active' => true]);
        $this->actingAs(Admin::create(['is_owner' => true, 'name' => 'Owner', 'email' => 'o@s.lk', 'password' => Hash::make('secret123')]), 'admin');
    }

    private function appt(Service $s, array $extra = []): Appointment
    {
        $c = Customer::firstOrCreate(['phone' => '0771234567'], ['name' => 'Dilini Perera']);

        return Appointment::create(array_merge([
            'appointment_no' => strtoupper(\Illuminate\Support\Str::random(8)), 'customer_id' => $c->id, 'service_id' => $s->id, 'staff_id' => $this->amaya->id,
            'appointment_date' => now()->addDays(10)->toDateString(), 'slot_start' => '09:00:00', 'slot_end' => '12:00:00',
            'type' => 'online', 'status' => 'confirmed', 'payment_status' => 'unpaid',
        ], $extra));
    }

    private function pay(Appointment $a, array $lines, string $kind = 'payment', ?float $expected = null): TestResponse
    {
        return $this->postJson(route('cashier.pay', $a), array_filter([
            'kind' => $kind, 'lines' => $lines, 'expected_balance' => $expected,
        ], fn ($v) => $v !== null));
    }

    // ── Advance rules on services ──────────────────────────────────────

    public function test_service_advance_amounts(): void
    {
        $this->assertEquals(15000, $this->bridal->advanceAmount());              // 30% of 50,000
        $this->assertSame('30% advance (LKR 15,000.00)', $this->bridal->advanceLabel());
        $this->assertFalse($this->cut->requiresAdvance());

        $fixed = Service::create(['name' => 'Engagement', 'slug' => 'eng', 'price' => 20000, 'duration_minutes' => 120, 'advance_type' => 'fixed', 'advance_value' => 5000]);
        $this->assertEquals(5000, $fixed->advanceAmount());
    }

    public function test_admin_sets_advance_on_a_service_with_validation(): void
    {
        $base = ['name' => 'Bridal', 'price' => 40000, 'duration_minutes' => 180, 'is_active' => 1];

        $this->post(route('admin.services.store'), $base + ['advance_type' => 'percent', 'advance_value' => 150])->assertSessionHasErrors('advance_value');
        $this->post(route('admin.services.store'), $base + ['advance_type' => 'fixed', 'advance_value' => 50000])->assertSessionHasErrors('advance_value');
        $this->post(route('admin.services.store'), $base + ['advance_type' => 'fixed'])->assertSessionHasErrors('advance_value');

        $this->post(route('admin.services.store'), $base + ['advance_type' => 'fixed', 'advance_value' => 10000])->assertRedirect();
        $this->assertEquals(10000, Service::where('name', 'Bridal')->first()->advanceAmount());
    }

    // ── Split payments at the cashier ──────────────────────────────────

    public function test_bill_can_be_split_across_methods(): void
    {
        $a = $this->appt($this->cut, ['status' => 'completed', 'appointment_date' => now()->toDateString()]);

        $res = $this->pay($a, [
            ['method' => 'cash', 'amount' => 500, 'cash_received' => 1000],
            ['method' => 'card', 'amount' => 1000],
            ['method' => 'qr', 'amount' => 500, 'reference' => 'LQR-778812'],
        ], 'payment', 2000)->assertOk()->assertJsonPath('appointment.payment_status', 'paid');

        $this->assertSame('split', $res->json('appointment.payment_method'));
        $this->assertSame('Split payment', $res->json('appointment.payment_method_label'));
        $this->assertEquals(500, $res->json('appointment.change'));

        $a->refresh();
        $this->assertSame('paid', $a->payment_status);
        $this->assertSame('completed', $a->status);
        $this->assertEquals(2000, $a->amount_paid);
        $this->assertSame(3, $a->payments()->count());
        $this->assertSame('LQR-778812', $a->payments()->where('method', 'qr')->value('reference'));
        $this->assertStringContainsString('Cash LKR 500.00 + Card LKR 1,000.00 + QR payment LKR 500.00', SystemLog::where('action', 'payment.received')->value('description'));
    }

    public function test_split_must_add_up_exactly(): void
    {
        $a = $this->appt($this->cut, ['status' => 'completed']);

        $this->pay($a, [['method' => 'cash', 'amount' => 500], ['method' => 'card', 'amount' => 1000]])
            ->assertStatus(422)->assertJsonFragment(['error' => 'The payments add up to LKR 1,500.00 — LKR 500.00 is still due.']);
        $this->pay($a, [['method' => 'card', 'amount' => 2500]])->assertStatus(422);
        $this->pay($a, [['method' => 'cash', 'amount' => 1000], ['method' => 'cash', 'amount' => 1000]])
            ->assertStatus(422)->assertJsonFragment(['error' => 'Put all the cash in one line.']);
        $this->pay($a, [['method' => 'cash', 'amount' => 2000, 'cash_received' => 1500]])->assertStatus(422);
        $this->pay($a, [['method' => 'crypto', 'amount' => 2000]])->assertStatus(422);

        $this->assertSame(0, Payment::count());
        $this->assertSame('unpaid', $a->fresh()->payment_status);
    }

    public function test_bank_transfer_settlement(): void
    {
        $a = $this->appt($this->cut, ['status' => 'completed']);

        $this->pay($a, [['method' => 'bank_transfer', 'amount' => 2000, 'reference' => 'BOC-55120']])->assertOk();

        $this->assertSame('bank_transfer', $a->fresh()->payment_method);
        $this->assertSame('Bank transfer', $a->fresh()->paymentMethodLabel());
    }

    // ── Advances ───────────────────────────────────────────────────────

    public function test_advance_then_settle_the_balance(): void
    {
        $a = $this->appt($this->bridal);

        // Bridal deposit by bank transfer, before the day.
        $this->pay($a, [['method' => 'bank_transfer', 'amount' => 15000, 'reference' => 'COM-90011']], 'advance')
            ->assertOk()->assertJsonPath('appointment.payment_status', 'partial')
            ->assertJsonPath('appointment.balance', 35000)->assertJsonPath('appointment.advance_paid', 15000);

        $a->refresh();
        $this->assertSame('confirmed', $a->status);          // not completed by an advance
        $this->assertEquals(50000, $a->load('payments', 'service', 'items')->total());   // bill total isn't the advance
        $this->assertSame(0.0, $a->advanceOutstanding());

        // Extras can still be added after an advance.
        $this->postJson(route('cashier.items.store', $a), ['name' => 'Saree draping', 'price' => 3000])->assertOk();

        // On the day: balance 38,000, split cash + QR.
        $this->pay($a, [['method' => 'cash', 'amount' => 8000, 'cash_received' => 10000], ['method' => 'qr', 'amount' => 30000]], 'payment', 38000)
            ->assertOk()->assertJsonPath('appointment.payment_status', 'paid')->assertJsonPath('appointment.balance', 0);

        $a->refresh();
        $this->assertEquals(53000, $a->amount_paid);
        $this->assertSame('split', $a->payment_method);
        $this->assertSame('completed', $a->status);
        $this->assertNotNull($a->job_number);
        $this->assertSame(3, $a->payments()->count());
        $this->assertStringContainsString('after LKR 15,000.00 advance', SystemLog::where('action', 'payment.received')->value('description'));
    }

    public function test_advance_rules(): void
    {
        $a = $this->appt($this->bridal);

        $this->pay($a, [['method' => 'cash', 'amount' => 60000]], 'advance')->assertStatus(422);   // more than the bill

        $done = $this->appt($this->cut, ['status' => 'completed']);
        $this->pay($done, [['method' => 'cash', 'amount' => 500]], 'advance')->assertStatus(422);

        $gone = $this->appt($this->cut, ['status' => 'cancelled']);
        $this->pay($gone, [['method' => 'cash', 'amount' => 2000]])->assertStatus(422);

        $this->assertSame(0, Payment::count());
    }

    public function test_advance_covering_everything_then_closing_the_bill(): void
    {
        $a = $this->appt($this->cut, ['status' => 'in_progress']);
        $this->pay($a, [['method' => 'qr', 'amount' => 2000]], 'advance')->assertOk();

        $this->pay($a, [], 'payment', 0)->assertOk()->assertJsonPath('appointment.payment_status', 'paid');
        $this->assertSame('completed', $a->fresh()->status);
    }

    public function test_changed_bill_stops_the_payment(): void
    {
        $a = $this->appt($this->cut, ['status' => 'completed']);
        $a->items()->create(['name' => 'Toner', 'price' => 500, 'quantity' => 1, 'added_by' => 'stylist']);

        $this->pay($a, [['method' => 'card', 'amount' => 2000]], 'payment', 2000)
            ->assertStatus(409)->assertJsonPath('appointment.balance', 2500);
        $this->assertSame(0, Payment::count());
    }

    // ── Admin page ─────────────────────────────────────────────────────

    public function test_admin_records_and_voids_an_advance(): void
    {
        $a = $this->appt($this->bridal);

        $this->get(route('admin.appointments.show', $a))->assertSee('Advance needed:')->assertSee('Record an advance');

        $this->post(route('admin.appointments.payments.store', $a), [
            'kind' => 'advance', 'method' => 'bank_transfer', 'amount' => 10000, 'reference' => 'SAMPATH-1', 'note' => 'Deposit',
        ])->assertSessionHas('success');

        $a->refresh();
        $this->assertSame('partial', $a->payment_status);
        $this->get(route('admin.appointments.show', $a))->assertSee('SAMPATH-1')->assertSee('LKR 5,000.00 still to come');
        $this->get(route('admin.appointments.index', ['scope' => 'all']))->assertSee('Advance · LKR 10,000.00');

        $payment = $a->payments()->first();
        $this->delete(route('admin.appointments.payments.destroy', [$a, $payment]))->assertSessionHas('success');

        $a->refresh();
        $this->assertSame('unpaid', $a->payment_status);
        $this->assertNull($a->amount_paid);
        $this->assertSame('warning', SystemLog::where('action', 'payment.voided')->value('level'));
    }

    public function test_admin_settles_balance_after_the_visit(): void
    {
        $a = $this->appt($this->cut, ['status' => 'completed']);

        $this->post(route('admin.appointments.payments.store', $a), ['kind' => 'payment', 'method' => 'bank_transfer', 'amount' => 1500])
            ->assertSessionHas('error');   // must match the balance
        $this->post(route('admin.appointments.payments.store', $a), ['kind' => 'payment', 'method' => 'bank_transfer', 'amount' => 2000, 'reference' => 'HNB-7'])
            ->assertSessionHas('success');

        $this->assertSame('paid', $a->fresh()->payment_status);
    }

    // ── Customers ──────────────────────────────────────────────────────

    public function test_online_booking_of_an_advance_package(): void
    {
        Setting::create(['key' => 'auto_confirm', 'value' => '1']);
        Setting::create(['key' => 'bank_details', 'value' => "Commercial Bank\nAcc 1234567890"]);
        setting('__flush');

        $this->get('/booking')->assertSee('30% advance (LKR 15,000.00) to book');

        $this->postJson(route('booking.store'), [
            'name' => 'Kavindi Silva', 'phone' => '0779988776', 'email' => 'kavindi@example.com', 'service_id' => $this->bridal->id,
            'date' => now()->addDays(20)->toDateString(), 'start_time' => '08:00',
        ])->assertOk()
            ->assertJsonPath('status', 'pending')            // waits for the advance even with auto-confirm
            ->assertJsonPath('advance.amount', 15000);

        $a = Appointment::latest('id')->first();
        $this->post(route('booking.lookup'), ['reference' => $a->appointment_no, 'phone' => '0779988776']);
        $this->get(route('booking.manage'))->assertSee('Advance needed: LKR 15,000.00')->assertSee('Acc 1234567890')->assertSee('Cancel booking');

        // Once the advance is in, the salon handles cancellations.
        $a->payments()->create(['kind' => 'advance', 'method' => 'bank_transfer', 'amount' => 15000, 'received_at' => now()]);
        $a->update(['payment_status' => 'partial', 'amount_paid' => 15000]);
        $this->get(route('booking.manage'))->assertSee('Advance of LKR 15,000.00 received')->assertSee('changes and cancellations are handled by the salon')->assertDontSee('Cancel booking');
        $this->post(route('booking.cancel'))->assertSessionHas('error');
        $this->assertSame('pending', $a->fresh()->status);
    }

    // ── Reports ────────────────────────────────────────────────────────

    public function test_reports_break_down_every_method_and_advances_held(): void
    {
        $today = now()->toDateString();
        $done = $this->appt($this->cut, ['status' => 'completed', 'appointment_date' => $today]);
        $this->pay($done, [['method' => 'cash', 'amount' => 1000], ['method' => 'bank_transfer', 'amount' => 1000]])->assertOk();

        $upcoming = $this->appt($this->bridal);
        $this->pay($upcoming, [['method' => 'qr', 'amount' => 15000]], 'advance')->assertOk();

        $s = ReportService::forRange(null, $today, $today)->sales();

        $this->assertEquals(2000, $s['revenue']);
        $this->assertEquals(2000, $s['collected']);
        $this->assertEquals(1000, $s['methods']['cash']['total']);
        $this->assertEquals(1000, $s['methods']['bank_transfer']['total']);
        $this->assertSame(0, $s['methods']['qr']['count']);           // the advance is for a future visit
        $this->assertEquals(['total' => 15000.0, 'bookings' => 1], $s['advancesHeld']);

        $this->get(route('admin.reports', ['tab' => 'sales', 'from' => $today, 'to' => $today]))
            ->assertSee('Bank transfer')->assertSee('QR payment')->assertSee('in advances held');
    }
}
