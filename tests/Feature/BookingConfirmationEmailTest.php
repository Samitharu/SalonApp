<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmed;
use App\Models\Admin;
use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\SystemLog;
use App\Services\BookingService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Email is required for bookings, and the customer is emailed when their
 * booking is confirmed or changes. Mail problems never block the salon.
 */
class BookingConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    private Service $facial;
    private Staff $amaya;
    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('next monday 10:00', config('app.timezone')));
        for ($d = 0; $d <= 6; $d++) {
            BusinessHour::create(['day_of_week' => $d, 'open_time' => '08:00:00', 'close_time' => '20:00:00', 'is_closed' => false]);
        }
        $this->amaya = Staff::create(['name' => 'Amaya', 'is_active' => true]);
        $this->facial = Service::create(['name' => 'Gold Facial', 'slug' => 'gold', 'price' => 9500, 'duration_minutes' => 60, 'is_active' => true]);
        $this->date = now()->addDays(3)->toDateString();
        Setting::updateOrCreate(['key' => 'salon_name'], ['value' => 'Falothes']);
        setting('__flush');

        Mail::fake();
        config(['mail.default' => 'smtp']);   // "live" mail; Mail::fake catches it
    }

    private function admin(): static
    {
        return $this->actingAs(Admin::firstOrCreate(['email' => 'o@s.lk'], ['name' => 'Owner', 'is_owner' => true, 'password' => Hash::make('secret123')]), 'admin');
    }

    private function bookOnline(array $over = [])
    {
        return $this->postJson(route('booking.store'), array_merge([
            'name' => 'Dilini Perera', 'phone' => '0771234567', 'email' => 'dilini@example.com',
            'service_id' => $this->facial->id, 'date' => $this->date, 'start_time' => '10:00',
        ], $over));
    }

    private function appt(array $over = []): Appointment
    {
        $c = Customer::firstOrCreate(['phone' => '0771234567'], ['name' => 'Dilini Perera']);

        return Appointment::create(array_merge([
            'appointment_no' => strtoupper(Str::random(8)), 'customer_id' => $c->id, 'service_id' => $this->facial->id, 'staff_id' => $this->amaya->id,
            'appointment_date' => $this->date, 'slot_start' => '10:00:00', 'slot_end' => '11:00:00',
            'type' => 'online', 'status' => 'pending', 'payment_status' => 'unpaid', 'contact_email' => 'dilini@example.com',
        ], $over));
    }

    // ── Email is compulsory ────────────────────────────────────────────

    public function test_online_booking_requires_a_valid_email(): void
    {
        $this->bookOnline(['email' => ''])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->bookOnline(['email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->assertSame(0, Appointment::count());

        $this->bookOnline(['email' => ' Dilini@Example.COM '])->assertOk();
        $this->assertSame('dilini@example.com', Appointment::first()->contact_email);
        $this->get('/booking')->assertSee('We’ll send your booking confirmation here.');
    }

    public function test_staff_bookings_for_later_require_email_but_walk_ins_do_not(): void
    {
        $this->admin();
        $base = ['name' => 'Phone Caller', 'phone' => '0712345678', 'service_id' => $this->facial->id, 'date' => $this->date, 'start_time' => '11:00', 'type' => 'phone'];

        $this->post(route('admin.appointments.store'), $base)->assertSessionHasErrors('email');
        $this->assertSame(0, Appointment::count());

        // Walk-ins are already here, so no email is needed.
        $this->post(route('admin.appointments.store'), ['walk_in' => 1, 'name' => 'Walk In', 'phone' => '0711111111', 'service_id' => $this->facial->id, 'start_time' => '10:00', 'staff_id' => $this->amaya->id, 'type' => 'counter'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, Appointment::where('walk_in', true)->count());

        $staff = ['mode' => 'later', 'customer_name' => 'Nadeesha', 'customer_phone' => '0772222222', 'service_id' => $this->facial->id, 'date' => $this->date, 'start_time' => '14:00', 'type' => 'phone'];
        $this->actingAs($this->amaya, 'staff')->postJson(route('stylist.walkin-store'), $staff)->assertStatus(422)->assertJsonValidationErrors('customer_email');
        $this->actingAs($this->amaya, 'staff')->postJson(route('stylist.walkin-store'), ['customer_name' => 'Walk Two', 'customer_phone' => '0773333333', 'service_id' => $this->facial->id, 'start_time' => '14:00'])->assertOk();

        Mail::assertNothingSent();
    }

    // ── When the email goes out ────────────────────────────────────────

    public function test_customer_is_emailed_when_staff_confirm_not_before(): void
    {
        $this->bookOnline()->assertOk();
        Mail::assertNothingSent();                               // still pending

        $a = Appointment::first();
        $this->admin()->post(route('admin.appointments.status', $a), ['status' => 'confirmed'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Confirmation email going to dilini@example.com'));

        Mail::assertSent(BookingConfirmed::class, 1);
        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('dilini@example.com') && $m->kind === 'confirmed' && $m->appointment->is($a));
        $a->refresh();
        $this->assertNotNull($a->confirmation_sent_at);
        $this->assertNull($a->confirmation_error);
        $this->assertDatabaseHas('system_logs', ['action' => 'appointment.confirmation_sent']);

        $this->get(route('admin.appointments.show', $a))->assertSee('to dilini@example.com')->assertSee('Send confirmation again');
    }

    public function test_auto_confirmed_online_booking_is_emailed_straight_away(): void
    {
        Setting::updateOrCreate(['key' => 'auto_confirm'], ['value' => '1']);
        setting('__flush');

        $this->bookOnline()->assertOk()->assertJson(['status' => 'confirmed']);

        Mail::assertSent(BookingConfirmed::class, 1);
    }

    public function test_phone_bookings_by_staff_are_confirmed_and_emailed(): void
    {
        $this->admin()->post(route('admin.appointments.store'), [
            'name' => 'Phone Caller', 'phone' => '0712345678', 'email' => 'caller@example.com', 'service_id' => $this->facial->id,
            'date' => $this->date, 'start_time' => '11:00', 'type' => 'phone',
        ])->assertRedirect();
        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('caller@example.com'));

        $this->actingAs($this->amaya, 'staff')->postJson(route('stylist.walkin-store'), [
            'mode' => 'later', 'customer_name' => 'Nadeesha', 'customer_phone' => '0772222222', 'customer_email' => 'nadeesha@example.com',
            'service_id' => $this->facial->id, 'date' => $this->date, 'start_time' => '14:00', 'type' => 'phone', 'staff_id' => $this->amaya->id,
        ])->assertOk()->assertJson(['email' => 'nadeesha@example.com']);
        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('nadeesha@example.com'));
    }

    public function test_a_walk_in_starting_now_gets_no_email_but_one_coming_back_later_does(): void
    {
        $this->actingAs($this->amaya, 'staff')->postJson(route('stylist.walkin-store'), [
            'customer_name' => 'Here Now', 'customer_phone' => '0771111111', 'customer_email' => 'now@example.com', 'service_id' => $this->facial->id, 'start_time' => '10:00',
        ])->assertOk()->assertJson(['status' => 'in_progress']);
        Mail::assertNothingSent();

        $this->actingAs($this->amaya, 'staff')->postJson(route('stylist.walkin-store'), [
            'customer_name' => 'Back Later', 'customer_phone' => '0772222222', 'customer_email' => 'later@example.com', 'service_id' => $this->facial->id, 'start_time' => '15:00',
        ])->assertOk()->assertJson(['status' => 'confirmed']);
        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('later@example.com'));
    }

    public function test_only_real_changes_send_mail(): void
    {
        $a = $this->appt();
        $this->admin()->post(route('admin.appointments.status', $a), ['status' => 'confirmed']);
        Mail::assertSent(BookingConfirmed::class, 1);

        // Notes don't concern the customer; neither does stepping back from "in progress".
        $this->put(route('admin.appointments.update', $a), ['staff_id' => $this->amaya->id, 'notes' => 'Allergic to latex']);
        $this->post(route('admin.appointments.status', $a), ['status' => 'in_progress']);
        $this->post(route('admin.appointments.status', $a), ['status' => 'confirmed']);
        Mail::assertSent(BookingConfirmed::class, 1);

        // Cancelled then re-confirmed: they need to know it's back on.
        $this->post(route('admin.appointments.status', $a), ['status' => 'cancelled']);
        $this->post(route('admin.appointments.status', $a), ['status' => 'confirmed']);
        Mail::assertSent(BookingConfirmed::class, 2);
    }

    public function test_moving_a_confirmed_booking_sends_the_new_details(): void
    {
        $a = $this->appt(['status' => 'pending']);
        $a->update(['status' => 'confirmed']);
        BookingService::reschedule($a->fresh(), $this->facial, $this->date, '15:00', $this->amaya->id, 'admin');
        $this->get('/');   // ends a request, like production

        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->kind === 'updated');
        Mail::assertSent(BookingConfirmed::class, fn ($m) => str_contains($m->envelope()->subject, 'has changed'));
    }

    public function test_a_customers_own_change_waits_for_staff_before_any_email(): void
    {
        $a = $this->appt(['status' => 'confirmed', 'confirmation_sent_at' => now()]);
        $this->get('/');
        Mail::assertSent(BookingConfirmed::class, 1);            // created confirmed

        BookingService::reschedule($a->fresh(), $this->facial, $this->date, '15:00', $this->amaya->id, 'customer');
        $this->get('/');
        $this->assertSame('pending', $a->fresh()->status);
        Mail::assertSent(BookingConfirmed::class, 1);

        $this->admin()->post(route('admin.appointments.status', $a), ['status' => 'confirmed']);
        Mail::assertSent(BookingConfirmed::class, 2);
    }

    // ── Mail problems never block the salon ────────────────────────────

    public function test_a_mail_failure_never_blocks_confirming_and_can_be_retried(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection to smtp.example.com timed out'));
        $a = $this->appt();

        $this->admin()->post(route('admin.appointments.status', $a), ['status' => 'confirmed'])->assertRedirect()->assertSessionHas('success');

        $a->refresh();
        $this->assertSame('confirmed', $a->status);
        $this->assertNull($a->confirmation_sent_at);
        $this->assertStringContainsString('timed out', $a->confirmation_error);
        $this->assertSame('error', SystemLog::where('action', 'appointment.confirmation_failed')->value('level'));
        $this->get(route('admin.appointments.show', $a))->assertSee('Couldn’t be sent to dilini@example.com')->assertSee('timed out')->assertSee('Send confirmation again');

        // Retrying reports the problem plainly.
        $this->post(route('admin.appointments.confirmation-email', $a))->assertSessionHas('error', fn ($m) => str_contains($m, 'timed out'));
    }

    public function test_resending_after_correcting_the_address(): void
    {
        $a = $this->appt(['status' => 'confirmed', 'contact_email' => 'typo@exmaple.com']);
        $this->admin()->get('/admin');
        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('typo@exmaple.com'));
        $this->assertNotNull($a->fresh()->confirmation_sent_at);

        $this->put(route('admin.appointments.update', $a), ['staff_id' => $this->amaya->id, 'contact_email' => 'Right@Example.com'])->assertRedirect();
        $a->refresh();
        $this->assertSame('right@example.com', $a->contact_email);
        $this->assertNull($a->confirmation_sent_at, 'The new address hasn’t received anything yet');
        $this->assertFalse(Mail::sent(BookingConfirmed::class, fn ($m) => $m->hasTo('right@example.com'))->isNotEmpty());

        $this->post(route('admin.appointments.confirmation-email', $a))->assertSessionHas('success', 'Confirmation sent to right@example.com.');
        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('right@example.com'));

        $this->put(route('admin.appointments.update', $a), ['staff_id' => $this->amaya->id, 'contact_email' => 'nope'])->assertSessionHasErrors('contact_email');
    }

    public function test_older_bookings_without_an_email_can_still_be_confirmed(): void
    {
        $a = $this->appt(['contact_email' => null]);

        $this->admin()->post(route('admin.appointments.status', $a), ['status' => 'confirmed'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'There’s no email on this booking'));

        $this->assertSame('confirmed', $a->fresh()->status);
        Mail::assertNothingSent();
        $this->get(route('admin.appointments.show', $a))->assertSee('No email on this booking')->assertDontSee('Send confirmation again');

        // The customer's profile email is used when the booking has none.
        $a->customer->update(['email' => 'profile@example.com']);
        $this->post(route('admin.appointments.confirmation-email', $a))->assertSessionHas('success');
        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('profile@example.com'));
    }

    public function test_resend_is_only_for_confirmed_bookings_and_admins(): void
    {
        $a = $this->appt();
        $this->post(route('admin.appointments.confirmation-email', $a))->assertRedirect(route('admin.login'));

        $this->admin()->post(route('admin.appointments.confirmation-email', $a))->assertSessionHas('error');
        Mail::assertNothingSent();
    }

    public function test_bookings_dont_claim_to_be_sent_while_mail_only_goes_to_the_log(): void
    {
        config(['mail.default' => 'log']);
        $a = $this->appt();

        $this->admin()->post(route('admin.appointments.status', $a), ['status' => 'confirmed'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Email isn’t set up yet'));

        $this->assertNull($a->fresh()->confirmation_sent_at);
        $this->get(route('admin.appointments.show', $a))->assertSee('email isn’t set up yet');
        $this->get(route('admin.settings.index'))->assertSee('Customers aren’t receiving emails yet');
    }

    // ── Whose address ──────────────────────────────────────────────────

    public function test_typing_someones_phone_online_cannot_redirect_their_email(): void
    {
        $owner = Customer::create(['name' => 'Real Owner', 'phone' => '0771234567', 'email' => 'owner@example.com']);
        Setting::updateOrCreate(['key' => 'auto_confirm'], ['value' => '1']);
        setting('__flush');

        $this->bookOnline(['email' => 'stranger@example.com'])->assertOk();

        $this->assertSame('owner@example.com', $owner->fresh()->email);
        // This booking's mail goes where it was asked to; the owner's other mail doesn't move.
        Mail::assertSent(BookingConfirmed::class, fn ($m) => $m->hasTo('stranger@example.com'));
        $this->assertSame('owner@example.com', $this->appt(['contact_email' => null, 'slot_start' => '15:00:00', 'slot_end' => '16:00:00'])->confirmationEmail());
    }

    public function test_staff_can_correct_a_customers_email_when_booking(): void
    {
        $owner = Customer::create(['name' => 'Regular', 'phone' => '0712345678', 'email' => 'old@example.com']);

        $this->admin()->post(route('admin.appointments.store'), [
            'name' => 'Regular', 'phone' => '0712345678', 'email' => 'new@example.com', 'service_id' => $this->facial->id,
            'date' => $this->date, 'start_time' => '11:00', 'type' => 'phone',
        ])->assertRedirect();

        $this->assertSame('new@example.com', $owner->fresh()->email);
    }

    // ── The email itself ───────────────────────────────────────────────

    public function test_email_shows_everything_the_customer_needs(): void
    {
        Setting::updateOrCreate(['key' => 'salon_phone'], ['value' => '+94 77 000 0000']);
        Setting::updateOrCreate(['key' => 'salon_email'], ['value' => 'hello@falothes.lk']);
        setting('__flush');
        $a = $this->appt(['status' => 'confirmed', 'appointment_no' => 'REF12345']);

        $mail = new BookingConfirmed($a->fresh(['customer', 'service', 'staff', 'items', 'payments']));
        $when = Carbon::parse($this->date);

        $mail->assertHasSubject('Your Falothes booking is confirmed — ' . $when->format('j M') . ', 10:00 AM');
        $mail->assertFrom(config('mail.from.address'), 'Falothes');
        $mail->assertHasReplyTo('hello@falothes.lk');
        $mail->assertSeeInHtml('Your booking is confirmed');
        $mail->assertSeeInHtml('Hello Dilini,');
        $mail->assertSeeInHtml('REF12345');
        $mail->assertSeeInHtml($when->format('j F Y'));
        $mail->assertSeeInHtml('10:00 AM – 11:00 AM');
        $mail->assertSeeInHtml('Gold Facial');
        $mail->assertSeeInHtml('Amaya');
        $mail->assertSeeInHtml('LKR 9,500.00');
        $mail->assertSeeInHtml('+94 77 000 0000');
        $mail->assertSeeInHtml(route('booking.manage'));
        $mail->assertSeeInText('REF12345');
        $mail->assertSeeInText('Gold Facial');
    }

    public function test_email_shows_advance_due_or_received(): void
    {
        Setting::updateOrCreate(['key' => 'bank_details'], ['value' => 'Commercial Bank 12345']);
        setting('__flush');
        $this->facial->update(['advance_type' => 'percent', 'advance_value' => 30]);
        $a = $this->appt(['status' => 'confirmed']);

        $mail = new BookingConfirmed($a->fresh(['customer', 'service', 'staff', 'items', 'payments']));
        $mail->assertSeeInHtml('Advance to pay: LKR 2,850.00');
        $mail->assertSeeInHtml('Commercial Bank 12345');

        PaymentService::record($a, 'advance', [['method' => 'bank_transfer', 'amount' => 2850]], 'admin', 'Owner');
        $paid = new BookingConfirmed($a->fresh(['customer', 'service', 'staff', 'items', 'payments']));
        $paid->assertSeeInHtml('Advance received');
        $paid->assertSeeInHtml('LKR 6,650.00');
        $paid->assertDontSeeInHtml('Advance to pay');
    }

    public function test_email_embeds_the_logo_and_escapes_customer_text(): void
    {
        Storage::fake('public');
        $this->admin()->post(route('admin.settings.branding'), ['logo' => UploadedFile::fake()->image('logo.png', 400, 200), 'receipt_paper' => 'a5']);
        $customer = Customer::create(['name' => '<script>alert(1)</script> Name', 'phone' => '0700000000']);
        $a = $this->appt(['status' => 'confirmed', 'customer_id' => $customer->id]);

        $mail = new BookingConfirmed($a->fresh(['customer', 'service', 'staff', 'items', 'payments']));
        $html = $mail->render();

        // render() inlines embedded images; a real send attaches them. PNG, because Outlook can't show WebP.
        $this->assertStringContainsString('src="data:image/png;base64,', $html);
        $this->assertStringNotContainsString('<script>alert', $html);
    }

    // ── Test email from Settings ───────────────────────────────────────

    public function test_settings_can_send_a_test_email(): void
    {
        $this->post(route('admin.settings.test-email'), ['to' => 'me@example.com'])->assertRedirect(route('admin.login'));

        $this->admin()->get(route('admin.settings.index'))->assertSee('Send test email')->assertSee('Email is set up');
        $this->post(route('admin.settings.test-email'), ['to' => 'bad'])->assertSessionHasErrorsIn('testEmail', 'to');
        $this->post(route('admin.settings.test-email'), ['to' => 'me@example.com'])->assertSessionHas('success', fn ($m) => str_contains($m, 'me@example.com'));
    }

    public function test_settings_test_email_reports_failures(): void
    {
        Mail::shouldReceive('raw')->andThrow(new RuntimeException('535 Authentication failed'));

        $this->admin()->post(route('admin.settings.test-email'), ['to' => 'me@example.com'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Authentication failed'));
    }
}
