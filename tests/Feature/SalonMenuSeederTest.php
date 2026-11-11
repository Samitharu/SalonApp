<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use Database\Seeders\SalonMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SalonMenuSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_seeds_categories_services_and_addons_ready_for_the_homepage(): void
    {
        $this->seed(SalonMenuSeeder::class);

        $this->assertSame(6, ServiceCategory::count());
        $this->assertSame(['bridal', 'hair', 'makeup', 'skin', 'nails', 'mens-grooming'], ServiceCategory::showcase()->get()->sortBy('sort_order')->pluck('slug')->all());
        $this->assertTrue(ServiceCategory::whereNull('icon')->doesntExist(), 'Every category has an icon');
        $this->assertGreaterThanOrEqual(50, Service::bookable()->count());
        $this->assertSame(4, Service::where('is_addon', true)->count());

        $kandyan = Service::where('name', 'Kandyan Bridal Dressing')->first();
        $this->assertSame(19500.0, $kandyan->advanceAmount(), '30% advance on bridal');
        $this->assertSame(30, (int) $kandyan->buffer_minutes);
        $this->assertSame(0.0, Service::where('name', 'Eyebrow Threading')->first()->advanceAmount());

        $this->get('/')->assertOk()->assertSee('Kandyan Bridal Dressing')->assertSee("Men's Grooming");
        $this->get('/services/bridal')->assertOk()->assertSee('Indian Bridal Dressing');
    }

    public function test_services_get_their_illustration_but_uploaded_photos_are_kept(): void
    {
        Storage::fake('public');
        // A service the salon added with its own photo, and one without.
        Service::create(['name' => 'Hair Cut', 'slug' => 'hair-cut', 'price' => 1500, 'duration_minutes' => 25, 'is_active' => true, 'image' => 'services/own-photo.jpg']);
        Service::create(['name' => 'Hair Cut with facial', 'slug' => 'hair-cut-with-facial', 'price' => 2500, 'duration_minutes' => 60, 'is_active' => true]);

        $this->seed(SalonMenuSeeder::class);

        $kandyan = Service::where('name', 'Kandyan Bridal Dressing')->first();
        $this->assertSame('services/menu/kandyan-bridal-dressing.webp', $kandyan->image);
        Storage::disk('public')->assertExists($kandyan->image);
        $this->assertSame('services/own-photo.jpg', Service::where('name', 'Hair Cut')->value('image'), 'Uploaded photo kept');
        $this->assertSame('services/menu/hair-cut-with-facial.webp', Service::where('name', 'Hair Cut with facial')->value('image'), 'Blank image filled');
        $this->assertSame(0, Service::bookable()->whereNull('image')->count(), 'Every bookable package has an image');
        $this->assertSame(4, Service::where('is_addon', true)->whereNull('image')->count(), 'Add-ons need no image');

        // Each illustration is a real, small WebP.
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('public')->get($kandyan->image));
        $this->assertSame([1200, 750, IMAGETYPE_WEBP], [$w, $h, $type]);
    }

    public function test_running_again_adds_nothing_and_keeps_admin_changes(): void
    {
        // An existing install: the admin's own category and service, plus an edited price.
        $hair = ServiceCategory::create(['name' => 'Hair Services', 'slug' => 'hair-services', 'tagline' => 'Our own line']);
        Service::create(['service_category_id' => $hair->id, 'name' => 'Hair Cut', 'slug' => 'hair-cut', 'price' => 1500, 'duration_minutes' => 25, 'is_active' => true]);

        $this->seed(SalonMenuSeeder::class);
        Service::where('name', 'Party Make-up')->update(['price' => 9999]);
        $counts = [ServiceCategory::count(), Service::count()];

        $this->seed(SalonMenuSeeder::class);

        $this->assertSame($counts, [ServiceCategory::count(), Service::count()], 'Second run adds nothing');
        $this->assertSame(6, ServiceCategory::count(), 'Existing "Hair Services" is reused, not duplicated');
        $this->assertSame('Hair Services', $hair->fresh()->name);
        $this->assertSame('Our own line', $hair->fresh()->tagline, 'Admin text is kept');
        $this->assertSame('scissors', $hair->fresh()->icon, 'Blank icon is filled in');
        $this->assertSame($hair->id, Service::where('name', "Ladies' Haircut & Blow-dry")->value('service_category_id'));
        $this->assertSame('9999.00', Service::where('name', 'Party Make-up')->value('price'));
        $this->assertSame(1, Service::where('name', 'Hair Cut')->count());
    }
}
