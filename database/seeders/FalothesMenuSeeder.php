<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Setting;
use App\Models\SystemLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Falothes' live service menu: the salon name, categories, packages and add-ons
 * exactly as they were set up in the office copy on 2026-11-11 (5 categories, 54 packages and 4 add-ons).
 *
 *   php artisan db:seed --class=FalothesMenuSeeder --force
 *
 * Use it to bring a server in line with that menu. Categories are matched by
 * slug (then name) and services by slug (then name); matches are updated to the
 * values below and anything missing is created. Nothing is deleted: other
 * categories and services on the server are left as they are. Running it again
 * resets these entries to this snapshot, so edit prices in the admin afterwards
 * rather than re-running it.
 *
 * Photos come from database/seeders/menu-images and are copied to the public disk.
 */
class FalothesMenuSeeder extends Seeder
{
    public const SALON_NAME = 'Falothes';

    /** slug => category */
    private const CATEGORIES = [
        'hair-services' => [
            'name' => 'Hair Services', 'icon' => 'scissors', 'sort_order' => 0, 'is_active' => true,
            'tagline' => 'Cuts, colour and care',
            'description' => 'Precision cuts, colour and treatments for healthy, beautiful hair. Prices are for short to medium length; longer hair may cost a little more.',
            'image' => 'categories/hair-services.webp',
        ],
        'bridal' => [
            'name' => 'Bridal', 'icon' => 'ring', 'sort_order' => 0, 'is_active' => true,
            'tagline' => 'Timeless looks for your special day',
            'description' => 'Our senior team creates your complete look — hair, make-up, draping and jewellery setting — with a trial session before the big day.',
            'image' => 'categories/bridal.webp',
        ],
        'makeup' => [
            'name' => 'Make-up', 'icon' => 'makeup', 'sort_order' => 2, 'is_active' => true,
            'tagline' => 'Party & photo-ready looks',
            'description' => 'Flawless make-up for parties, photoshoots and every special occasion.',
            'image' => 'categories/makeup.webp',
        ],
        'skin' => [
            'name' => 'Skin Care', 'icon' => 'face', 'sort_order' => 3, 'is_active' => true,
            'tagline' => 'Facials, threading & waxing',
            'description' => 'Facials for every skin type, plus threading and waxing by gentle, experienced hands.',
            'image' => 'categories/skin.webp',
        ],
        'nails' => [
            'name' => 'Nails', 'icon' => 'nails', 'sort_order' => 4, 'is_active' => true,
            'tagline' => 'Manicures, pedicures & nail art',
            'description' => 'Clean, hygienic nail care with long-lasting gel colours and custom nail art.',
            'image' => 'categories/nails.webp',
        ],
    ];

