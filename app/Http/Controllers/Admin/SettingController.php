<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    /** Allowed keys and their validation rules. */
    private const RULES = [
        'salon_name'            => 'required|string|max:80',
        'salon_tagline'         => 'nullable|string|max:160',
        'salon_phone'           => 'nullable|string|max:30',
        'salon_email'           => 'nullable|email|max:120',
        'salon_address'         => 'nullable|string|max:200',
        'maps_url'              => 'nullable|url:https|max:500',
        'currency'              => 'required|string|max:5',
        'facebook_url'          => 'nullable|url:https|max:255',
        'instagram_url'         => 'nullable|url:https|max:255',
        'whatsapp'              => 'nullable|regex:/^[0-9]{7,15}$/',
        'meta_description'      => 'nullable|string|max:160',
        'max_advance_days'      => 'required|integer|min:1|max:365',
        'min_notice_minutes'    => 'required|integer|min:0|max:2880',
        'cancellation_hours'    => 'required|integer|min:0|max:168',
        'slot_step'             => 'required|in:0,15,30,60',
        'max_parallel_bookings' => 'required|integer|min:1|max:50',
        'auto_confirm'          => 'required|in:0,1',
        'booking_policy'        => 'nullable|string|max:600',
        'log_retention_days'    => 'nullable|integer|min:30|max:3650', // blank = default (180)
        'bank_details'          => 'nullable|string|max:600',
        'qr_payment_info'       => 'nullable|string|max:300',
    ];

    public function index()
    {
        $settings = Setting::pluck('value', 'key');
        $hasPin   = !empty($settings['cashier_pin_hash']);

        return view('admin.settings.index', compact('settings', 'hasPin'));
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (self::RULES as $key => $rule) {
            $rules["settings.$key"] = $rule;
        }

        $data = $request->validate($rules, [
            'settings.whatsapp.regex' => 'Enter the WhatsApp number with country code, digits only (e.g. 94771234567).',
            'settings.*.url'          => 'Enter a full https:// link.',
        ], [
            'settings.salon_name' => 'salon name',
        ])['settings'];

        $old = Setting::whereIn('key', array_keys(self::RULES))->pluck('value', 'key');
        $changed = [];

        foreach (array_keys(self::RULES) as $key) {
            Setting::updateOrCreate(['key' => $key], ['value' => $data[$key] ?? null]);
            if ((string) ($old[$key] ?? '') !== (string) ($data[$key] ?? '')) {
                $changed[$key] = ['from' => $old[$key] ?? null, 'to' => $data[$key] ?? null];
            }
        }

        setting('__flush');

        if ($changed) {
            SystemLog::record('settings.updated', 'Settings changed: ' . implode(', ', array_map(fn ($k) => str_replace('_', ' ', $k), array_keys($changed))), null, $changed);
        }

        return back()->with('success', 'Settings saved.');
    }

    /** Logo and receipt options (separate form: it uploads a file). */
    public function updateBranding(Request $request)
    {
        $data = $request->validateWithBag('branding', [
            'logo'           => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096|dimensions:min_width=64,min_height=64',
            'remove_logo'    => 'nullable|boolean',
            'receipt_footer' => 'nullable|string|max:300',
            'receipt_paper'  => 'required|in:a5,80mm',
        ], [
            'logo.dimensions' => 'The logo should be at least 64 × 64 pixels.',
            'logo.max'        => 'The logo must be 4 MB or smaller.',
        ]);

        $old = Setting::whereIn('key', ['salon_logo', 'receipt_footer', 'receipt_paper'])->pluck('value', 'key');
        $logo = $old['salon_logo'] ?? null;

        if ($request->boolean('remove_logo') || $request->hasFile('logo')) {
            ImageService::delete($logo);
            $logo = null;
        }
        if ($request->hasFile('logo')) {
            // Transparent PNG logos keep their transparency (WebP with alpha).
            $logo = ImageService::store($request->file('logo'), 'branding', 600)['path'];
        }

        $new = ['salon_logo' => $logo, 'receipt_footer' => $data['receipt_footer'] ?? null, 'receipt_paper' => $data['receipt_paper']];
        $changed = [];
        foreach ($new as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            if ((string) ($old[$key] ?? '') !== (string) ($value ?? '')) {
                $changed[$key] = ['from' => $old[$key] ?? null, 'to' => $value];
            }
        }
        setting('__flush');

        if ($changed) {
            SystemLog::record('settings.updated', 'Branding changed: ' . implode(', ', array_map(fn ($k) => str_replace('_', ' ', $k), array_keys($changed))), null, $changed);
        }

        return redirect()->to(route('admin.settings.index') . '#branding')->with('success', 'Branding & receipt settings saved.');
    }

    /** Lets the owner prove the mail settings work before relying on them. */
    public function testEmail(Request $request)
    {
        $data = $request->validateWithBag('testEmail', ['to' => 'required|email:rfc|max:120']);
        $back = redirect()->to(route('admin.settings.index') . '#email');
        $salon = (string) setting('salon_name', config('app.name'));

        try {
            Mail::raw("This is a test email from {$salon}. If you can read this, booking confirmations will reach your customers.", function ($message) use ($data, $salon) {
                $message->to($data['to'])->from((string) config('mail.from.address'), $salon)->subject("Test email from {$salon}");
            });
        } catch (\Throwable $e) {
            SystemLog::record('settings.test_email_failed', 'Test email to ' . $data['to'] . ' failed', null, ['error' => Str::limit($e->getMessage(), 240)], 'error');

            return $back->with('error', 'The email couldn’t be sent: ' . Str::limit($e->getMessage(), 240));
        }

        return $back->with('success', mail_is_live()
            ? "Test email sent to {$data['to']}. Check that inbox, including the spam folder."
            : 'Email isn’t set up yet, so the test was only written to the log.');
    }

    public function updateCashierPin(Request $request)
    {
        $data = $request->validateWithBag('pin', [
            'pin'        => 'nullable|digits_between:4,8|confirmed',
            'remove_pin' => 'nullable|boolean',
        ], ['pin.digits_between' => 'The PIN must be 4–8 digits.']);

        if ($request->boolean('remove_pin')) {
            Setting::where('key', 'cashier_pin_hash')->delete();
            setting('__flush');
            SystemLog::record('settings.cashier_pin_removed', 'Cashier station PIN removed', level: 'warning');

            return back()->with('success', 'Cashier PIN removed. Only signed-in admins can open the cashier station now.');
        }

        if (empty($data['pin'])) {
            return back()->withErrors(['pin' => 'Enter a PIN.'], 'pin');
        }

        Setting::updateOrCreate(['key' => 'cashier_pin_hash'], ['value' => Hash::make($data['pin'])]);
        setting('__flush');
        SystemLog::record('settings.cashier_pin_changed', 'Cashier station PIN changed');

        return back()->with('success', 'Cashier PIN updated.');
    }
}
