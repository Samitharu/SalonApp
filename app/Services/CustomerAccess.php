<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Http\Request;

/**
 * Which booking this browser has proven it owns, kept in the session.
 *
 *  - MANAGE: unlocked with reference + phone. Allows change, cancel, review.
 *  - REVIEW: unlocked by the signed "rate your visit" link on the receipt.
 *            Allows leaving feedback only, never changing the booking.
 */
class CustomerAccess
{
    public const MANAGE = 'manage_booking';
    public const REVIEW = 'review_booking';

    private const TTL = [self::MANAGE => 1800, self::REVIEW => 7200];

    public static function remember(Request $request, Appointment $appointment, string $key = self::MANAGE): void
    {
        $request->session()->put($key, ['id' => $appointment->id, 'at' => now()->timestamp]);
    }

    public static function forget(Request $request, string $key = self::MANAGE): void
    {
        $request->session()->forget($key);
    }

    public static function verified(Request $request, string $key = self::MANAGE): ?Appointment
    {
        $entry = $request->session()->get($key);

        if (!is_array($entry) || empty($entry['id']) || ($entry['at'] ?? 0) < now()->timestamp - self::TTL[$key]) {
            return null;
        }

        return Appointment::with(['service', 'staff', 'customer', 'items', 'feedback', 'payments'])->find($entry['id']);
    }

    /** The booking this browser may review: via the link, or via reference + phone. */
    public static function reviewable(Request $request): ?Appointment
    {
        return static::verified($request, self::REVIEW) ?? static::verified($request, self::MANAGE);
    }

    /** Booking reference + the phone number it was booked with. */
    public static function find(string $reference, string $phone): ?Appointment
    {
        $appointment = Appointment::with(['service', 'staff', 'customer', 'items'])
            ->where('appointment_no', strtoupper(trim($reference)))
            ->first();

        if (!$appointment || !$appointment->customer) {
            return null;
        }

        // Compare normalised digits so "077 123 4567" matches "0771234567".
        $given  = ltrim(normalize_phone($phone), '+');
        $stored = ltrim(normalize_phone($appointment->customer->phone), '+');

        return hash_equals($stored, $given) ? $appointment : null;
    }
}
