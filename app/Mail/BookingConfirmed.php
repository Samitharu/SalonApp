<?php

namespace App\Mail;

use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/** $kind: "confirmed" (newly confirmed) or "updated" (a confirmed booking changed). */
class BookingConfirmed extends Mailable
{
    public function __construct(public Appointment $appointment, public string $kind = 'confirmed')
    {
    }

    public function envelope(): Envelope
    {
        $salon = (string) setting('salon_name', config('app.name'));
        $when = Carbon::parse($this->appointment->appointment_date)->format('j M') . ', ' . fmt_time($this->appointment->slot_start);
        $replyTo = setting('salon_email');

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $salon),
            replyTo: $replyTo ? [new Address($replyTo, $salon)] : [],
            subject: ($this->kind === 'updated' ? "Your {$salon} booking has changed" : "Your {$salon} booking is confirmed") . " — {$when}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-confirmed',
            text: 'emails.booking-confirmed-text',
            with: ['a' => $this->appointment, 'kind' => $this->kind, 'logoPng' => $this->logoPng()],
        );
    }

    /** The stored logo is WebP, which Outlook can't show; emails get a PNG copy. */
    private function logoPng(): ?string
    {
        $path = setting('salon_logo');
        if (!$path || !function_exists('imagecreatefromstring') || !Storage::disk('public')->exists($path)) {
            return null;
        }

        $image = @imagecreatefromstring((string) Storage::disk('public')->get($path));
        if (!$image) {
            return null;
        }

        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        imagedestroy($image);

        return ob_get_clean() ?: null;
    }
}
