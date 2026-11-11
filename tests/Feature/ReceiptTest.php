<?php

namespace Tests\Feature;

use App\Http\Controllers\ReceiptController;
use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Printed receipts (cashier + admin) and the logo / receipt settings.
 */
class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Service $facial;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Setting::updateOrCreate(['key' => 'salon_name'], ['value' => 'Falothes']);
        Setting::updateOrCreate(['key' => 'salon_phone'], ['value' => '+94 77 000 0000']);
        setting('__flush');
        $this->facial = Service::create(['name' => 'Gold Radiance Facial', 'slug' => 'gold', 'price' => 9500, 'duration_minutes' => 75, 'is_active' => true]);
    }

    private function admin(): static
    {
        return $this->actingAs(Admin::create(['is_owner' => true, 'name' => 'Owner', 'email' => 'o@s.lk', 'password' => Hash::make('secret123')]), 'admin');
    }

    private function appt(array $extra = []): Appointment
    {
        $c = Customer::firstOrCreate(['phone' => '0771234567'], ['name' => 'Dilini Perera']);
        $s = Staff::firstOrCreate(['name' => 'Amaya'], ['is_active' => true]);

        return Appointment::create(array_merge([
            'appointment_no' => strtoupper(Str::random(8)), 'customer_id' => $c->id, 'service_id' => $this->facial->id, 'staff_id' => $s->id,
            'appointment_date' => now()->toDateString(), 'slot_start' => '10:00:00', 'slot_end' => '11:15:00',
            'type' => 'online', 'status' => 'confirmed', 'payment_status' => 'unpaid',
        ], $extra));
    }

    public function test_paid_receipt_shows_brand_items_totals_and_payments(): void
    {
        $a = $this->appt();
        $a->items()->create(['name' => 'Toner', 'price' => 900, 'quantity' => 2, 'added_by' => 'stylist']);
        $paid = PaymentService::record($a, 'payment', [['method' => 'cash', 'amount' => 11300, 'cash_received' => 12000]], 'cashier', 'Cashier station');

        $this->admin()->get(route('cashier.receipt', $a))->assertOk()
            ->assertSee('Falothes')
            ->assertSee('Payment receipt')
            ->assertSee($paid->job_number)                        // receipt no.
            ->assertSee($a->appointment_no)
            ->assertSee('Gold Radiance Facial')->assertSee('1 hr 15 min · Amaya')
            ->assertSee('Toner')->assertSee('LKR 900.00 each')->assertSee('LKR 1,800.00')
            ->assertSee('LKR 11,300.00')
            ->assertSee('Change given')->assertSee('LKR 700.00')
            ->assertSee('received LKR 12,000.00')
            ->assertSee('Thank you, Dilini')
            ->assertSee('077 ••• 4567')->assertDontSee('0771234567')   // phone masked
            ->assertSee('Rate your visit')                          // scan-to-rate QR
            ->assertDontSee('Balance due');
    }

    public function test_advance_receipt_shows_the_balance_due(): void
    {
        $a = $this->appt();
        PaymentService::record($a, 'advance', [['method' => 'bank_transfer', 'amount' => 3000, 'reference' => 'COMB-1182']], 'admin', 'Owner');

        $this->admin()->get(route('admin.appointments.receipt', $a))->assertOk()
            ->assertSee('Advance receipt')->assertSee('Advance paid')
            ->assertSee('Advance · Bank transfer')->assertSee('Ref COMB-1182')
            ->assertSee('Balance due')->assertSee('LKR 6,500.00')
            ->assertDontSee('Rate your visit');                     // not completed yet
    }

    public function test_unpaid_visit_prints_as_a_bill_and_cancelled_ones_have_none(): void
    {
        $this->admin()->get(route('admin.appointments.receipt', $this->appt()))->assertOk()->assertSee('<h2 class="r-title">Bill</h2>', false)->assertSee('Unpaid');
        $this->get(route('admin.appointments.receipt', $this->appt(['status' => 'cancelled'])))->assertNotFound();
    }

    public function test_logo_and_thank_you_message_appear_on_the_receipt(): void
    {
        $a = $this->appt();
        $this->admin()->get(route('admin.appointments.receipt', $a))->assertSee('class="r-mono"', false);   // initial when no logo

        $this->post(route('admin.settings.branding'), [
            'logo' => UploadedFile::fake()->image('logo.png', 800, 400),
            'receipt_footer' => 'Follow us @falothes',
            'receipt_paper' => '80mm',
        ])->assertRedirect();

        $logo = setting('salon_logo');
        $this->assertStringStartsWith('branding/', $logo);
        Storage::disk('public')->assertExists($logo);
        $this->assertSame('80mm', setting('receipt_paper'));

        $this->get(route('admin.appointments.receipt', $a))->assertOk()
            ->assertSee('storage/' . $logo, false)
            ->assertSee('Follow us @falothes')
            ->assertSee('<body class="thermal">', false);

        // Paper can be overridden per print; the logo can be removed.
        $this->get(route('admin.appointments.receipt', [$a, 'paper' => 'a5']))->assertSee('<body class="">', false);
        $this->post(route('admin.settings.branding'), ['remove_logo' => '1', 'receipt_paper' => 'a5'])->assertRedirect();
        $this->assertNull(setting('salon_logo'));
        Storage::disk('public')->assertMissing($logo);
    }

    public function test_branding_validation(): void
    {
        $this->admin();
        $this->post(route('admin.settings.branding'), ['logo' => UploadedFile::fake()->create('logo.pdf', 50, 'application/pdf'), 'receipt_paper' => 'a5'])->assertSessionHasErrorsIn('branding', 'logo');
        $this->post(route('admin.settings.branding'), ['receipt_paper' => 'a3'])->assertSessionHasErrorsIn('branding', 'receipt_paper');
        $this->post(route('admin.settings.branding'), ['logo' => UploadedFile::fake()->image('tiny.png', 20, 20), 'receipt_paper' => 'a5'])->assertSessionHasErrorsIn('branding', 'logo');
        $this->assertNull(setting('salon_logo'));
    }

    public function test_receipts_need_cashier_or_admin_access(): void
    {
        $a = $this->appt();

        $this->get(route('cashier.receipt', $a))->assertRedirect(route('cashier.unlock'));
        $this->get(route('admin.appointments.receipt', $a))->assertRedirect(route('admin.login'));
        $this->post(route('admin.settings.branding'), ['receipt_paper' => '80mm'])->assertRedirect(route('admin.login'));
    }

    public function test_auto_print_only_when_asked(): void
    {
        $a = $this->appt();
        $this->admin()->get(route('cashier.receipt', $a))->assertDontSee('window.print(); }, 150');
        $this->get(route('cashier.receipt', [$a, 'print' => 1]))->assertSee('window.print(); }, 150', false);
    }

    public function test_phone_masking(): void
    {
        $this->assertSame('077 ••• 4567', ReceiptController::maskPhone('077 123 4567'));
        $this->assertSame('947 ••• 4567', ReceiptController::maskPhone('+94 77 123 4567'));
        $this->assertSame('12345', ReceiptController::maskPhone('12345'));
        $this->assertNull(ReceiptController::maskPhone(null));
    }
}
