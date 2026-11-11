<?php

namespace Database\Seeders;

use App\Models\Slider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Falothes' homepage slideshow, as set up in the office copy on 2026-11-11.
 *
 *   php artisan db:seed --class=FalothesSlidesSeeder --force
 *
 * Slides are matched by title: matches are updated to the values below and
 * missing ones are created. Other slides on the server are left alone.
 * Photos come from database/seeders/menu-images/slides.
 */
class FalothesSlidesSeeder extends Seeder
{
    private const SLIDES = [
        ['title' => 'Beauty beyond the everyday', 'subtitle' => 'Hair, skin, nails and bridal, by a team that takes its time with you.',
            'button_text' => 'Book appointment', 'button_link' => '/booking', 'image' => 'beauty-beyond-the-everyday.webp'],
        ['title' => 'Your day, your look', 'subtitle' => 'Kandyan, Indian and Western bridal dressing, with a trial before the big day.',
            'button_text' => 'Bridal packages', 'button_link' => '/services/bridal', 'image' => 'your-day-your-look.webp'],
        ['title' => 'Time just for you', 'subtitle' => 'Facials and treatments to relax, refresh and glow.',
            'button_text' => 'Skin care', 'button_link' => '/services/skin', 'image' => 'time-just-for-you.webp'],
    ];

    public function run(): void
    {
        $created = $updated = 0;

        foreach (self::SLIDES as $i => $s) {
            $slide = Slider::firstOrNew(['title' => $s['title']]);
            $slide->exists ? $updated++ : $created++;

            $slide->fill([
                'subtitle'    => $s['subtitle'],
                'button_text' => $s['button_text'],
                'button_link' => $s['button_link'],
                'sort_order'  => $i + 1,
                'is_active'   => true,
            ]);

            $source = database_path('seeders/menu-images/slides/' . $s['image']);
            if (is_file($source)) {
                $path = 'sliders/home/' . $s['image'];
                Storage::disk('public')->put($path, file_get_contents($source));
                $slide->image = $path;
            }

            $slide->save();
        }

        $this->command?->info("Homepage slides: {$created} added, {$updated} updated.");
    }
}
