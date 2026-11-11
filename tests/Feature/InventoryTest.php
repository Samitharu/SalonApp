<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StockMovement;
use App\Models\SystemLog;
use App\Services\InventoryService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stock: products, linking them to services, automatic usage on completed
 * visits, restocking, stock counts and low-stock alerts.
 */
class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Staff $amaya;
    private Service $facial;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('next monday 10:00', config('app.timezone')));
        for ($d = 0; $d <= 6; $d++) {
            BusinessHour::create(['day_of_week' => $d, 'open_time' => '08:00:00', 'close_time' => '20:00:00', 'is_closed' => false]);
        }
        $this->amaya = Staff::create(['name' => 'Amaya', 'is_active' => true]);
        $this->facial = Service::create(['name' => 'Gold facial', 'slug' => 'gold-facial', 'price' => 9500, 'duration_minutes' => 60, 'is_active' => true]);
        $this->actingAs(Admin::create(['is_owner' => true, 'name' => 'Owner', 'email' => 'o@s.lk', 'password' => Hash::make('secret123')]), 'admin');
    }

    private function product(string $name, float $stock, array $extra = []): Product
    {
        $p = Product::create(array_merge(['name' => $name, 'unit' => 'packet', 'uses_per_unit' => 1], $extra));
        InventoryService::open($p, $stock);

        return $p->fresh();
    }

    private function appt(Service $s, array $extra = []): Appointment
    {
        $c = Customer::firstOrCreate(['phone' => '0771234567'], ['name' => 'Dilini Perera']);

        return Appointment::create(array_merge([
            'appointment_no' => strtoupper(Str::random(8)), 'customer_id' => $c->id, 'service_id' => $s->id, 'staff_id' => $this->amaya->id,
            'appointment_date' => now()->toDateString(), 'slot_start' => '11:00:00', 'slot_end' => '12:00:00',
            'type' => 'online', 'status' => 'confirmed', 'payment_status' => 'unpaid',
        ], $extra));
    }

    // ── Products ───────────────────────────────────────────────────────

    public function test_admin_adds_a_product_with_opening_stock(): void
    {
        $this->post(route('admin.products.store'), [
            'name' => 'Gold facial pack', 'brand' => "Nature's Secrets", 'unit' => 'packet', 'uses_per_unit' => 2,
            'opening_stock' => 10, 'opening_cost' => 15000, 'low_stock_level' => 3,
        ])->assertRedirect(route('admin.products.index'));

        $p = Product::first();
        $this->assertSame(10.0, $p->stock);
        $this->assertSame(2.0, $p->uses_per_unit);
        $this->assertSame(1500.0, $p->cost_per_unit);
        $this->assertSame(20, $p->clientsLeft());
        $this->assertSame(0.5, $p->defaultPerClient());
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'type' => 'opening', 'quantity' => 10, 'balance_after' => 10]);
        $this->assertDatabaseHas('system_logs', ['action' => 'stock.product_created', 'category' => 'stock']);

        $this->get(route('admin.products.index'))->assertOk()->assertSee('Gold facial pack')->assertSee('10 packets')->assertSee('≈ 20 clients');
    }

    public function test_product_validation(): void
    {
        $this->product('Wax', 5, ['brand' => 'Rica']);

        $this->post(route('admin.products.store'), ['name' => 'Wax', 'brand' => 'Rica', 'unit' => 'packet', 'uses_per_unit' => 1])->assertSessionHasErrors('name');
        $this->post(route('admin.products.store'), ['name' => 'X', 'unit' => 'bucket', 'uses_per_unit' => 1])->assertSessionHasErrors('unit');
        $this->post(route('admin.products.store'), ['name' => 'X', 'unit' => 'ml', 'uses_per_unit' => 0.5])->assertSessionHasErrors('uses_per_unit');
        $this->post(route('admin.products.store'), ['name' => 'X', 'unit' => 'ml', 'uses_per_unit' => 1, 'opening_stock' => -2])->assertSessionHasErrors('opening_stock');
        // Same name, different brand is fine.
        $this->post(route('admin.products.store'), ['name' => 'Wax', 'brand' => 'Veet', 'unit' => 'jar', 'uses_per_unit' => 1])->assertSessionDoesntHaveErrors();
    }

    public function test_editing_a_product_never_changes_stock(): void
    {
        $p = $this->product('Cream', 4);

        $this->put(route('admin.products.update', $p), ['name' => 'Cleansing cream', 'unit' => 'packet', 'uses_per_unit' => 1, 'stock' => 999, 'is_active' => '1'])->assertRedirect();

        $this->assertSame('Cleansing cream', $p->fresh()->name);
        $this->assertSame(4.0, $p->fresh()->stock);
    }

    // ── Restock & adjust ───────────────────────────────────────────────

    public function test_restock_adds_stock_and_records_the_purchase(): void
    {
        $p = $this->product('Facial pack', 2);

        $this->get(route('admin.products.restock', ['product' => $p->id]))->assertOk()->assertSee('Facial pack');
        $this->post(route('admin.products.restock.store'), [
            'product_id' => $p->id, 'quantity' => 12, 'total_cost' => 18000, 'supplier' => 'Lanka Beauty Supplies', 'reference' => 'INV-552', 'occurred_on' => now()->subDay()->toDateString(),
        ])->assertRedirect(route('admin.products.index'))->assertSessionHas('success', fn ($m) => str_contains($m, 'Stock is now 14 packets'));

        $p->refresh();
        $this->assertSame(14.0, $p->stock);
        $this->assertSame(1500.0, $p->cost_per_unit);
        $m = $p->movements()->where('type', 'purchase')->first();
        $this->assertSame(['Lanka Beauty Supplies', 'INV-552', 18000.0, 14.0], [$m->supplier, $m->reference, $m->total_cost, $m->balance_after]);
        $this->assertSame('Owner', $m->created_by_name);
        $this->assertDatabaseHas('system_logs', ['action' => 'stock.restocked']);

        // "Save & restock another" comes back to the form.
        $this->post(route('admin.products.restock.store'), ['product_id' => $p->id, 'quantity' => 1, 'next' => 'another'])->assertRedirect(route('admin.products.restock'));
    }

    public function test_restock_validation(): void
    {
        $p = $this->product('Pack', 1);
        $archived = $this->product('Old pack', 1, ['is_active' => false]);

        $this->post(route('admin.products.restock.store'), ['product_id' => $p->id, 'quantity' => 0])->assertSessionHasErrors('quantity');
        $this->post(route('admin.products.restock.store'), ['product_id' => $p->id, 'quantity' => 2, 'occurred_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('occurred_on');
        $this->post(route('admin.products.restock.store'), ['product_id' => $archived->id, 'quantity' => 2])->assertSessionHasErrors('product_id');
        $this->assertSame(1.0, $p->fresh()->stock);
    }

    public function test_stock_count_sets_the_actual_quantity(): void
    {
        $p = $this->product('Pack', 10);

        $this->post(route('admin.products.adjust.store', $p), ['counted' => 7.5, 'reason' => 'damaged', 'note' => 'Box got wet'])->assertRedirect(route('admin.products.show', $p));
        $p->refresh();
        $this->assertSame(7.5, $p->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $p->id, 'type' => 'adjustment', 'quantity' => -2.5, 'reason' => 'damaged']);
        $this->assertSame('warning', SystemLog::where('action', 'stock.adjusted')->value('level'));

        // "Other" needs a note; the same count changes nothing.
        $this->post(route('admin.products.adjust.store', $p), ['counted' => 5, 'reason' => 'other'])->assertSessionHasErrors('note');
        $this->post(route('admin.products.adjust.store', $p), ['counted' => 7.5, 'reason' => 'count'])->assertSessionHas('success', fn ($m) => str_contains($m, 'nothing changed'));
        $this->assertSame(2, $p->movements()->count());
    }

    // ── Linking products to services ───────────────────────────────────

    public function test_service_form_links_products_per_client(): void
    {
        $pack = $this->product('Gold pack', 10, ['uses_per_unit' => 2]);
        $cream = $this->product('Massage cream', 500, ['unit' => 'ml']);

        $this->get(route('admin.services.edit', $this->facial))->assertOk()->assertSee('Products used per client')->assertSee('data-default="0.5"', false);

        $this->put(route('admin.services.update', $this->facial), [
            'name' => 'Gold facial', 'price' => 9500, 'duration_minutes' => 60, 'is_active' => '1',
            'products' => [['id' => $pack->id, 'qty' => '0.5'], ['id' => '', 'qty' => ''], ['id' => $cream->id, 'qty' => '15']],
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $links = $this->facial->products()->get()->pluck('pivot.quantity', 'id')->map(fn ($q) => (float) $q)->all();
        $this->assertEquals([$pack->id => 0.5, $cream->id => 15.0], $links);
        $log = SystemLog::where('action', 'service.updated')->latest('id')->first();
        $this->assertContains('Gold pack: 0.5 packets', $log->properties['products']['to']);

        // Removing all rows unlinks them.
        $this->put(route('admin.services.update', $this->facial), ['name' => 'Gold facial', 'price' => 9500, 'duration_minutes' => 60, 'is_active' => '1'])->assertRedirect();
        $this->assertSame(0, $this->facial->products()->count());
    }

    public function test_service_product_rows_are_validated(): void
    {
        $pack = $this->product('Pack', 5);
        $base = ['name' => 'Gold facial', 'price' => 9500, 'duration_minutes' => 60, 'is_active' => '1'];

        $this->put(route('admin.services.update', $this->facial), $base + ['products' => [['id' => $pack->id, 'qty' => '']]])->assertSessionHasErrors('products.0.qty');
        $this->put(route('admin.services.update', $this->facial), $base + ['products' => [['id' => '', 'qty' => '2']]])->assertSessionHasErrors('products.0.id');
        $this->put(route('admin.services.update', $this->facial), $base + ['products' => [['id' => $pack->id, 'qty' => 1], ['id' => $pack->id, 'qty' => 2]]])->assertSessionHasErrors('products.1.id');
        $this->put(route('admin.services.update', $this->facial), $base + ['products' => [['id' => 999, 'qty' => 1]]])->assertSessionHasErrors('products.0.id');
        $this->assertSame(0, $this->facial->products()->count());

        // Creating a service with products works in one go.
        $this->post(route('admin.services.store'), ['name' => 'Clean-up', 'price' => 3000, 'duration_minutes' => 45, 'is_active' => '1', 'products' => [['id' => $pack->id, 'qty' => 1]]])->assertRedirect();
        $this->assertSame(1, Service::where('name', 'Clean-up')->first()->products()->count());
    }

    // ── Automatic usage ────────────────────────────────────────────────

    public function test_completing_a_visit_by_payment_uses_its_products_once(): void
    {
        $pack = $this->product('Gold pack', 10, ['uses_per_unit' => 2]);
        $cream = $this->product('Massage cream', 500, ['unit' => 'ml']);
        $this->facial->products()->sync([$pack->id => ['quantity' => 0.5], $cream->id => ['quantity' => 15]]);

        $a = $this->appt($this->facial);
        $this->assertSame(10.0, $pack->fresh()->stock, 'Nothing used before the visit is completed');

        $this->postJson(route('cashier.pay', $a), ['kind' => 'payment', 'lines' => [['method' => 'card', 'amount' => 9500]]])->assertOk();

        $this->assertSame(9.5, $pack->fresh()->stock);
        $this->assertSame(485.0, $cream->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $pack->id, 'type' => 'usage', 'quantity' => -0.5, 'appointment_id' => $a->id, 'reference' => $a->appointment_no]);

        // Saving the completed visit again never deducts twice.
        $a->refresh()->update(['notes' => 'Lovely client']);
        $a->update(['status' => 'completed']);
        InventoryService::syncAppointment($a);
        $this->assertSame(9.5, $pack->fresh()->stock);
        $this->assertSame(1, StockMovement::where('appointment_id', $a->id)->where('product_id', $pack->id)->count());

        $this->get(route('admin.appointments.show', $a))->assertOk()->assertSee('Products used')->assertSee('15 ml');
    }

    public function test_extras_on_the_bill_use_their_products_too(): void
    {
        $conditioner = $this->product('Conditioner', 1000, ['unit' => 'ml']);
        $addon = Service::create(['name' => 'Deep conditioning', 'slug' => 'deep', 'price' => 1500, 'duration_minutes' => 5, 'is_active' => true, 'is_addon' => true]);
        $addon->products()->sync([$conditioner->id => ['quantity' => 30]]);

        $a = $this->appt($this->facial, ['status' => 'in_progress']);
        $a->items()->create(['service_id' => $addon->id, 'name' => 'Deep conditioning', 'price' => 1500, 'quantity' => 2, 'added_by' => 'stylist']);
        $this->assertSame(1000.0, $conditioner->fresh()->stock);

        $a->update(['status' => 'completed']);
        $this->assertSame(940.0, $conditioner->fresh()->stock, '2 × 30 ml');

        // An extra removed after completion goes back on the shelf.
        $a->items()->first()->delete();
        $this->assertSame(1000.0, $conditioner->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', ['product_id' => $conditioner->id, 'type' => 'return', 'quantity' => 60]);
    }

    public function test_stylist_and_admin_completion_use_stock_and_undoing_returns_it(): void
    {
        $pack = $this->product('Pack', 4);
        $this->facial->products()->sync([$pack->id => ['quantity' => 1]]);

        // Stylist marks the job complete.
        $a = $this->appt($this->facial, ['status' => 'in_progress']);
        $this->actingAs($this->amaya, 'staff')->postJson(route('stylist.complete', $a))->assertOk();
        $this->assertSame(3.0, $pack->fresh()->stock);

        // Admin corrects it to cancelled → stock returned; back to completed → used again.
        $this->post(route('admin.appointments.status', $a), ['status' => 'cancelled'])->assertRedirect();
        $this->assertSame(4.0, $pack->fresh()->stock);
        $this->post(route('admin.appointments.status', $a), ['status' => 'completed'])->assertRedirect();
        $this->assertSame(3.0, $pack->fresh()->stock);

        $types = StockMovement::where('appointment_id', $a->id)->orderBy('id')->pluck('type')->all();
        $this->assertSame(['usage', 'return', 'usage'], $types);
    }

    public function test_running_out_never_blocks_a_payment(): void
    {
        $pack = $this->product('Pack', 0.5, ['low_stock_level' => 2]);
        $this->facial->products()->sync([$pack->id => ['quantity' => 1]]);
        $a = $this->appt($this->facial);

        $paid = PaymentService::record($a, 'payment', [['method' => 'cash', 'amount' => 9500]], 'cashier', 'Cashier station');

        $this->assertSame('completed', $paid->status);
        $this->assertSame(-0.5, $pack->fresh()->stock);
        $this->assertSame('out', $pack->fresh()->stockStatus());
        $this->assertDatabaseHas('system_logs', ['action' => 'stock.low', 'level' => 'warning']);
    }

    public function test_services_without_products_change_nothing(): void
    {
        $pack = $this->product('Pack', 3);
        $a = $this->appt($this->facial);
        $a->update(['status' => 'completed']);

        $this->assertSame(3.0, $pack->fresh()->stock);
        $this->assertSame(0, StockMovement::where('appointment_id', $a->id)->count());
    }

    // ── Alerts ─────────────────────────────────────────────────────────

    public function test_low_stock_alerts_show_on_dashboard_sidebar_and_list(): void
    {
        $low = $this->product('Gold pack', 3, ['low_stock_level' => 5]);
        $out = $this->product('Wax strips', 0);
        $fine = $this->product('Cotton', 50, ['low_stock_level' => 10]);
        $this->product('Old cream', 0, ['is_active' => false]);

        $this->assertEqualsCanonicalizing([$low->id, $out->id], Product::lowStock()->pluck('id')->all());

        $this->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Low stock')->assertSee('Gold pack')->assertSee('3 packets left')->assertSee('Wax strips')->assertSee('Out of stock')
            ->assertDontSee('Old cream')
            ->assertSee('title="2 low or out of stock"', false);

        $this->get(route('admin.products.index', ['status' => 'low']))->assertOk()->assertSee('Gold pack')->assertSee('Wax strips')->assertDontSee('Cotton');
        $this->get(route('admin.products.index', ['status' => 'out']))->assertOk()->assertSee('Wax strips')->assertDontSee('Gold pack');
    }

    public function test_crossing_the_alert_level_logs_a_warning_once(): void
    {
        $p = $this->product('Pack', 6, ['low_stock_level' => 5]);
        $this->facial->products()->sync([$p->id => ['quantity' => 1]]);

        $this->appt($this->facial)->update(['status' => 'completed']);   // 5 → low
        $this->appt($this->facial)->update(['status' => 'completed']);   // 4, still low: no new warning

        $this->assertSame(4.0, $p->fresh()->stock);
        $this->assertSame(1, SystemLog::where('action', 'stock.low')->count());
    }

    // ── Archive / delete / access ──────────────────────────────────────

    public function test_products_with_history_are_archived_not_deleted(): void
    {
        $fresh = $this->product('Never used', 5);
        $used = $this->product('Used', 5);
        InventoryService::restock($used, 1, null);

        $this->delete(route('admin.products.destroy', $fresh))->assertRedirect();
        $this->assertModelMissing($fresh);

        $this->delete(route('admin.products.destroy', $used))->assertRedirect();
        $this->assertFalse($used->fresh()->is_active);
        $this->get(route('admin.products.index'))->assertDontSee('>Used<', false);
        $this->get(route('admin.products.index', ['archived' => 1]))->assertSee('Used');

        $this->post(route('admin.products.restore', $used))->assertRedirect();
        $this->assertTrue($used->fresh()->is_active);
    }

    public function test_history_page_explains_every_change(): void
    {
        $p = $this->product('Pack', 5);
        $this->facial->products()->sync([$p->id => ['quantity' => 1]]);
        InventoryService::restock($p, 10, 12000, ['supplier' => 'Cargills']);
        $a = $this->appt($this->facial);
        $a->update(['status' => 'completed']);

        $this->get(route('admin.products.show', $p))->assertOk()
            ->assertSee('Opening stock')->assertSee('Restock')->assertSee('Cargills')->assertSee('Used')->assertSee($a->appointment_no)
            ->assertSee('Gold facial');                        // "Used in"
        $this->assertSame(14.0, $p->fresh()->stock);
        $this->assertSame(14.0, (float) $p->movements()->sum('quantity'), 'Balance always equals the sum of movements');
    }

    public function test_stock_pages_are_admin_only(): void
    {
        $p = $this->product('Pack', 1);
        auth('admin')->logout();

        foreach ([route('admin.products.index'), route('admin.products.create'), route('admin.products.restock'), route('admin.products.show', $p), route('admin.products.adjust', $p)] as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
        }
        $this->post(route('admin.products.restock.store'), ['product_id' => $p->id, 'quantity' => 100])->assertRedirect(route('admin.login'));
        $this->assertSame(1.0, $p->fresh()->stock);
    }
}