    /** 'category' is a slug from CATEGORIES (null = no category). */
    private const SERVICES = [
        // Category: hair-services
        ['category' => 'hair-services', 'name' => 'Hair Cut', 'slug' => 'hair-cut', 'price' => 1500, 'minutes' => 25, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 0, 'is_active' => true, 'is_addon' => false, 'image' => 'services/hair-cut.webp', 'description' => 'meka labai'],
        ['category' => 'hair-services', 'name' => 'Hair Cut with facial', 'slug' => 'hair-cut-with-facial', 'price' => 2500, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 0, 'is_active' => true, 'is_addon' => false, 'image' => 'services/hair-cut-with-facial.webp', 'description' => 'meka patta ganan'],
        ['category' => 'hair-services', 'name' => 'Ladies\' Haircut & Blow-dry', 'slug' => 'ladies-haircut-blow-dry', 'price' => 3500, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 10, 'is_active' => true, 'is_addon' => false, 'image' => 'services/ladies-haircut-blow-dry.webp', 'description' => 'Consultation, wash, cut and a finishing blow-dry.'],
        ['category' => 'hair-services', 'name' => 'Kids\' Haircut (under 12)', 'slug' => 'kids-haircut-under-12', 'price' => 1200, 'minutes' => 30, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 20, 'is_active' => true, 'is_addon' => false, 'image' => 'services/kids-haircut-under-12.webp', 'description' => 'A quick, friendly cut for little ones.'],
        ['category' => 'hair-services', 'name' => 'Blow-dry & Styling', 'slug' => 'blow-dry-styling', 'price' => 2500, 'minutes' => 45, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 30, 'is_active' => true, 'is_addon' => false, 'image' => 'services/blow-dry-styling.webp', 'description' => 'Wash and a smooth or bouncy blow-dry.'],
        ['category' => 'hair-services', 'name' => 'Party Hair-up', 'slug' => 'party-hair-up', 'price' => 4500, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 40, 'is_active' => true, 'is_addon' => false, 'image' => 'services/party-hair-up.webp', 'description' => 'Elegant updo or braided style for an event.'],
        ['category' => 'hair-services', 'name' => 'Deep Conditioning Treatment', 'slug' => 'deep-conditioning-treatment', 'price' => 3000, 'minutes' => 45, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 50, 'is_active' => true, 'is_addon' => false, 'image' => 'services/deep-conditioning-treatment.webp', 'description' => 'Intensive moisture treatment for dry or damaged hair.'],
        ['category' => 'hair-services', 'name' => 'Hair Spa', 'slug' => 'hair-spa', 'price' => 4500, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 60, 'is_active' => true, 'is_addon' => false, 'image' => 'services/hair-spa.webp', 'description' => 'Scalp massage, steam and nourishing mask.'],
        ['category' => 'hair-services', 'name' => 'Root Touch-up', 'slug' => 'root-touch-up', 'price' => 5500, 'minutes' => 90, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 70, 'is_active' => true, 'is_addon' => false, 'image' => 'services/root-touch-up.webp', 'description' => 'Colour for regrowth up to 1 inch.'],
        ['category' => 'hair-services', 'name' => 'Full Hair Colour', 'slug' => 'full-hair-colour', 'price' => 9500, 'minutes' => 120, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 80, 'is_active' => true, 'is_addon' => false, 'image' => 'services/full-hair-colour.webp', 'description' => 'All-over permanent colour with gloss finish.'],
        ['category' => 'hair-services', 'name' => 'Highlights / Balayage', 'slug' => 'highlights-balayage', 'price' => 18000, 'minutes' => 180, 'buffer' => 15, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 90, 'is_active' => true, 'is_addon' => false, 'image' => 'services/highlights-balayage.webp', 'description' => 'Hand-painted or foil highlights for natural dimension.'],
        ['category' => 'hair-services', 'name' => 'Keratin Smoothing Treatment', 'slug' => 'keratin-smoothing-treatment', 'price' => 22000, 'minutes' => 180, 'buffer' => 15, 'advance_type' => 'fixed', 'advance_value' => 5000, 'sort_order' => 100, 'is_active' => true, 'is_addon' => false, 'image' => 'services/keratin-smoothing-treatment.webp', 'description' => 'Frizz-free, glossy hair for up to four months.'],
        ['category' => 'hair-services', 'name' => 'Hair Rebonding', 'slug' => 'hair-rebonding', 'price' => 25000, 'minutes' => 240, 'buffer' => 15, 'advance_type' => 'fixed', 'advance_value' => 5000, 'sort_order' => 110, 'is_active' => true, 'is_addon' => false, 'image' => 'services/hair-rebonding.webp', 'description' => 'Permanent straightening for sleek, straight hair.'],

        // Category: bridal
        ['category' => 'bridal', 'name' => 'Kandyan Bridal Dressing', 'slug' => 'kandyan-bridal-dressing', 'price' => 65000, 'minutes' => 300, 'buffer' => 30, 'advance_type' => 'percent', 'advance_value' => 30, 'sort_order' => 10, 'is_active' => true, 'is_addon' => false, 'image' => 'services/kandyan-bridal-dressing.webp', 'description' => 'Complete Kandyan bride: hair, make-up, osariya draping and jewellery setting.'],
        ['category' => 'bridal', 'name' => 'Western Bridal Dressing', 'slug' => 'western-bridal-dressing', 'price' => 60000, 'minutes' => 240, 'buffer' => 30, 'advance_type' => 'percent', 'advance_value' => 30, 'sort_order' => 20, 'is_active' => true, 'is_addon' => false, 'image' => 'services/western-bridal-dressing.webp', 'description' => 'Hair, make-up and veil setting for a classic white wedding.'],
        ['category' => 'bridal', 'name' => 'Indian Bridal Dressing', 'slug' => 'indian-bridal-dressing', 'price' => 70000, 'minutes' => 300, 'buffer' => 30, 'advance_type' => 'percent', 'advance_value' => 30, 'sort_order' => 30, 'is_active' => true, 'is_addon' => false, 'image' => 'services/indian-bridal-dressing.webp', 'description' => 'Hair, HD make-up, saree or lehenga draping and jewellery setting.'],
        ['category' => 'bridal', 'name' => 'Homecoming Dressing', 'slug' => 'homecoming-dressing', 'price' => 35000, 'minutes' => 180, 'buffer' => 30, 'advance_type' => 'fixed', 'advance_value' => 10000, 'sort_order' => 40, 'is_active' => true, 'is_addon' => false, 'image' => 'services/homecoming-dressing.webp', 'description' => 'Hair, make-up and saree draping for your homecoming.'],
        ['category' => 'bridal', 'name' => 'Engagement Dressing', 'slug' => 'engagement-dressing', 'price' => 25000, 'minutes' => 150, 'buffer' => 15, 'advance_type' => 'fixed', 'advance_value' => 7500, 'sort_order' => 50, 'is_active' => true, 'is_addon' => false, 'image' => 'services/engagement-dressing.webp', 'description' => 'Hair, make-up and draping for your engagement or registration.'],
        ['category' => 'bridal', 'name' => 'Bridal Trial Session', 'slug' => 'bridal-trial-session', 'price' => 10000, 'minutes' => 120, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 60, 'is_active' => true, 'is_addon' => false, 'image' => 'services/bridal-trial-session.webp', 'description' => 'A full run-through of your wedding look, so there are no surprises.'],
        ['category' => 'bridal', 'name' => 'Bridesmaid Dressing', 'slug' => 'bridesmaid-dressing', 'price' => 12000, 'minutes' => 120, 'buffer' => null, 'advance_type' => 'fixed', 'advance_value' => 3000, 'sort_order' => 70, 'is_active' => true, 'is_addon' => false, 'image' => 'services/bridesmaid-dressing.webp', 'description' => 'Hair, make-up and saree draping for bridesmaids.'],
        ['category' => 'bridal', 'name' => 'Flower Girl Dressing', 'slug' => 'flower-girl-dressing', 'price' => 5000, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 80, 'is_active' => true, 'is_addon' => false, 'image' => 'services/flower-girl-dressing.webp', 'description' => 'Hairstyle and light make-up for flower girls.'],
        ['category' => 'bridal', 'name' => 'Groom Dressing', 'slug' => 'groom-dressing', 'price' => 8500, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 90, 'is_active' => true, 'is_addon' => false, 'image' => 'services/groom-dressing.webp', 'description' => 'Grooming, hair styling and a light finish for the groom.'],

        // Category: makeup
        ['category' => 'makeup', 'name' => 'Natural Day Make-up', 'slug' => 'natural-day-make-up', 'price' => 4500, 'minutes' => 45, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 10, 'is_active' => true, 'is_addon' => false, 'image' => 'services/natural-day-make-up.webp', 'description' => 'Fresh, natural look for everyday events.'],
        ['category' => 'makeup', 'name' => 'Party Make-up', 'slug' => 'party-make-up', 'price' => 7500, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 20, 'is_active' => true, 'is_addon' => false, 'image' => 'services/party-make-up.webp', 'description' => 'Long-wear make-up with lashes for evening events.'],
        ['category' => 'makeup', 'name' => 'Party Make-up & Hairstyle', 'slug' => 'party-make-up-hairstyle', 'price' => 11000, 'minutes' => 90, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 30, 'is_active' => true, 'is_addon' => false, 'image' => 'services/party-make-up-hairstyle.webp', 'description' => 'Full party look: make-up, lashes and a hairstyle.'],
        ['category' => 'makeup', 'name' => 'Photoshoot Make-up', 'slug' => 'photoshoot-make-up', 'price' => 9000, 'minutes' => 75, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 40, 'is_active' => true, 'is_addon' => false, 'image' => 'services/photoshoot-make-up.webp', 'description' => 'HD make-up designed for camera and studio lights.'],
        ['category' => 'makeup', 'name' => 'Saree Draping', 'slug' => 'saree-draping', 'price' => 2500, 'minutes' => 30, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 50, 'is_active' => true, 'is_addon' => false, 'image' => 'services/saree-draping.webp', 'description' => 'Neat, secure draping in your chosen style.'],
        ['category' => 'makeup', 'name' => 'Classic Eyelash Extensions', 'slug' => 'classic-eyelash-extensions', 'price' => 6500, 'minutes' => 90, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 60, 'is_active' => true, 'is_addon' => false, 'image' => 'services/classic-eyelash-extensions.webp', 'description' => 'Natural-looking individual lash extensions.'],
        ['category' => 'makeup', 'name' => 'Strip Lash Application', 'slug' => 'strip-lash-application', 'price' => 1500, 'minutes' => 15, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 70, 'is_active' => true, 'is_addon' => false, 'image' => 'services/strip-lash-application.webp', 'description' => 'Strip lashes applied and blended.'],

        // Category: skin
        ['category' => 'skin', 'name' => 'Classic Clean-up', 'slug' => 'classic-clean-up', 'price' => 3000, 'minutes' => 45, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 10, 'is_active' => true, 'is_addon' => false, 'image' => 'services/classic-clean-up.webp', 'description' => 'Cleanse, exfoliate, steam and mask.'],
        ['category' => 'skin', 'name' => 'Hydrating Facial', 'slug' => 'hydrating-facial', 'price' => 6500, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 20, 'is_active' => true, 'is_addon' => false, 'image' => 'services/hydrating-facial.webp', 'description' => 'Deep moisture for dull or dehydrated skin.'],
        ['category' => 'skin', 'name' => 'Brightening Vitamin C Facial', 'slug' => 'brightening-vitamin-c-facial', 'price' => 8000, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 30, 'is_active' => true, 'is_addon' => false, 'image' => 'services/brightening-vitamin-c-facial.webp', 'description' => 'Evens tone and restores glow.'],
        ['category' => 'skin', 'name' => 'Anti-acne Facial', 'slug' => 'anti-acne-facial', 'price' => 7000, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 40, 'is_active' => true, 'is_addon' => false, 'image' => 'services/anti-acne-facial.webp', 'description' => 'Deep pore cleansing and calming treatment for breakouts.'],
        ['category' => 'skin', 'name' => 'Gold Radiance Facial', 'slug' => 'gold-radiance-facial', 'price' => 9500, 'minutes' => 75, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 50, 'is_active' => true, 'is_addon' => false, 'image' => 'services/gold-radiance-facial.webp', 'description' => 'Luxury facial with gold-infused mask for special occasions.'],
        ['category' => 'skin', 'name' => 'Eyebrow Threading', 'slug' => 'eyebrow-threading', 'price' => 500, 'minutes' => 10, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 60, 'is_active' => true, 'is_addon' => false, 'image' => 'services/eyebrow-threading.webp', 'description' => 'Clean, defined brows shaped to suit your face.'],
        ['category' => 'skin', 'name' => 'Upper Lip Threading', 'slug' => 'upper-lip-threading', 'price' => 300, 'minutes' => 10, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 70, 'is_active' => true, 'is_addon' => false, 'image' => 'services/upper-lip-threading.webp', 'description' => 'Quick and precise.'],
        ['category' => 'skin', 'name' => 'Full Face Threading', 'slug' => 'full-face-threading', 'price' => 1500, 'minutes' => 20, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 80, 'is_active' => true, 'is_addon' => false, 'image' => 'services/full-face-threading.webp', 'description' => 'Brows, upper lip, chin and sides.'],
        ['category' => 'skin', 'name' => 'Underarm Waxing', 'slug' => 'underarm-waxing', 'price' => 1000, 'minutes' => 15, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 90, 'is_active' => true, 'is_addon' => false, 'image' => 'services/underarm-waxing.webp', 'description' => 'Smooth results with gentle wax.'],
        ['category' => 'skin', 'name' => 'Full Arm Waxing', 'slug' => 'full-arm-waxing', 'price' => 2500, 'minutes' => 30, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 100, 'is_active' => true, 'is_addon' => false, 'image' => 'services/full-arm-waxing.webp', 'description' => 'Full arms with soothing after-care.'],
        ['category' => 'skin', 'name' => 'Half Leg Waxing', 'slug' => 'half-leg-waxing', 'price' => 2500, 'minutes' => 30, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 110, 'is_active' => true, 'is_addon' => false, 'image' => 'services/half-leg-waxing.webp', 'description' => 'Knee down, with soothing after-care.'],
        ['category' => 'skin', 'name' => 'Full Leg Waxing', 'slug' => 'full-leg-waxing', 'price' => 4000, 'minutes' => 45, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 120, 'is_active' => true, 'is_addon' => false, 'image' => 'services/full-leg-waxing.webp', 'description' => 'Full legs with soothing after-care.'],

        // Category: nails
        ['category' => 'nails', 'name' => 'Classic Manicure', 'slug' => 'classic-manicure', 'price' => 2500, 'minutes' => 45, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 10, 'is_active' => true, 'is_addon' => false, 'image' => 'services/classic-manicure.webp', 'description' => 'Shape, cuticle care, hand massage and polish.'],
        ['category' => 'nails', 'name' => 'Classic Pedicure', 'slug' => 'classic-pedicure', 'price' => 3000, 'minutes' => 50, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 20, 'is_active' => true, 'is_addon' => false, 'image' => 'services/classic-pedicure.webp', 'description' => 'Soak, scrub, shape, cuticle care and polish.'],
        ['category' => 'nails', 'name' => 'Spa Pedicure', 'slug' => 'spa-pedicure', 'price' => 4500, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 30, 'is_active' => true, 'is_addon' => false, 'image' => 'services/spa-pedicure.webp', 'description' => 'Classic pedicure plus mask and extended massage.'],
        ['category' => 'nails', 'name' => 'Gel Polish – Hands', 'slug' => 'gel-polish-hands', 'price' => 3500, 'minutes' => 45, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 40, 'is_active' => true, 'is_addon' => false, 'image' => 'services/gel-polish-hands.webp', 'description' => 'Chip-resistant gel colour that lasts up to three weeks.'],
        ['category' => 'nails', 'name' => 'Gel Polish – Feet', 'slug' => 'gel-polish-feet', 'price' => 3500, 'minutes' => 45, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 50, 'is_active' => true, 'is_addon' => false, 'image' => 'services/gel-polish-feet.webp', 'description' => 'Chip-resistant gel colour for toes.'],
        ['category' => 'nails', 'name' => 'Acrylic Extensions – Full Set', 'slug' => 'acrylic-extensions-full-set', 'price' => 8500, 'minutes' => 120, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 60, 'is_active' => true, 'is_addon' => false, 'image' => 'services/acrylic-extensions-full-set.webp', 'description' => 'Length and strength with your choice of shape.'],
        ['category' => 'nails', 'name' => 'Gel / Acrylic Removal', 'slug' => 'gel-acrylic-removal', 'price' => 1000, 'minutes' => 20, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 70, 'is_active' => true, 'is_addon' => false, 'image' => 'services/gel-acrylic-removal.webp', 'description' => 'Safe removal without damaging natural nails.'],

        // No category
        ['category' => null, 'name' => 'Beard Trim & Shape', 'slug' => 'beard-trim-shape', 'price' => 800, 'minutes' => 20, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 10, 'is_active' => true, 'is_addon' => false, 'image' => 'services/beard-trim-shape.webp', 'description' => 'Trim, shape and line-up.'],
        ['category' => null, 'name' => 'Classic Hot-towel Shave', 'slug' => 'classic-hot-towel-shave', 'price' => 1200, 'minutes' => 30, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 20, 'is_active' => true, 'is_addon' => false, 'image' => 'services/classic-hot-towel-shave.webp', 'description' => 'Traditional straight-razor shave with hot towels.'],
        ['category' => null, 'name' => 'Haircut & Beard Combo', 'slug' => 'haircut-beard-combo', 'price' => 2000, 'minutes' => 45, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 30, 'is_active' => true, 'is_addon' => false, 'image' => 'services/haircut-beard-combo.webp', 'description' => 'Haircut with beard trim and styling.'],
        ['category' => null, 'name' => 'Men\'s Hair Colour', 'slug' => 'mens-hair-colour', 'price' => 3500, 'minutes' => 60, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 40, 'is_active' => true, 'is_addon' => false, 'image' => 'services/mens-hair-colour.webp', 'description' => 'Natural-looking colour or grey coverage.'],
        ['category' => null, 'name' => 'Men\'s Deep Cleansing Facial', 'slug' => 'mens-deep-cleansing-facial', 'price' => 4000, 'minutes' => 45, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 50, 'is_active' => true, 'is_addon' => false, 'image' => 'services/mens-deep-cleansing-facial.webp', 'description' => 'Cleanse, exfoliate and refresh tired skin.'],
        ['category' => null, 'name' => 'Head & Shoulder Massage', 'slug' => 'head-shoulder-massage', 'price' => 2000, 'minutes' => 20, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 60, 'is_active' => true, 'is_addon' => false, 'image' => 'services/head-shoulder-massage.webp', 'description' => 'Relieves tension in the head, neck and shoulders.'],

        // Add-ons (added to a bill, not booked on their own)
        ['category' => null, 'name' => 'Long Hair Surcharge', 'slug' => 'long-hair-surcharge', 'price' => 1000, 'minutes' => 5, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 10, 'is_active' => true, 'is_addon' => true, 'image' => null, 'description' => 'For hair below shoulder length.'],
        ['category' => null, 'name' => 'Nail Art (per nail)', 'slug' => 'nail-art-per-nail', 'price' => 200, 'minutes' => 5, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 20, 'is_active' => true, 'is_addon' => true, 'image' => null, 'description' => 'Hand-painted designs, stones or foils.'],
        ['category' => null, 'name' => 'Paraffin Wax Treatment', 'slug' => 'paraffin-wax-treatment', 'price' => 1500, 'minutes' => 5, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 30, 'is_active' => true, 'is_addon' => true, 'image' => null, 'description' => 'Softening paraffin dip for hands or feet.'],
        ['category' => null, 'name' => 'Hair Extension Setting', 'slug' => 'hair-extension-setting', 'price' => 2000, 'minutes' => 5, 'buffer' => null, 'advance_type' => 'none', 'advance_value' => 0, 'sort_order' => 40, 'is_active' => true, 'is_addon' => true, 'image' => null, 'description' => 'Clip-in or temporary extensions set and blended.'],
    ];

