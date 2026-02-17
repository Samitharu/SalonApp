<?php

use App\Models\Setting;

function setting($key, $default = null)
{
    static $settings;

    if (!$settings) {
        $settings = Setting::pluck('value', 'key');
    }

    return $settings[$key] ?? $default;
}