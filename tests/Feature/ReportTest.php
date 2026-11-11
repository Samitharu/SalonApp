<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\Staff;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reports: every figure is checked against a small, hand-counted dataset.
 *
 * Period = last 10 days. Inside it:
 *   Kamal (regular, 3 visits): 3 × Haircut 2,000 by Amaya, all completed + paid (cash/cash/card)
 *       one of them has a 500 extra → paid 2,500
 *   Nimali (new): 1 × Colour 5,000 by Priya, completed, NOT paid (outstanding)
 *   Ruwan: 1 × Haircut by Priya, cancelled by customer; 1 × Colour by Amaya, no-show
 *   Saman: 1 × Haircut, pending, unassigned
 * Outside it: Dilini, 3 completed visits 80+ days ago, nothing booked → lapsed regular.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Staff $amaya;
    private Staff $priya;
    private Service $haircut;
    private Service $colour;

    protected function setUp(): void
    {
        parent::setUp();

        $this->amaya = Staff::create(['name' => 'Amaya', 'is_active' => true]);
        $this->priya = Staff::create(['name' => 'Priya', 'is_active' => true]);
        $this->haircut = Service::create(['name' => 'Haircut', 'slug' => 'haircut', 'price' => 2000, 'duration_minutes' => 60, 'is_active' => true]);
        $this->colour = Service::create(['name' => 'Colour', 'slug' => 'colour', 'price' => 5000, 'duration_minutes' => 120, 'is_active' => true]);

        $kamal = Customer::create(['name' => 'Kamal Perera', 'phone' => '0771111111']);
        $first = $this->appt($kamal, $this->haircut, $this->amaya, 9, ['status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cash', 'amount_paid' => 2500]);
        $first->items()->create(['name' => 'Toner', 'price' => 500, 'quantity' => 1, 'added_by' => 'stylist']);
        $this->appt($kamal, $this->haircut, $this->amaya, 6, ['status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cash', 'amount_paid' => 2000]);
        $last = $this->appt($kamal, $this->haircut, $this->amaya, 2, ['status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'card', 'amount_paid' => 2000, 'walk_in' => true, 'type' => 'counter']);
        Feedback::create(['appointment_id' => $last->id, 'customer_id' => $kamal->id, 'staff_id' => $this->amaya->id, 'service_id' => $this->haircut->id, 'rating' => 4]);

        $nimali = Customer::create(['name' => 'Nimali Silva', 'phone' => '0772222222']);
        $this->appt($nimali, $this->colour, $this->priya, 3, ['status' => 'completed', 'type' => 'phone']);

        $ruwan = Customer::create(['name' => 'Ruwan Dias', 'phone' => '0773333333']);
        $this->appt($ruwan, $this->haircut, $this->priya, 5, ['status' => 'cancelled', 'cancelled_by' => 'customer']);
        $this->appt($ruwan, $this->colour, $this->amaya, 4, ['status' => 'no_show']);

        $saman = Customer::create(['name' => 'Saman Kumara', 'phone' => '0774444444']);
        $this->appt($saman, $this->haircut, null, 1, ['status' => 'pending']);

        $dilini = Customer::create(['name' => 'Dilini Fernando', 'phone' => '0775555555']);
        foreach ([80, 90, 100] as $ago) {
            $this->appt($dilini, $this->haircut, $this->amaya, $ago, ['status' => 'completed', 'payment_status' => 'paid', 'payment_method' => 'cash', 'amount_paid' => 2000]);
        }

        // Paid bills carry their payment lines, as the cashier records them.
        foreach (Appointment::where('payment_status', 'paid')->get() as $a) {
            $a->payments()->create(['kind' => 'payment', 'method' => $a->payment_method, 'amount' => $a->amount_paid, 'received_at' => now()]);
        }
    }

    private function appt(Customer $c, Service $s, ?Staff $st, int $daysAgo, array $extra = []): Appointment
    {
        return Appointment::create(array_merge([
            'appointment_no' => strtoupper(Str::random(8)), 'customer_id' => $c->id, 'service_id' => $s->id, 'staff_id' => $st?->id,
            'appointment_date' => now()->subDays($daysAgo)->toDateString(), 'slot_start' => '10:00:00', 'slot_end' => '11:00:00',
            'type' => 'online', 'status' => 'pending', 'payment_status' => 'unpaid',
        ], $extra));
    }

    private function report(): ReportService
    {
        return ReportService::forRange(null, now()->subDays(9)->toDateString(), now()->toDateString());
    }

    // ── Figures ────────────────────────────────────────────────────────

    public function test_sales_figures(): void
    {
        $s = $this->report()->sales();

        $this->assertEquals(11500, $s['revenue']);      // 2500 + 2000 + 2000 + 5000 (unpaid completed)
        $this->assertSame(4, $s['bills']);
        $this->assertEquals(2875, $s['average']);
        $this->assertEquals(6500, $s['collected']);
        $this->assertEquals(5000, $s['outstanding']);
        $this->assertEquals(500, $s['extras']);
        $this->assertEquals(11000, $s['services']);
        $this->assertEquals(['label' => 'Cash', 'count' => 2, 'total' => 4500.0], $s['methods']['cash']);
        $this->assertEquals(['label' => 'Card', 'count' => 1, 'total' => 2000.0], $s['methods']['card']);
        $this->assertSame(0, $s['methods']['bank_transfer']['count']);
        $this->assertEquals(0, $s['advancesHeld']['total']);
        $this->assertCount(10, $s['series']);
        $this->assertEquals(11500, collect($s['series'])->sum('value'));
        $this->assertSame('Haircut', $s['topServices']->first()->name);   // 6,500 billed vs Colour 5,000
        $this->assertEquals(6500, $s['topServices']->first()->revenue);
    }

    public function test_bookings_figures(): void
    {
        $b = $this->report()->bookings();

        $this->assertSame(7, $b['total']);
        $this->assertSame(4, $b['status']['completed']);
        $this->assertSame(1, $b['status']['cancelled']);
        $this->assertSame(1, $b['status']['no_show']);
        $this->assertSame(1, $b['status']['pending']);
        $this->assertEquals(66.7, $b['completion']);   // 4 of 6 finished
        $this->assertSame(1, $b['cancelledByCustomer']);
        $this->assertSame(['Online' => 5, 'Phone' => 1, 'At the desk' => 0, 'Walk-in' => 1], $b['channels']);
        $this->assertSame(5, array_sum($b['weekdays']));   // excludes cancelled + no-show
        $this->assertSame(['10 AM' => 5], $b['hours']);
    }

    public function test_staff_performance(): void
    {
        $rows = $this->report()->staff()->keyBy('name');

        $amaya = $rows['Amaya'];
        $this->assertSame(4, $amaya['bookings']);
        $this->assertSame(3, $amaya['completed']);
        $this->assertSame(1, $amaya['no_show']);
        $this->assertEquals(75, $amaya['completion']);
        $this->assertEquals(6500, $amaya['revenue']);
        $this->assertEquals(500, $amaya['extras']);
        $this->assertEquals(3.0, $amaya['hours']);
        $this->assertEquals(4.0, $amaya['rating']);
        $this->assertSame(1, $amaya['walkins']);

        $priya = $rows['Priya'];
        $this->assertEquals(5000, $priya['revenue']);
        $this->assertEquals(50, $priya['completion']);    // 1 completed, 1 cancelled
        $this->assertNull($priya['rating']);

        $this->assertSame(1, $rows['Unassigned']['bookings']);
        $this->assertSame('Amaya', $this->report()->staff()->first()['name']); // sorted by revenue
    }

    public function test_customer_figures_regulars_and_lapsed(): void
    {
        $c = $this->report()->customers(3);

        $this->assertSame(2, $c['served']);       // Kamal + Nimali
        $this->assertSame(2, $c['new']);          // both had their first visit in the period
        $this->assertSame(1, $c['returning']);    // Kamal has visited more than once; Nimali once
        $this->assertEquals(50, $c['repeatRate']);
        $this->assertCount(1, $c['regulars']);
        $kamal = $c['regulars']->first();
        $this->assertSame('Kamal Perera', $kamal['name']);
        $this->assertSame(3, $kamal['visits']);
        $this->assertEquals(6500, $kamal['spent']);
        $this->assertSame('Haircut', $kamal['service']);
        $this->assertSame('Amaya', $kamal['stylist']);

        $this->assertSame(['Dilini Fernando'], $c['lapsed']->pluck('name')->all());

        // A lapsed regular with an upcoming booking isn't on the win-back list.
        $dilini = Customer::where('name', 'Dilini Fernando')->first();
        $this->appt($dilini, $this->haircut, $this->amaya, -5, ['status' => 'confirmed']);
        $this->assertCount(0, $this->report()->lapsedRegulars(3));
    }

    public function test_returning_customer_is_not_new(): void
    {
        $range = ReportService::forRange(null, now()->subDays(5)->toDateString(), now()->toDateString());
        $c = $range->customers(2);

        // Kamal first visited 9 days ago (before this range), so he's not new but is a repeat customer; Nimali is new.
        $this->assertSame(2, $c['served']);
        $this->assertSame(1, $c['new']);
        $this->assertSame(1, $c['returning']);
    }

    public function test_service_revenue_excludes_extras(): void
    {
        $data = $this->report()->services();
        $byName = $data['services']->keyBy('name');

        $this->assertEquals(6000, $byName['Haircut']['revenue']);   // 6500 billed − 500 toner
        $this->assertEquals(5000, $byName['Colour']['revenue']);
        $this->assertSame(5, $byName['Haircut']['bookings']);
        $this->assertEquals(20, $byName['Haircut']['lostRate']);    // 1 cancelled of 5
        $this->assertSame([['name' => 'Toner', 'qty' => 1, 'revenue' => 500.0]], $data['extras']->all());
    }

    public function test_previous_period_comparison(): void
    {
        $r = ReportService::forRange(null, now()->subDays(4)->toDateString(), now()->toDateString());
        $prev = $r->previous();

        $this->assertSame(now()->subDays(9)->toDateString(), $prev->from->toDateString());
        $this->assertSame(now()->subDays(5)->toDateString(), $prev->to->toDateString());
        $this->assertEquals(7000, $r->sales()['revenue']);        // 2000 (2 days ago) + 5000 (3 days ago)
        $this->assertEquals(4500, $r->sales()['previous']);       // 2500 (9 days) + 2000 (6 days)
    }

    // ── Pages ──────────────────────────────────────────────────────────

    private function asAdmin(): static
    {
        return $this->actingAs(Admin::create(['is_owner' => true, 'name' => 'Owner', 'email' => 'o@s.lk', 'password' => Hash::make('secret123')]), 'admin');
    }

    public function test_every_report_tab_renders(): void
    {
        $this->asAdmin();
        $q = ['from' => now()->subDays(9)->toDateString(), 'to' => now()->toDateString()];

        $this->get(route('admin.reports', ['tab' => 'sales'] + $q))->assertOk()->assertSee('Revenue per day')->assertSee('11,500');
        $this->get(route('admin.reports', ['tab' => 'bookings'] + $q))->assertOk()->assertSee('Busiest days')->assertSee('66.7%');
        $this->get(route('admin.reports', ['tab' => 'staff'] + $q))->assertOk()->assertSee('Amaya')->assertSee('75%');
        $this->get(route('admin.reports', ['tab' => 'customers'] + $q))->assertOk()->assertSee('Kamal Perera')->assertSee('Dilini Fernando');
        $this->get(route('admin.reports', ['tab' => 'services'] + $q))->assertOk()->assertSee('Toner');

        foreach (array_keys(ReportService::PRESETS) as $preset) {
            $this->get(route('admin.reports', ['tab' => 'sales', 'range' => $preset]))->assertOk();
        }
        $this->get(route('admin.reports'))->assertOk()->assertSee('This month');
    }

    public function test_csv_export(): void
    {
        $this->asAdmin();
        $q = ['from' => now()->subDays(9)->toDateString(), 'to' => now()->toDateString()];

        $csv = $this->get(route('admin.reports.export', ['tab' => 'staff'] + $q))->assertOk()->streamedContent();
        $this->assertStringContainsString('Staff performance report', $csv);
        $this->assertStringContainsString('Amaya,4,3,0,1,75,1,3,6500,500,4,1', $csv);

        $csv = $this->get(route('admin.reports.export', ['tab' => 'customers', 'min_visits' => 3] + $q))->assertOk()->streamedContent();
        $this->assertStringContainsString('Kamal Perera', $csv);

        $this->assertDatabaseHas('system_logs', ['action' => 'system.report_exported']);
    }

    public function test_validation_and_access(): void
    {
        $this->get(route('admin.reports'))->assertRedirect('/admin/login');

        $this->asAdmin();
        $this->get('/admin/reports/nope')->assertNotFound();
        $this->get(route('admin.reports', ['tab' => 'sales', 'from' => now()->toDateString(), 'to' => now()->subDays(3)->toDateString()]))
            ->assertSessionHasErrors('to');
        $this->get(route('admin.reports', ['tab' => 'sales', 'from' => now()->addDay()->toDateString(), 'to' => now()->addDays(2)->toDateString()]))
            ->assertSessionHasErrors('from');
    }
}
