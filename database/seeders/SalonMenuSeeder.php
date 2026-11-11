<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\SystemLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Starter service menu: main categories with their packages and a few add-ons.
 *
 *   php artisan db:seed --class=SalonMenuSeeder
 *
 * Safe to run again: categories are matched by slug (or name) and services by
 * name, and anything that already exists is left exactly as the admin set it.
 * Empty icon/tagline/description fields on an existing category are filled in,
 * and services without an image get their illustration from
 * resources/images/services (sources: the .svg files beside them).
 */
class SalonMenuSeeder extends Seeder
{
    /**
     * slug => [name, icon, tagline, description, [match existing slugs], [services]]
     * service: [name, price, minutes, description, advance_type, advance_value, buffer]
     */
    private const MENU = [
        'bridal' => ['Bridal', 'ring', 'Timeless looks for your special day',
            'Our senior team creates your complete look — hair, make-up, draping and jewellery setting — with a trial session before the big day.',
            [], [
                ['Kandyan Bridal Dressing', 65000, 300, 'Complete Kandyan bride: hair, make-up, osariya draping and jewellery setting.', 'percent', 30, 30],
                ['Western Bridal Dressing', 60000, 240, 'Hair, make-up and veil setting for a classic white wedding.', 'percent', 30, 30],
                ['Indian Bridal Dressing', 70000, 300, 'Hair, HD make-up, saree or lehenga draping and jewellery setting.', 'percent', 30, 30],
                ['Homecoming Dressing', 35000, 180, 'Hair, make-up and saree draping for your homecoming.', 'fixed', 10000, 30],
                ['Engagement Dressing', 25000, 150, 'Hair, make-up and draping for your engagement or registration.', 'fixed', 7500, 15],
                ['Bridal Trial Session', 10000, 120, 'A full run-through of your wedding look, so there are no surprises.', 'none', 0, 0],
                ['Bridesmaid Dressing', 12000, 120, 'Hair, make-up and saree draping for bridesmaids.', 'fixed', 3000, 0],
                ['Flower Girl Dressing', 5000, 60, 'Hairstyle and light make-up for flower girls.', 'none', 0, 0],
                ['Groom Dressing', 8500, 60, 'Grooming, hair styling and a light finish for the groom.', 'none', 0, 0],
            ]],
        'hair' => ['Hair', 'scissors', 'Cuts, colour and care',
            'Precision cuts, colour and treatments for healthy, beautiful hair. Prices are for short to medium length; longer hair may cost a little more.',
            ['hair-services'], [
                ["Ladies' Haircut & Blow-dry", 3500, 60, 'Consultation, wash, cut and a finishing blow-dry.', 'none', 0, 0],
                ["Kids' Haircut (under 12)", 1200, 30, 'A quick, friendly cut for little ones.', 'none', 0, 0],
                ['Blow-dry & Styling', 2500, 45, 'Wash and a smooth or bouncy blow-dry.', 'none', 0, 0],
                ['Party Hair-up', 4500, 60, 'Elegant updo or braided style for an event.', 'none', 0, 0],
                ['Deep Conditioning Treatment', 3000, 45, 'Intensive moisture treatment for dry or damaged hair.', 'none', 0, 0],
                ['Hair Spa', 4500, 60, 'Scalp massage, steam and nourishing mask.', 'none', 0, 0],
                ['Root Touch-up', 5500, 90, 'Colour for regrowth up to 1 inch.', 'none', 0, 0],
                ['Full Hair Colour', 9500, 120, 'All-over permanent colour with gloss finish.', 'none', 0, 0],
                ['Highlights / Balayage', 18000, 180, 'Hand-painted or foil highlights for natural dimension.', 'none', 0, 15],
                ['Keratin Smoothing Treatment', 22000, 180, 'Frizz-free, glossy hair for up to four months.', 'fixed', 5000, 15],
                ['Hair Rebonding', 25000, 240, 'Permanent straightening for sleek, straight hair.', 'fixed', 5000, 15],
            ]],
        'makeup' => ['Make-up', 'makeup', 'Party & photo-ready looks',
            'Flawless make-up for parties, photoshoots and every special occasion.',
            [], [
                ['Natural Day Make-up', 4500, 45, 'Fresh, natural look for everyday events.', 'none', 0, 0],
                ['Party Make-up', 7500, 60, 'Long-wear make-up with lashes for evening events.', 'none', 0, 0],
                ['Party Make-up & Hairstyle', 11000, 90, 'Full party look: make-up, lashes and a hairstyle.', 'none', 0, 0],
                ['Photoshoot Make-up', 9000, 75, 'HD make-up designed for camera and studio lights.', 'none', 0, 0],
                ['Saree Draping', 2500, 30, 'Neat, secure draping in your chosen style.', 'none', 0, 0],
                ['Classic Eyelash Extensions', 6500, 90, 'Natural-looking individual lash extensions.', 'none', 0, 0],
                ['Strip Lash Application', 1500, 15, 'Strip lashes applied and blended.', 'none', 0, 0],
            ]],
        'skin' => ['Skin Care', 'face', 'Facials, threading & waxing',
            'Facials for every skin type, plus threading and waxing by gentle, experienced hands.',
            [], [
                ['Classic Clean-up', 3000, 45, 'Cleanse, exfoliate, steam and mask.', 'none', 0, 0],
                ['Hydrating Facial', 6500, 60, 'Deep moisture for dull or dehydrated skin.', 'none', 0, 0],
                ['Brightening Vitamin C Facial', 8000, 60, 'Evens tone and restores glow.', 'none', 0, 0],
                ['Anti-acne Facial', 7000, 60, 'Deep pore cleansing and calming treatment for breakouts.', 'none', 0, 0],
                ['Gold Radiance Facial', 9500, 75, 'Luxury facial with gold-infused mask for special occasions.', 'none', 0, 0],
                ['Eyebrow Threading', 500, 10, 'Clean, defined brows shaped to suit your face.', 'none', 0, 0],
                ['Upper Lip Threading', 300, 10, 'Quick and precise.', 'none', 0, 0],
                ['Full Face Threading', 1500, 20, 'Brows, upper lip, chin and sides.', 'none', 0, 0],
                ['Underarm Waxing', 1000, 15, 'Smooth results with gentle wax.', 'none', 0, 0],
                ['Full Arm Waxing', 2500, 30, 'Full arms with soothing after-care.', 'none', 0, 0],
                ['Half Leg Waxing', 2500, 30, 'Knee down, with soothing after-care.', 'none', 0, 0],
                ['Full Leg Waxing', 4000, 45, 'Full legs with soothing after-care.', 'none', 0, 0],
            ]],
        'nails' => ['Nails', 'nails', 'Manicures, pedicures & nail art',
            'Clean, hygienic nail care with long-lasting gel colours and custom nail art.',
            [], [
                ['Classic Manicure', 2500, 45, 'Shape, cuticle care, hand massage and polish.', 'none', 0, 0],
                ['Classic Pedicure', 3000, 50, 'Soak, scrub, shape, cuticle care and polish.', 'none', 0, 0],
                ['Spa Pedicure', 4500, 60, 'Classic pedicure plus mask and extended massage.', 'none', 0, 0],
                ['Gel Polish – Hands', 3500, 45, 'Chip-resistant gel colour that lasts up to three weeks.', 'none', 0, 0],
                ['Gel Polish – Feet', 3500, 45, 'Chip-resistant gel colour for toes.', 'none', 0, 0],
                ['Acrylic Extensions – Full Set', 8500, 120, 'Length and strength with your choice of shape.', 'none', 0, 0],
                ['Gel / Acrylic Removal', 1000, 20, 'Safe removal without damaging natural nails.', 'none', 0, 0],
            ]],
        'mens-grooming' => ["Men's Grooming", 'groom', 'Cuts, shaves & grooming',
            'Sharp cuts, beard care and relaxing treatments for men.',
            [], [
                ['Beard Trim & Shape', 800, 20, 'Trim, shape and line-up.', 'none', 0, 0],
                ['Classic Hot-towel Shave', 1200, 30, 'Traditional straight-razor shave with hot towels.', 'none', 0, 0],
                ['Haircut & Beard Combo', 2000, 45, 'Haircut with beard trim and styling.', 'none', 0, 0],
                ["Men's Hair Colour", 3500, 60, 'Natural-looking colour or grey coverage.', 'none', 0, 0],
                ["Men's Deep Cleansing Facial", 4000, 45, 'Cleanse, exfoliate and refresh tired skin.', 'none', 0, 0],
                ['Head & Shoulder Massage', 2000, 20, 'Relieves tension in the head, neck and shoulders.', 'none', 0, 0],
            ]],
    ];

