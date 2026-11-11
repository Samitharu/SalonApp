<?php

namespace App\Jobs;

use App\Mail\BookingConfirmed;
use App\Models\Appointment;
use App\Models\SystemLog;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Emails the customer their booking confirmation.
 *
 * Runs after the HTTP response, so a slow or broken mail server can't hold up
 * or fail the booking. No queue worker is needed. A failure is recorded on the
 * booking, where staff can see it and send it again.
 */
class SendBookingConfirmation
{
    use Dispatchable;

    private bool $handled = false;

    public function __construct(public int $appointmentId, public string $kind = 'confirmed')
    {
    }

    /** Send once any open transaction has committed; nothing goes out if it rolls back. */
    public static function schedule(Appointment $appointment, string $kind): void
    {
        $id = $appointment->id;

        DB::afterCommit(fn () => static::dispatchAfterResponse($id, $kind));
    }

    public function handle(): void
    {
        // Terminating callbacks can run more than once in a long-lived process.
        if ($this->handled) {
            return;
        }
        $this->handled = true;

        $appointment = Appointment::find($this->appointmentId);

        // It may have moved on (started, cancelled) since this was scheduled.
        if ($appointment && $appointment->status === 'confirmed') {
            static::send($appointment, $this->kind);
        }
    }

    /** Sends now. Returns the error message, or null on success. Never throws. */
    public static function send(Appointment $appointment, string $kind = 'confirmed'): ?string
    {
        $appointment->loadMissing(['customer', 'service', 'staff', 'items', 'payments']);
        $to = $appointment->confirmationEmail();

        if (!$to) {
            return 'This booking has no email address.';
        }

        try {
            Mail::to($to, $appointment->customer?->name)->send(new BookingConfirmed($appointment, $kind));
        } catch (\Throwable $e) {
            $error = Str::limit($e->getMessage(), 240);
            // Plain update: this must not trigger the model's own hooks again.
            Appointment::whereKey($appointment->id)->update(['confirmation_error' => $error]);
            SystemLog::record('appointment.confirmation_failed', "Couldn't email the confirmation for {$appointment->appointment_no} to {$to}", $appointment, ['to' => $to, 'error' => $error], 'error');

            return $error;
        }

        // Written to the log isn't sent: don't let the booking claim it was.
        if (!mail_is_live()) {
            Appointment::whereKey($appointment->id)->update(['confirmation_error' => null]);

            return null;
        }

        Appointment::whereKey($appointment->id)->update(['confirmation_sent_at' => now(), 'confirmation_error' => null]);
        SystemLog::record('appointment.confirmation_sent', ($kind === 'updated' ? 'Updated booking details' : 'Confirmation') . " for {$appointment->appointment_no} emailed to {$to}", $appointment, ['to' => $to, 'kind' => $kind]);

        return null;
    }
}
