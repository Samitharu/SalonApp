<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\GalleryImage;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SystemLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Homepage categories, category pages with hero photos, and the gallery.
 */
class GalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs(Admin::create(['is_owner' => true, 'name' => 'Owner', 'email' => 'owner@salon.test', 'password' => Hash::make('secret123')]), 'admin');
    }

    private function category(string $name, array $extra = []): ServiceCategory
    {
        return ServiceCategory::create(array_merge(['name' => $name, 'slug' => Str::slug($name)], $extra));
    }

    private function service(?ServiceCategory $cat, string $name, float $price = 2500, array $extra = []): Service
    {
        return Service::create(array_merge([
            'service_category_id' => $cat?->id, 'name' => $name, 'slug' => Str::slug($name . uniqid()),
            'price' => $price, 'duration_minutes' => 60, 'is_active' => true,
        ], $extra));
    }

    private function photo(?ServiceCategory $cat = null, array $extra = []): GalleryImage
    {
        return GalleryImage::create(array_merge([
            'image' => 'gallery/' . Str::random(8) . '.webp', 'width' => 1200, 'height' => 800, 'service_category_id' => $cat?->id,
        ], $extra));
    }

    // ── Homepage categories ────────────────────────────────────────────

    public function test_homepage_shows_categories_with_bookable_services_in_order(): void
    {
        $bridal = $this->category('Bridal', ['sort_order' => 1, 'tagline' => 'For your big day']);
        $hair = $this->category('Hair', ['sort_order' => 2]);
        $this->category('Empty');                                  // no services
        $hidden = $this->category('Hidden', ['is_active' => false]);
        $addonsOnly = $this->category('Extras');

        $this->service($bridal, 'Bridal dressing', 45000);
        $this->service($bridal, 'Bridal trial', 8000);
        $this->service($hair, 'Haircut', 2500);
        $this->service($hidden, 'Secret', 1000);
        $this->service($addonsOnly, 'Toner', 900, ['is_addon' => true]);

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Explore by category', 'Bridal', '2 packages', 'from LKR 8,000.00', 'Hair', '1 package'])
            ->assertSee(route('services.category', 'bridal'))
            ->assertDontSee(route('services.category', 'empty'))
            ->assertDontSee(route('services.category', 'hidden'))
            ->assertDontSee(route('services.category', 'extras'));
    }

    public function test_homepage_strip_lists_categories_and_overview_shows_by_default(): void
    {
        $bridal = $this->category('Bridal', ['icon' => 'ring', 'tagline' => 'For your big day', 'image' => 'categories/bridal.webp']);
        $nails = $this->category('Nails');
        $this->service($bridal, 'Bridal dressing', 45000);
        $this->service($nails, 'Gel manicure', 3500);

        $res = $this->get('/')->assertOk()
            ->assertSee('aria-label="Service categories"', false)
            ->assertSee('data-cat-select="bridal"', false)
            ->assertSee('fa-solid fa-ring', false)
            ->assertSee('For your big day')
            ->assertSee('data-mode="slides"', false)
            ->assertSee('data-cat-panel="" data-selected', false)
            ->assertDontSee('Why choose us');

        // Each category's packages are in the page (hidden) so switching is instant…
        $res->assertSee('data-cat-panel="bridal" hidden', false)->assertSee('Gel manicure');
        // …but its hero photo isn't downloaded until it's picked.
        $res->assertSee('data-src="' . asset('storage/categories/bridal.webp') . '"', false)
            ->assertDontSee('src="' . asset('storage/categories/bridal.webp') . '" fetchpriority', false);
    }

    public function test_homepage_opens_on_a_category_from_the_url(): void
    {
        $bridal = $this->category('Bridal', ['image' => 'categories/bridal.webp', 'tagline' => 'Timeless looks']);
        $hair = $this->category('Hair');
        $this->service($bridal, 'Bridal dressing', 45000);
        $this->service($hair, 'Haircut', 2500);

        $this->get('/?category=bridal')->assertOk()
            ->assertSee('data-mode="category"', false)
            ->assertSee('class="lux-slide lux-cat-slide is-active" data-cat-hero="bridal"', false)
            ->assertSee('src="' . asset('storage/categories/bridal.webp') . '" fetchpriority="high"', false)
            ->assertSee('data-cat-panel="bridal" data-selected', false)
            ->assertSee('data-cat-panel="" hidden', false)
            ->assertSee('data-cat-panel="hair" hidden', false)
            ->assertSee('data-cat-select="bridal" aria-current="true"', false)
            ->assertSee(route('booking', ['service' => Service::where('name', 'Bridal dressing')->value('id')]), false);

        // Unknown or hidden categories fall back to the overview.
        $this->get('/?category=nope')->assertOk()->assertSee('data-mode="slides"', false)->assertSee('data-cat-panel="" data-selected', false);
    }

    public function test_admin_picks_a_category_icon(): void
    {
        $cat = $this->category('Nails');
        $admin = $this->actingAsAdmin();

        $admin->get("/admin/categories/{$cat->id}/edit")->assertOk()->assertSee('fa-solid fa-hand-sparkles', false);
        $admin->put("/admin/categories/{$cat->id}", ['name' => 'Nails', 'is_active' => '1', 'icon' => 'nails'])->assertRedirect();
        $this->assertSame('nails', $cat->fresh()->icon);
        $this->assertSame('fa-solid fa-hand-sparkles', $cat->fresh()->iconClass());

        $admin->put("/admin/categories/{$cat->id}", ['name' => 'Nails', 'is_active' => '1', 'icon' => 'fa-skull" onclick="x'])->assertSessionHasErrors('icon');
        $this->assertSame('nails', $cat->fresh()->icon);

        $admin->put("/admin/categories/{$cat->id}", ['name' => 'Nails', 'is_active' => '1', 'icon' => ''])->assertRedirect();
        $this->assertNull($cat->fresh()->icon);
    }

    public function test_homepage_falls_back_to_service_tiles_without_categories(): void
    {
        $this->service(null, 'Walk-in trim');

        $this->get('/')->assertOk()->assertDontSee('Explore by category')->assertSee('Our signature services')->assertSee('Walk-in trim');
    }

    // ── Category page ──────────────────────────────────────────────────

    public function test_category_page_shows_hero_packages_and_its_photos(): void
    {
        $bridal = $this->category('Bridal', ['image' => 'categories/bridal.webp', 'tagline' => 'Timeless looks', 'description' => 'Everything for the wedding day.']);
        $hair = $this->category('Hair');
        $this->service($bridal, 'Bridal dressing', 45000, ['advance_type' => 'percent', 'advance_value' => 30]);
        $this->service($bridal, 'Old package', 100, ['is_active' => false]);
        $this->service($hair, 'Haircut');
        $this->photo($bridal, ['caption' => 'Kandyan bride']);
        $this->photo($hair, ['caption' => 'Bob cut']);

        $this->get('/services/bridal')->assertOk()
            ->assertSee('storage/categories/bridal.webp', false)
            ->assertSee('Timeless looks')
            ->assertSee('Everything for the wedding day.')
            ->assertSee('Bridal dressing')
            ->assertSee('30% advance')
            ->assertSee(route('booking', ['service' => Service::where('name', 'Bridal dressing')->value('id')]), false)
            ->assertDontSee('Old package')
            ->assertSee('Kandyan bride')
            ->assertDontSee('Bob cut')
            ->assertSee(route('services.category', 'hair'));             // category switcher
    }

    public function test_category_page_is_404_when_hidden_or_without_packages(): void
    {
        $hidden = $this->category('Hidden', ['is_active' => false]);
        $this->service($hidden, 'Secret');
        $this->category('Empty');

        $this->get('/services/hidden')->assertNotFound();
        $this->get('/services/empty')->assertNotFound();
        $this->get('/services/nope')->assertNotFound();
    }

    public function test_services_menu_links_each_category_to_its_page(): void
    {
        $nails = $this->category('Nails');
        $this->service($nails, 'Gel manicure');

        $this->get('/services')->assertOk()->assertSee(route('services.category', 'nails'));
    }

    // ── Admin: category hero photo ─────────────────────────────────────

    public function test_admin_uploads_category_hero_which_is_resized_to_webp(): void
    {
        $this->actingAsAdmin()->post('/admin/categories', [
            'name' => 'Bridal', 'tagline' => 'For your big day', 'is_active' => '1',
            'image' => UploadedFile::fake()->image('hero.jpg', 3200, 1600),
        ])->assertRedirect(route('admin.categories.index'));

        $cat = ServiceCategory::where('name', 'Bridal')->first();
        $this->assertSame('For your big day', $cat->tagline);
        $this->assertTrue($cat->is_active);
        $this->assertStringEndsWith('.webp', $cat->image);
        Storage::disk('public')->assertExists($cat->image);
        [$w] = getimagesizefromstring(Storage::disk('public')->get($cat->image));
        $this->assertSame(2000, $w);
    }

    public function test_replacing_or_removing_category_hero_deletes_old_file(): void
    {
        Storage::disk('public')->put('categories/old.webp', 'x');
        $cat = $this->category('Hair', ['image' => 'categories/old.webp']);

        $this->actingAsAdmin()->put("/admin/categories/{$cat->id}", [
            'name' => 'Hair', 'is_active' => '1', 'image' => UploadedFile::fake()->image('new.png', 800, 400),
        ])->assertRedirect();
        Storage::disk('public')->assertMissing('categories/old.webp');
        $new = $cat->fresh()->image;
        Storage::disk('public')->assertExists($new);

        $this->put("/admin/categories/{$cat->id}", ['name' => 'Hair', 'is_active' => '1', 'remove_image' => '1'])->assertRedirect();
        $this->assertNull($cat->fresh()->image);
        Storage::disk('public')->assertMissing($new);
    }

    public function test_admin_can_hide_a_category_from_the_website(): void
    {
        $cat = $this->category('Hair');
        $this->service($cat, 'Haircut');

        $this->actingAsAdmin()->put("/admin/categories/{$cat->id}", ['name' => 'Hair', 'is_active' => '0'])->assertRedirect();

        $this->assertFalse($cat->fresh()->is_active);
        $this->get('/services/hair')->assertNotFound();
    }

    public function test_category_photo_must_be_an_image(): void
    {
        $this->actingAsAdmin()->post('/admin/categories', [
            'name' => 'Hair', 'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('service_categories', ['name' => 'Hair']);
    }

    // ── Admin: gallery ─────────────────────────────────────────────────

    public function test_admin_uploads_several_photos_with_thumbnails(): void
    {
        $bridal = $this->category('Bridal');

        $this->actingAsAdmin()->post('/admin/gallery', [
            'images' => [UploadedFile::fake()->image('a.jpg', 4000, 3000), UploadedFile::fake()->image('b.png', 500, 700)],
            'service_category_id' => $bridal->id,
            'caption' => 'Wedding season',
        ])->assertRedirect(route('admin.gallery.index'))->assertSessionHas('success', '2 photos uploaded.');

        $this->assertSame(2, GalleryImage::count());
        $big = GalleryImage::where('width', 1800)->first();
        $this->assertNotNull($big, 'Large photo is scaled to 1800px wide');
        $this->assertSame(1350, $big->height);
        $this->assertNotNull($big->thumb);
        Storage::disk('public')->assertExists([$big->image, $big->thumb]);
        [$tw] = getimagesizefromstring(Storage::disk('public')->get($big->thumb));
        $this->assertSame(640, $tw);

        $small = GalleryImage::where('width', 500)->first();
        $this->assertNull($small->thumb, 'Small photos need no thumbnail');
        $this->assertSame('Wedding season', $small->caption);
        $this->assertSame($bridal->id, $small->service_category_id);

        $this->assertDatabaseHas('system_logs', ['action' => 'gallery.uploaded']);
    }

    public function test_gallery_upload_validation(): void
    {
        $admin = $this->actingAsAdmin();

        $admin->post('/admin/gallery', [])->assertSessionHasErrors('images');
        $admin->post('/admin/gallery', ['images' => [UploadedFile::fake()->create('virus.exe', 10)]])->assertSessionHasErrors('images.0');
        $admin->post('/admin/gallery', ['images' => array_fill(0, 13, UploadedFile::fake()->image('x.jpg', 10, 10))])->assertSessionHasErrors('images');
        $admin->post('/admin/gallery', ['images' => [UploadedFile::fake()->image('x.jpg', 10, 10)], 'service_category_id' => 999])->assertSessionHasErrors('service_category_id');

        $this->assertSame(0, GalleryImage::count());
    }

    public function test_admin_edits_and_deletes_a_photo(): void
    {
        Storage::disk('public')->put('gallery/p.webp', 'x');
        Storage::disk('public')->put('gallery/thumbs/p.webp', 'x');
        $photo = $this->photo(null, ['image' => 'gallery/p.webp', 'thumb' => 'gallery/thumbs/p.webp']);
        $hair = $this->category('Hair');

        $admin = $this->actingAsAdmin();
        $admin->get("/admin/gallery/{$photo->id}/edit")->assertOk();
        $admin->put("/admin/gallery/{$photo->id}", ['caption' => 'Balayage', 'service_category_id' => $hair->id, 'sort_order' => 3])->assertRedirect();

        $photo->refresh();
        $this->assertSame('Balayage', $photo->caption);
        $this->assertSame($hair->id, $photo->service_category_id);
        $this->assertFalse($photo->is_active, 'Unticked switch hides the photo');

        $admin->delete("/admin/gallery/{$photo->id}")->assertRedirect();
        $this->assertModelMissing($photo);
        Storage::disk('public')->assertMissing(['gallery/p.webp', 'gallery/thumbs/p.webp']);
        $this->assertSame('warning', SystemLog::where('action', 'gallery.deleted')->value('level'));
    }

    public function test_gallery_admin_requires_login(): void
    {
        $photo = $this->photo();

        $this->get('/admin/gallery')->assertRedirect(route('admin.login'));
        $this->post('/admin/gallery', ['images' => [UploadedFile::fake()->image('x.jpg')]])->assertRedirect(route('admin.login'));
        $this->delete("/admin/gallery/{$photo->id}")->assertRedirect(route('admin.login'));
        $this->assertSame(1, GalleryImage::count());
    }

    public function test_admin_gallery_page_lists_and_filters_photos(): void
    {
        $hair = $this->category('Hair');
        $this->photo($hair, ['caption' => 'Hair photo']);
        $this->photo(null, ['caption' => 'Loose photo']);

        $admin = $this->actingAsAdmin();
        $admin->get('/admin/gallery')->assertOk()->assertSee('Hair photo')->assertSee('Loose photo');
        $admin->get('/admin/gallery?category=' . $hair->id)->assertOk()->assertSee('Hair photo')->assertDontSee('Loose photo');
        $admin->get('/admin/gallery?category=none')->assertOk()->assertSee('Loose photo')->assertDontSee('Hair photo');
    }

    // ── Public gallery ─────────────────────────────────────────────────

    public function test_public_gallery_shows_visible_photos_and_filters_by_category(): void
    {
        $bridal = $this->category('Bridal');
        $nails = $this->category('Nails');
        $this->photo($bridal, ['caption' => 'Bride one']);
        $this->photo($nails, ['caption' => 'Nail art']);
        $this->photo(null, ['caption' => 'Hidden one', 'is_active' => false]);

        $this->get('/gallery')->assertOk()
            ->assertSee('Bride one')->assertSee('Nail art')->assertDontSee('Hidden one')
            ->assertSee(route('gallery', ['category' => 'nails']), false);

        $this->get('/gallery?category=nails')->assertOk()->assertSee('Nail art')->assertDontSee('Bride one');
        // Unknown filter falls back to everything.
        $this->get('/gallery?category=zzz')->assertOk()->assertSee('Bride one')->assertSee('Nail art');
    }

    public function test_gallery_is_paginated_and_empty_state_is_friendly(): void
    {
        $this->get('/gallery')->assertOk()->assertSee('Photos coming soon');

        for ($i = 1; $i <= 30; $i++) {
            $this->photo(null, ['caption' => 'Look ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        // Newest first: page 1 has Look 30 … Look 07, page 2 the rest.
        $this->get('/gallery')->assertOk()->assertSee('Look 30')->assertDontSee('Look 06');
        $this->get('/gallery?page=2')->assertOk()->assertSee('Look 06')->assertSee('Look 01');
    }

    public function test_homepage_previews_latest_gallery_photos(): void
    {
        $this->photo(null, ['caption' => 'Fresh balayage']);

        $this->get('/')->assertOk()->assertSee('Our latest work')->assertSee('Fresh balayage');
    }

    public function test_deleting_a_category_keeps_its_photos(): void
    {
        $cat = $this->category('Hair');
        $photo = $this->photo($cat);

        $this->actingAsAdmin()->delete("/admin/categories/{$cat->id}")->assertRedirect();

        $this->assertNull($photo->fresh()->service_category_id);
    }
}
