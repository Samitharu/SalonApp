<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use Database\Seeders\FalothesMenuSeeder;
use Database\Seeders\SalonMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FalothesMenuSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_fresh_server_gets_the_name_menu_and_photos(): void
    {
        Setting::create(['key' => 'salon_name', 'value' => 'Negombo Saloon']);

        $this->seed(FalothesMenuSeeder::class);

        $this->assertSame('Falothes', Setting::where('key', 'salon_name')->value('value'));
        $this->assertSame(['hair-services', 'bridal', 'makeup', 'skin', 'nails'], ServiceCategory::orderBy('sort_order')->orderBy('id')->pluck('slug')->all());
        $this->assertSame(54, Service::where('is_addon', false)->count());
        $this->assertSame(4, Service::where('is_addon', true)->count());

        $kandyan = Service::where('slug', 'kandyan-bridal-dressing')->first();
        $this->assertSame('bridal', $kandyan->category->slug);
        $this->assertSame(19500.0, $kandyan->advanceAmount());
        Storage::disk('public')->assertExists($kandyan->image);
        Storage::disk('public')->assertExists(ServiceCategory::where('slug', 'bridal')->value('image'));

        $this->get('/')->assertOk()->assertSee('Falothes')->assertDontSee('Negombo Saloon')->assertSee('Hair Services');
    }

    public function test_updates_an_existing_menu_without_duplicates_and_can_run_again(): void
    {
        $this->seed(SalonMenuSeeder::class);
        Service::where('slug', 'kandyan-bridal-dressing')->update(['price' => 1]);

        $this->seed(FalothesMenuSeeder::class);
        $this->seed(FalothesMenuSeeder::class);

        $this->assertSame(Service::count(), Service::distinct()->count('name'), 'No duplicate services');
        $this->assertSame(1, ServiceCategory::where('slug', 'bridal')->count());
        $this->assertSame('65000.00', Service::where('slug', 'kandyan-bridal-dressing')->value('price'));
        $this->assertTrue(Service::where('slug', 'hair-cut')->exists());
    }
}