    private int $images = 0;

    public function run(): void
    {
        $created = ['categories' => 0, 'services' => 0];
        $updated = ['categories' => 0, 'services' => 0];

        DB::transaction(function () use (&$created, &$updated) {
            Setting::updateOrCreate(['key' => 'salon_name'], ['value' => self::SALON_NAME]);

            $categoryIds = [];
            foreach (self::CATEGORIES as $slug => $c) {
                $category = ServiceCategory::where('slug', $slug)->first()
                    ?? ServiceCategory::where('name', $c['name'])->first()
                    ?? new ServiceCategory();

                $category->fill([
                    'name'        => $c['name'],
                    'slug'        => $slug,
                    'icon'        => $c['icon'],
                    'tagline'     => $c['tagline'],
                    'description' => $c['description'],
                    'sort_order'  => $c['sort_order'],
                    'is_active'   => $c['is_active'],
                ]);
                if ($path = $this->publishImage($c['image'], 'categories/menu')) {
                    $category->image = $path;
                }

                $category->exists ? $updated['categories']++ : $created['categories']++;
                $category->save();
                $categoryIds[$slug] = $category->id;
            }

            foreach (self::SERVICES as $s) {
                $service = Service::where('slug', $s['slug'])->first()
                    ?? Service::where('name', $s['name'])->first()
                    ?? new Service();

                $service->fill([
                    'service_category_id' => $s['category'] ? $categoryIds[$s['category']] : null,
                    'name'                => $s['name'],
                    'slug'                => $s['slug'],
                    'price'               => $s['price'],
                    'duration_minutes'    => $s['minutes'],
                    'buffer_minutes'      => $s['buffer'],
                    'advance_type'        => $s['advance_type'],
                    'advance_value'       => $s['advance_value'],
                    'description'         => $s['description'],
                    'sort_order'          => $s['sort_order'],
                    'is_active'           => $s['is_active'],
                    'is_addon'            => $s['is_addon'],
                ]);
                if ($path = $this->publishImage($s['image'], 'services/menu')) {
                    $service->image = $path;
                }

                $service->exists ? $updated['services']++ : $created['services']++;
                $service->save();
            }
        });

        setting('__flush');

        SystemLog::record('service.menu_seeded',
            "Falothes menu: {$created['categories']} categories and {$created['services']} services added, {$updated['categories']} and {$updated['services']} updated",
            null, compact('created', 'updated') + ['images' => $this->images], 'info', ['system', null, 'System']);

        $this->command?->info(sprintf(
            'Salon name set to %s. Categories: %d added, %d updated. Services: %d added, %d updated. %d photos copied.',
            self::SALON_NAME, $created['categories'], $updated['categories'], $created['services'], $updated['services'], $this->images
        ));
    }

    /** Copies a bundled photo to the public disk and returns its stored path. */
    private function publishImage(?string $file, string $folder): ?string
    {
        $source = $file ? database_path('seeders/menu-images/' . $file) : null;
        if (!$source || !is_file($source)) {
            return null;
        }
        $path = $folder . '/' . basename($file);
        Storage::disk('public')->put($path, file_get_contents($source));
        $this->images++;

        return $path;
    }
}
