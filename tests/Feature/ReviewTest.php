<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Feedback;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Public "Rate your visit" page and the receipt QR link. */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private function visit(array $extra = []): Appointment
    {
        $customer = Customer::create(['name' => 'Kamal Perera', 'phone' => '0771234567']);
        $service = Service::create(['name' => 'Haircut', 'slug' => 'haircut', 'price' => 2000, 'duration_minutes' => 60, 'is_active' => true]);
        $staff = Staff::create(['name' => 'Amaya', 'is_active' => true]);

        return Appointment::create(array_merge([
            'appointment_no' => 'RATE0001', 'customer_id' => $customer->id, 'service_id' => $service->id, 'staff_id' => $staff->id,
            'appointment_date' => now()->subDay()->toDateString(), 'slot_start' => '10:00:00', 'slot_end' => '11:00:00',
            'type' => 'online', 'status' => 'completed', 'payment_status' => 'paid', 'amount_paid' => 2000,
        ], $extra));
    }

    public function test_page_is_linked_and_starts_with_lookup(): void
    {
        $this->get('/')->assertSee(route('review.create'), false);
        $this->get(route('review.create'))->assertOk()->assertSee('Find your visit')->assertSee('Booking reference');
    }

    public function test_receipt_link_opens_the_rating_form_without_typing(): void
    {
        $appt = $this->visit();

        $this->get($appt->feedbackUrl())->assertRedirect(route('review.create'));
        $this->get(route('review.create'))->assertOk()
            ->assertSee('How was your visit?')->assertSee('Haircut')->assertSee('with Amaya');

        $this->post(route('review.store'), ['rating' => 5, 'comment' => 'Brilliant cut, thank you!', 'allow_public' => '1'])
            ->assertRedirect(route('review.create'))->assertSessionHas('success');

        $fb = Feedback::first();
        $this->assertSame(5, $fb->rating);
        $this->assertSame('Brilliant cut, thank you!', $fb->comment);
        $this->assertTrue($fb->allow_public);
        $this->assertSame($appt->staff_id, $fb->staff_id);

        $this->get(route('review.create'))->assertSee('Thank you, Kamal!')->assertSee('Brilliant cut, thank you!')
            ->assertDontSee('How was your visit?');
    }

    public function test_tampered_or_expired_links_are_rejected(): void
    {
        $appt = $this->visit();
        $other = Appointment::create(array_merge($appt->only(['customer_id', 'service_id', 'staff_id', 'appointment_date', 'slot_start', 'slot_end', 'type', 'status']), ['appointment_no' => 'RATE0002']));

        // Signature for one visit can't be reused for another.
        $tampered = str_replace('/feedback/visit/' . $appt->id, '/feedback/visit/' . $other->id, $appt->feedbackUrl());
        $this->get($tampered)->assertForbidden();
        $this->get('/feedback/visit/' . $appt->id)->assertForbidden();

        $url = $appt->feedbackUrl();
        $this->travel(31)->days();
        $this->get($url)->assertForbidden();
    }

    public function test_receipt_link_can_rate_but_not_manage_the_booking(): void
    {
        $appt = $this->visit(['status' => 'confirmed', 'payment_status' => 'unpaid', 'appointment_date' => now()->addDays(5)->toDateString()]);

        $this->get($appt->feedbackUrl());

        // The manage page still asks for reference + phone, and cancelling is refused.
        $this->get(route('booking.manage'))->assertSee('Find my booking')->assertDontSee('RATE0001');
        $this->post(route('booking.cancel'))->assertSessionHasErrors();
        $this->assertSame('confirmed', $appt->fresh()->status);

        // And it can't be reviewed before it's completed.
        $this->get(route('review.create'))->assertSee('once your appointment is completed');
        $this->post(route('review.store'), ['rating' => 5])->assertSessionHas('error');
        $this->assertSame(0, Feedback::count());
    }

    public function test_lookup_with_reference_and_phone(): void
    {
        $this->visit();

        $this->post(route('review.lookup'), ['reference' => 'rate0001', 'phone' => '0710000000'])->assertSessionHasErrors('reference');
        $this->get(route('review.create'))->assertSee('Find your visit');

        $this->post(route('review.lookup'), ['reference' => 'rate0001', 'phone' => '077 123 4567'])->assertRedirect(route('review.create'));
        $this->get(route('review.create'))->assertSee('How was your visit?');

        $this->post(route('review.forget'));
        $this->get(route('review.create'))->assertSee('Find your visit');
    }

    public function test_rating_is_required_and_only_once(): void
    {
        $appt = $this->visit();
        $this->get($appt->feedbackUrl());

        $this->post(route('review.store'), ['comment' => 'No stars'])->assertSessionHasErrors('rating', null, 'feedback');
        $this->post(route('review.store'), ['rating' => 7])->assertSessionHasErrors('rating', null, 'feedback');
        $this->assertSame(0, Feedback::count());

        $this->post(route('review.store'), ['rating' => 4]);
        $this->post(route('review.store'), ['rating' => 1])->assertSessionHas('error');
        $this->assertSame(1, Feedback::count());
        $this->assertSame(4, Feedback::first()->rating);
        $this->assertNull(Feedback::first()->comment);
    }

    public function test_cashier_gets_a_scan_to_rate_link_until_reviewed(): void
    {
        $appt = $this->visit(['payment_status' => 'unpaid', 'amount_paid' => null, 'appointment_date' => now()->toDateString(), 'job_number' => 'JRATE1']);
        $this->actingAs(Admin::create(['is_owner' => true, 'name' => 'Owner', 'email' => 'o@s.lk', 'password' => Hash::make('secret123')]), 'admin');

        $url = $this->postJson(route('cashier.pay', $appt), ['payment_method' => 'card'])->assertOk()->json('appointment.feedback_url');
        $this->assertNotNull($url);
        $this->assertStringContainsString('/feedback/visit/' . $appt->id, $url);
        $this->assertStringContainsString('signature=', $url);

        Feedback::create(['appointment_id' => $appt->id, 'customer_id' => $appt->customer_id, 'rating' => 5]);
        $this->postJson(route('cashier.lookup'), ['ref' => 'JRATE1'])->assertOk()->assertJsonPath('appointment.feedback_url', null);
    }
}
