<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmed;
use App\Models\Admin;
use App\Models\Appointment;
use App\Models\BusinessHour;
use App\Models\Offer;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\SystemLog;
use App\Services\BookingService;
use App\Services\OfferPricing;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Discounts for a period of time: which visits get them, and that the price
 * then holds everywhere, even after the offer has gone.
 */
class OfferTest extends TestCase
{
    use RefreshDatabase;

    private Service $facial;
    private Service $haircut;
    private Staff $amaya;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('next monday 10:00', config('app.timezone')));
        for ($d = 0; $d <= 6; $d++) {
            BusinessHour::create(['day_of_week' => $d, 'open_time' => '08:00:00', 'close_time' => '20:00:00', 'is_closed' => false]);
        }
        $this->amaya = Staff::create(['name' => 'Amaya', 'is_active' => true]);
        $this->facial = Service::create(['name' => 'Gold Facial', 'slug' => 'gold', 'price' => 10000, 'duration_minutes' => 60, 'is_active' => true]);
        $this->haircut = Service::create(['name' => 'Haircut', 'slug' => 'cut', 'price' => 2000, 'duration_minutes' => 30, 'is_active' => true]);
    }

    private function day(int $offset): string
    {
        return now()->addDays($offset)->toDateString();
    }

    /** 20% off everything, from 2 days ahead to 5 days ahead. */
    private function offer(array $over = [], array $services = []): Offer
    {
        $offer = Offer::create($over + [
            'name' => 'Festive glow', 'discount_type' => 'percent', 'discount_value' => 20,
            'starts_on' => $this->day(2), 'ends_on' => $this->day(5), 'applies_to' => $services ? 'selected' : 'all',
        ]);
        $offer->services()->sync(array_map(fn ($s) => $s->id, $services));
        app(OfferPricing::class)->flush();

        return $offer;
    }

    private function book(string $date, ?Service $service = null, string $start = '10:00'): Appointment
    {
        return BookingService::book($service ?? $this->facial, [
            'name' => 'Dilini Perera', 'phone' => '077' . random_int(1000000, 9999999), 'email' => 'd@example.com',
            'date' => $date, 'start' => $start, 'type' => 'online', 'status' => 'confirmed',
        ])->fresh(['service', 'items', 'payments']);
    }

    private function signInOwner(): static
    {
        return $this->actingAs(Admin::create(['is_owner' => true, 'name' => 'Owner', 'email' => 'o@s.lk', 'password' => Hash::make('secret123')]), 'admin');
    }

    private function formData(array $over = []): array
    {
        return $over + [
            'name' => 'Festive glow', 'discount_type' => 'percent', 'discount_value' => '20',
            'starts_on' => $this->day(2), 'ends_on' => $this->day(5), 'applies_to' => 'all', 'is_active' => '1',
        ];
    }

    // ── Which visits get it ─────────────────────────────────────────────

    public function test_the_visit_date_decides_and_both_end_days_count(): void
    {
        $this->offer();

        $this->assertSame(0.0, $this->book($this->day(1))->discount(), 'the day before');
        $this->assertSame(2000.0, $this->book($this->day(2))->discount(), 'the first day');
        $this->assertSame(2000.0, $this->book($this->day(5))->discount(), 'the last day');
        $this->assertSame(0.0, $this->book($this->day(6))->discount(), 'the day after');
    }

    public function test_booking_today_for_a_visit_after_the_offer_ends_pays_full_price(): void
    {
        $this->offer(['starts_on' => $this->day(0), 'ends_on' => $this->day(1)]);

        $this->assertSame(10000.0, $this->book($this->day(3))->total());
    }

    public function test_paused_offer_gives_nothing(): void
    {
        $this->offer(['is_active' => false]);

        $this->assertSame(10000.0, $this->book($this->day(3))->total());
    }

    public function test_offer_for_chosen_services_leaves_others_alone(): void
    {
        $this->offer([], [$this->facial]);

        $this->assertSame(8000.0, $this->book($this->day(3))->total());
        $this->assertSame(2000.0, $this->book($this->day(3), $this->haircut, '13:00')->total());
    }

    public function test_customer_gets_the_bigger_of_two_offers_and_they_do_not_stack(): void
    {
        $this->offer(['name' => 'Small', 'discount_value' => 10]);
        $this->offer(['name' => 'Big', 'discount_type' => 'fixed', 'discount_value' => 3000]);

        $a = $this->book($this->day(3));

        $this->assertSame('Big', $a->offer_name);
        $this->assertSame(7000.0, $a->total());
    }

    public function test_fixed_discount_never_goes_below_zero(): void
    {
        $this->offer(['discount_type' => 'fixed', 'discount_value' => 5000]);

        $this->assertSame(0.0, $this->book($this->day(3), $this->haircut)->total());
    }

    public function test_add_ons_are_never_discounted(): void
    {
        $addon = Service::create(['name' => 'Toner', 'slug' => 'toner', 'price' => 500, 'duration_minutes' => 5, 'is_active' => true, 'is_addon' => true]);
        $this->offer();

        $this->assertNull($addon->offerOn($this->day(3)));
    }

    // ── The price holds ─────────────────────────────────────────────────

    public function test_booked_price_survives_the_offer_being_changed_paused_or_deleted(): void
    {
        $offer = $this->offer();
        $a = $this->book($this->day(3));

        $offer->update(['discount_value' => 50]);
        $this->assertSame(8000.0, $a->fresh(['service', 'items'])->total());

        $offer->update(['is_active' => false]);
        $this->assertSame(8000.0, $a->fresh(['service', 'items'])->total());

        $offer->delete();
        $fresh = $a->fresh(['service', 'items']);
        $this->assertNull($fresh->offer_id);
        $this->assertSame('Festive glow', $fresh->offer_name);
        $this->assertSame(8000.0, $fresh->total());
    }

    public function test_bookings_with_no_offer_are_unchanged(): void
    {
        $a = $this->book($this->day(3));

        $this->assertSame(0.0, $a->discount());
        $this->assertSame(10000.0, $a->total());
        $this->assertSame(10000.0, $a->balanceDue());
    }

    public function test_changing_time_or_stylist_keeps_the_price_even_if_the_offer_has_gone(): void
    {
        $offer = $this->offer();
        $a = $this->book($this->day(3));
        $offer->update(['is_active' => false]);

        $moved = BookingService::reschedule($a, $this->facial, $this->day(3), '12:00', null, 'admin');

        $this->assertSame(8000.0, $moved->total());
    }

    public function test_moving_out_of_the_period_loses_the_offer_and_moving_in_gains_it(): void
    {
        $this->offer();
        $a = $this->book($this->day(3));

        $out = BookingService::reschedule($a, $this->facial, $this->day(8), '10:00', null, 'admin');
        $this->assertSame(10000.0, $out->total());
        $this->assertNull($out->offer_name);

        $in = BookingService::reschedule($out, $this->facial, $this->day(4), '10:00', null, 'admin');
        $this->assertSame(8000.0, $in->total());
        $this->assertSame('Festive glow', $in->offer_name);
    }

    public function test_changing_service_prices_the_new_service(): void
    {
        $this->offer([], [$this->facial]);
        $a = $this->book($this->day(3));

        $changed = BookingService::reschedule($a, $this->haircut, $this->day(3), '10:00', null, 'admin');

        $this->assertSame(2000.0, $changed->total());
        $this->assertSame(0.0, $changed->discount());
    }

    // ── Money ───────────────────────────────────────────────────────────

    public function test_customer_pays_the_discounted_balance_and_extras_are_not_discounted(): void
    {
        $this->offer();
        $a = $this->book($this->day(3));
        $a->items()->create(['name' => 'Toner', 'price' => 500, 'quantity' => 2, 'added_by' => 'stylist']);
        $a = $a->fresh(['service', 'items', 'payments']);

        $this->assertSame(9000.0, $a->total());

        PaymentService::record($a, 'payment', [['method' => 'cash', 'amount' => 9000, 'cash_received' => 9000]], 'cashier', 'Cashier station');

        $paid = $a->fresh(['service', 'items', 'payments']);
        $this->assertSame('paid', $paid->payment_status);
        $this->assertSame(0.0, $paid->balanceDue());
    }

    public function test_till_refuses_the_usual_price_on_a_discounted_bill(): void
    {
        $this->offer();
        $a = $this->book($this->day(3));

        try {
            PaymentService::record($a, 'payment', [['method' => 'cash', 'amount' => 10000, 'cash_received' => 10000]], 'cashier', 'Cashier station');
            $this->fail('The usual price should be refused on a discounted bill.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(money(8000), $e->getMessage());
        }

        $this->assertSame(0, $a->payments()->count());
    }

    public function test_revenue_sql_matches_the_bill(): void
    {
        $this->offer();
        $a = $this->book($this->day(3));
        $a->items()->create(['name' => 'Toner', 'price' => 500, 'quantity' => 1, 'added_by' => 'stylist']);

        $sql = (float) Appointment::withBillTotals()->where('appointments.id', $a->id)->sum(DB::raw(Appointment::BILL_TOTAL_SQL));

        $this->assertSame(8500.0, $sql);
        $this->assertSame($sql, $a->fresh(['service', 'items'])->total());
    }

    public function test_revenue_sql_never_goes_negative_if_the_price_drops_later(): void
    {
        $this->offer(['discount_type' => 'fixed', 'discount_value' => 1500]);
        $a = $this->book($this->day(3), $this->haircut);
        $this->haircut->update(['price' => 1000]);

        $sql = (float) Appointment::withBillTotals()->where('appointments.id', $a->id)->sum(DB::raw(Appointment::BILL_TOTAL_SQL));

        $this->assertSame(0.0, $sql);
        $this->assertSame(0.0, $a->fresh(['service', 'items'])->total());
    }

    public function test_percent_advance_is_worked_out_on_the_discounted_price(): void
    {
        $this->facial->update(['advance_type' => 'percent', 'advance_value' => 50]);
        $this->offer();

        $this->assertSame(4000.0, $this->book($this->day(3))->advanceRequired());
        $this->assertSame(5000.0, $this->book($this->day(8))->advanceRequired());
    }

    public function test_fixed_advance_is_never_more_than_the_discounted_price(): void
    {
        $this->facial->update(['advance_type' => 'fixed', 'advance_value' => 5000]);
        $this->offer(['discount_type' => 'percent', 'discount_value' => 80]);

        $this->assertSame(2000.0, $this->book($this->day(3))->advanceRequired());
    }

    // ── Online booking ──────────────────────────────────────────────────

    public function test_online_booking_gets_the_price_for_its_date_from_the_server(): void
    {
        $this->offer();

        $inside = $this->postJson(route('booking.store'), [
            'name' => 'Dilini', 'phone' => '0771234567', 'email' => 'd@x.lk', 'service_id' => $this->facial->id,
            'date' => $this->day(3), 'start_time' => '10:00',
        ])->assertOk()->assertJsonPath('offer', 'Festive glow');
        $this->assertStringContainsString('8,000', $inside->json('price'));

        $outside = $this->postJson(route('booking.store'), [
            'name' => 'Dilini', 'phone' => '0771234567', 'email' => 'd@x.lk', 'service_id' => $this->facial->id,
            'date' => $this->day(8), 'start_time' => '10:00',
        ])->assertOk()->assertJsonPath('offer', null);
        $this->assertStringContainsString('10,000', $outside->json('price'));
    }

    public function test_a_customer_cannot_send_their_own_price_or_discount(): void
    {
        $this->postJson(route('booking.store'), [
            'name' => 'Dilini', 'phone' => '0771234567', 'email' => 'd@x.lk', 'service_id' => $this->facial->id,
            'date' => $this->day(8), 'start_time' => '10:00', 'discount_amount' => 9999, 'offer_name' => 'Fake', 'price' => 1,
        ])->assertOk();

        $a = Appointment::latest('id')->first();
        $this->assertSame(0.0, $a->discount());
        $this->assertNull($a->offer_name);
    }

    public function test_booking_page_can_price_every_date(): void
    {
        $this->offer();

        $page = $this->get(route('booking'))->assertOk();

        $page->assertSee('id="offerNote"', false);
        $config = json_decode(Str::before(Str::after($page->getContent(), 'id="booking-config">'), '</script>'), true);
        $window = $config['services'][$this->facial->id]['offers'][0];
        $this->assertSame($this->day(2), $window['from']);
        $this->assertSame($this->day(5), $window['to']);
        $this->assertSame(2000, (int) $window['amount']);
        $this->assertStringContainsString('8,000', $window['price']);
    }

    public function test_customer_changing_a_booking_is_quoted_and_charged_correctly(): void
    {
        $this->offer();
        $a = $this->book($this->day(3));
        $this->withSession(['manage_booking' => ['id' => $a->id, 'at' => now()->timestamp]]);

        $this->get(route('booking.edit'))->assertOk()->assertSee('8,000');

        $this->postJson(route('booking.update'), ['service_id' => $this->facial->id, 'date' => $this->day(8), 'start_time' => '10:00'])
            ->assertOk()->assertJsonPath('offer', null);

        $this->assertSame(10000.0, $a->fresh(['service', 'items'])->total());
    }

    public function test_staff_and_walk_in_bookings_get_the_offer_too(): void
    {
        $this->offer(['starts_on' => $this->day(0), 'ends_on' => $this->day(0)]);

        $walkIn = BookingService::book($this->facial, [
            'name' => 'Walk In', 'phone' => '0779990000', 'date' => $this->day(0), 'start' => '12:00', 'staff_id' => $this->amaya->id,
            'type' => 'counter', 'status' => 'in_progress', 'walk_in' => true,
        ]);

        $this->assertSame(2000.0, $walkIn->fresh(['service', 'items'])->discount());
    }

    // ── Website ─────────────────────────────────────────────────────────

    public function test_website_shows_a_running_offer_with_the_old_price_crossed_out(): void
    {
        $this->offer(['starts_on' => $this->day(0), 'ends_on' => $this->day(5)]);

        $this->get(route('services'))->assertOk()
            ->assertSee('price-was', false)
            ->assertSee('8,000')
            ->assertSee('20% off');

        $this->get(route('home'))->assertOk()->assertSee('Festive glow')->assertSee(route('offers'));
        $this->get(route('offers'))->assertOk()->assertSee('Festive glow')->assertSee('On all services');
    }

    public function test_website_stays_quiet_about_offers_that_are_not_running(): void
    {
        $this->offer();                                                                 // starts in 2 days
        $this->offer(['is_active' => false, 'starts_on' => $this->day(0)]);             // paused
        $this->offer(['starts_on' => $this->day(-5), 'ends_on' => $this->day(-1)]);     // ended

        $this->get(route('services'))->assertOk()->assertDontSee('price-was', false)->assertSee('10,000');
        $this->get(route('home'))->assertOk()->assertDontSee('Festive glow')->assertDontSee(route('offers'));
        $this->get(route('offers'))->assertOk()->assertSee('No offers right now');
    }

    public function test_offer_starts_and_ends_on_its_own(): void
    {
        $this->offer();
        [$first, $last, $after] = [$this->day(2), $this->day(5), $this->day(6)];
        $at = function (string $moment) {
            $this->travelTo(Carbon::parse($moment, config('app.timezone')));
            app(OfferPricing::class)->flush();

            return $this->get(route('offers'));
        };

        $this->get(route('offers'))->assertDontSee('Festive glow');
        $at($first . ' 00:01')->assertSee('Festive glow');
        $at($last . ' 23:59')->assertSee('Festive glow');
        $at($after . ' 00:01')->assertDontSee('Festive glow');
    }

    public function test_offer_whose_services_are_all_hidden_is_not_advertised(): void
    {
        $this->offer(['starts_on' => $this->day(0)], [$this->facial]);
        $this->facial->update(['is_active' => false]);

        $this->get(route('offers'))->assertOk()->assertSee('No offers right now');
    }

    public function test_category_price_shows_the_offer(): void
    {
        $cat = ServiceCategory::create(['name' => 'Skin', 'slug' => 'skin', 'is_active' => true]);
        $this->facial->update(['service_category_id' => $cat->id]);
        $this->offer(['starts_on' => $this->day(0)]);

        $this->get(route('services.category', 'skin'))->assertOk()->assertSee('from ' . money(8000));
    }

    // ── Bills ───────────────────────────────────────────────────────────

    public function test_admin_booking_page_receipt_and_csv_show_the_offer(): void
    {
        $this->offer();
        $a = $this->book($this->day(3));
        $this->signInOwner();

        $this->get(route('admin.appointments.show', $a))->assertOk()->assertSee('Festive glow')->assertSee('− ' . money(2000));
        $this->get(route('admin.appointments.receipt', $a))->assertOk()->assertSee('Festive glow')->assertSee('− ' . money(2000))->assertSee(money(8000));

        $csv = $this->get(route('admin.appointments.export', ['scope' => 'all']))->streamedContent();
        $this->assertStringContainsString('Offer,Discount', $csv);
        $this->assertStringContainsString('Festive glow', $csv);
    }

    public function test_cashier_and_customer_screens_show_the_offer(): void
    {
        $this->offer(['starts_on' => $this->day(0)]);
        $a = $this->book($this->day(0), null, '14:00');

        $this->signInOwner();
        $this->postJson(route('cashier.lookup'), ['ref' => $a->appointment_no])->assertOk()
            ->assertJsonPath('appointment.offer_name', 'Festive glow')
            ->assertJsonPath('appointment.discount_label', '− ' . money(2000))
            ->assertJsonPath('appointment.total', 8000);

        $this->withSession(['manage_booking' => ['id' => $a->id, 'at' => now()->timestamp]])
            ->get(route('booking.manage'))->assertOk()->assertSee('Festive glow')->assertSee(money(8000));
    }

    public function test_confirmation_email_shows_the_offer(): void
    {
        Setting::updateOrCreate(['key' => 'salon_name'], ['value' => 'Falothes']);
        setting('__flush');
        $this->offer();
        $a = $this->book($this->day(3));

        $mail = (new BookingConfirmed($a, 'confirmed'))->render();

        $this->assertStringContainsString('Festive glow', $mail);
        $this->assertStringContainsString(e(money(8000)), $mail);
    }

    // ── Managing offers ─────────────────────────────────────────────────

    public function test_owner_creates_an_offer_for_chosen_services(): void
    {
        $this->signInOwner();

        $this->post(route('admin.offers.store'), $this->formData(['applies_to' => 'selected', 'services' => [$this->facial->id]]))
            ->assertRedirect(route('admin.offers.index'));

        $offer = Offer::first();
        $this->assertSame('selected', $offer->applies_to);
        $this->assertSame([$this->facial->id], $offer->services()->pluck('services.id')->all());
        $this->assertDatabaseHas('system_logs', ['action' => 'offer.created']);

        $this->assertSame(8000.0, $this->book($this->day(3))->total());
    }

    public function test_offer_screens_render(): void
    {
        ServiceCategory::create(['name' => 'Skin', 'slug' => 'skin', 'is_active' => true]);
        $this->facial->update(['service_category_id' => ServiceCategory::first()->id]);
        $offer = $this->offer([], [$this->facial]);
        $this->book($this->day(3));
        $this->signInOwner();

        $this->get(route('admin.offers.index'))->assertOk()->assertSee('Festive glow')->assertSee('Upcoming')->assertSee(money(2000) . ' off');
        $this->get(route('admin.offers.create'))->assertOk()->assertSee('Gold Facial');
        $this->get(route('admin.offers.edit', $offer))->assertOk()->assertSee('Festive glow');
    }

    #[DataProvider('badForms')]
    public function test_bad_offers_are_refused(array $over, string $field): void
    {
        $this->signInOwner();
        $data = array_map(fn ($v) => is_int($v) ? $this->day($v) : $v, $over);

        $this->post(route('admin.offers.store'), $this->formData($data))->assertSessionHasErrors($field);

        $this->assertSame(0, Offer::count());
    }

    public static function badForms(): array
    {
        return [
            'ends before it starts' => [['starts_on' => 5, 'ends_on' => 2], 'ends_on'],
            'already over'          => [['starts_on' => -5, 'ends_on' => -1], 'ends_on'],
            'no end date'           => [['ends_on' => ''], 'ends_on'],
            'over 100 percent'      => [['discount_value' => '101'], 'discount_value'],
            'zero discount'         => [['discount_value' => '0'], 'discount_value'],
            'negative discount'     => [['discount_value' => '-5'], 'discount_value'],
            'no name'               => [['name' => ''], 'name'],
            'bad type'              => [['discount_type' => 'free'], 'discount_type'],
            'chosen but none ticked' => [['applies_to' => 'selected'], 'services'],
        ];
    }

    public function test_add_on_cannot_be_chosen(): void
    {
        $addon = Service::create(['name' => 'Toner', 'slug' => 'toner', 'price' => 500, 'duration_minutes' => 5, 'is_active' => true, 'is_addon' => true]);
        $this->signInOwner();

        $this->post(route('admin.offers.store'), $this->formData(['applies_to' => 'selected', 'services' => [$addon->id]]))
            ->assertSessionHasErrors('services.0');
    }

    public function test_an_ended_offer_can_still_be_corrected(): void
    {
        $offer = $this->offer(['starts_on' => $this->day(-5), 'ends_on' => $this->day(-1)]);
        $this->signInOwner();

        $this->put(route('admin.offers.update', $offer), $this->formData(['name' => 'Renamed', 'starts_on' => $this->day(-5), 'ends_on' => $this->day(-1)]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $offer->fresh()->name);
    }

    public function test_switching_to_all_services_clears_the_chosen_list(): void
    {
        $offer = $this->offer([], [$this->facial]);
        $this->signInOwner();

        $this->put(route('admin.offers.update', $offer), $this->formData())->assertSessionHasNoErrors();

        $this->assertSame(0, $offer->services()->count());
        $this->assertSame('all', $offer->fresh()->applies_to);
    }

    public function test_deleting_an_offer_is_logged_and_keeps_its_bookings(): void
    {
        $offer = $this->offer();
        $a = $this->book($this->day(3));
        $this->signInOwner();

        $this->delete(route('admin.offers.destroy', $offer))->assertRedirect();

        $this->assertSame(0, Offer::count());
        $this->assertSame(8000.0, $a->fresh(['service', 'items'])->total());
        $this->assertSame('warning', SystemLog::where('action', 'offer.deleted')->first()->level);
    }

    public function test_managing_offers_needs_the_permission(): void
    {
        $offer = $this->offer();
        $limited = Admin::create(['name' => 'Staff', 'email' => 's@s.lk', 'password' => Hash::make('secret123'), 'permissions' => ['services.manage']]);
        $this->actingAs($limited, 'admin');

        $this->get(route('admin.offers.index'))->assertForbidden();
        $this->post(route('admin.offers.store'), $this->formData())->assertForbidden();
        $this->delete(route('admin.offers.destroy', $offer))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertDontSee(route('admin.offers.index'));

        $this->flushSession();
        $allowed = Admin::create(['name' => 'Manager', 'email' => 'm@s.lk', 'password' => Hash::make('secret123'), 'permissions' => ['offers.manage']]);
        $this->actingAs($allowed, 'admin');

        $this->get(route('admin.offers.index'))->assertOk();
        $this->get(route('admin.dashboard'))->assertSee(route('admin.offers.index'));
    }
}