    /** Extras the stylist or cashier can add to a bill (not booked on their own). */
    private const ADDONS = [
        ['Long Hair Surcharge', 1000, 'For hair below shoulder length.'],
        ['Nail Art (per nail)', 200, 'Hand-painted designs, stones or foils.'],
        ['Paraffin Wax Treatment', 1500, 'Softening paraffin dip for hands or feet.'],
        ['Hair Extension Setting', 2000, 'Clip-in or temporary extensions set and blended.'],
    ];

    private int $imagesAttached = 0;

    public function run(): void
    {
        $order = 0;
        $createdCats = $createdServices = 0;

        foreach (self::MENU as $slug => [$name, $icon, $tagline, $description, $aliases, $services]) {
            $category = ServiceCategory::whereIn('slug', array_merge([$slug], $aliases))->first()
                ?? ServiceCategory::where('name', $name)->first();

            if (!$category) {
                $category = ServiceCategory::create([
                    'name' => $name, 'slug' => $this->freeSlug(ServiceCategory::class, $slug),
                    'icon' => $icon, 'tagline' => $tagline, 'description' => $description,
                    'sort_order' => $order, 'is_active' => true,
                ]);
                $createdCats++;
            } else {
                // Only fill blanks; never overwrite what the admin set.
                $category->fill(array_filter([
                    'icon'        => $category->icon ? null : $icon,
                    'tagline'     => $category->tagline ? null : $tagline,
                    'description' => $category->description ? null : $description,
                ]))->save();
            }
            $order++;

            foreach ($services as $i => [$sName, $price, $minutes, $sDesc, $advType, $advValue, $buffer]) {
                if ($existing = Service::where('name', $sName)->first()) {
                    $this->attachImage($existing);
                    continue;
                }
                $service = Service::create([
                    'service_category_id' => $category->id,
                    'name'                => $sName,
                    'slug'                => Service::uniqueSlug($sName),
                    'price'               => $price,
                    'duration_minutes'    => $minutes,
                    'buffer_minutes'      => $buffer ?: null,
                    'description'         => $sDesc,
                    'advance_type'        => $advType,
                    'advance_value'       => $advValue,
                    'sort_order'          => ($i + 1) * 10,
                    'is_active'           => true,
                    'is_addon'            => false,
                ]);
                $this->attachImage($service);
                $createdServices++;
            }
        }

        // Any other service with a matching illustration (e.g. ones the salon added itself) gets one too.
        Service::whereNull('image')->where('is_addon', false)->get()->each(fn ($s) => $this->attachImage($s));

        foreach (self::ADDONS as $i => [$aName, $price, $aDesc]) {
            if (Service::where('name', $aName)->exists()) {
                continue;
            }
            Service::create([
                'name' => $aName, 'slug' => Service::uniqueSlug($aName), 'price' => $price, 'duration_minutes' => 5,
                'description' => $aDesc, 'sort_order' => ($i + 1) * 10, 'is_active' => true, 'is_addon' => true,
                'advance_type' => 'none', 'advance_value' => 0,
            ]);
            $createdServices++;
        }

        if ($createdCats || $createdServices || $this->imagesAttached) {
            SystemLog::record('service.menu_seeded', "Starter menu: {$createdCats} categories, {$createdServices} services and {$this->imagesAttached} images added", null,
                ['categories' => $createdCats, 'services' => $createdServices, 'images' => $this->imagesAttached], 'info', ['system', null, 'System']);
        }

        $this->command?->info("Added {$createdCats} categories, {$createdServices} services and {$this->imagesAttached} images (existing ones left unchanged).");
    }

    /**
     * Gives a service its illustration from resources/images/services/{name-slug}.webp
     * when it has no image yet. Photos the admin uploads are never replaced.
     */
    private function attachImage(Service $service): void
    {
        if ($service->image) {
            return;
        }
        $key = Str::slug($service->name);
        $source = resource_path("images/services/{$key}.webp");
        if (!is_file($source)) {
            return;
        }
        $path = "services/menu/{$key}.webp";
        Storage::disk('public')->put($path, file_get_contents($source));
        $service->forceFill(['image' => $path])->save();
        $this->imagesAttached++;
    }

    private function freeSlug(string $model, string $base): string
    {
        $slug = $base;
        for ($i = 2; $model::where('slug', $slug)->exists(); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}
