<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Feedback;
use App\Models\SystemLog;

/** Customer reviews: shared by "Manage my booking" and the "Rate your visit" page. */
class FeedbackService
{
    public const RULES = [
        'rating'       => 'required|integer|between:1,5',
        'comment'      => 'nullable|string|max:1000',
        'allow_public' => 'nullable|boolean',
    ];

    public const MESSAGES = [
        'rating.required' => 'Please choose a star rating.',
        'rating.between'  => 'Please choose between 1 and 5 stars.',
    ];

    /** Why this visit can't be reviewed yet, or null if it can. */
    public static function blockedReason(Appointment $a): ?string
    {
        if ($a->feedback) {
            return 'You’ve already reviewed this visit — thank you!';
        }

        return $a->status === 'completed' ? null : 'You can leave feedback once your appointment is completed.';
    }

    public static function submit(Appointment $a, array $data): Feedback
    {
        $comment = isset($data['comment']) ? trim((string) $data['comment']) : '';

        $feedback = $a->feedback()->create([
            'customer_id'  => $a->customer_id,
            'staff_id'     => $a->staff_id,
            'service_id'   => $a->service_id,
            'rating'       => (int) $data['rating'],
            'comment'      => $comment !== '' ? $comment : null,
            'allow_public' => !empty($data['allow_public']),
        ]);

        SystemLog::record('feedback.submitted', "{$feedback->rating}-star review left for {$a->appointment_no}", $feedback,
            ['rating' => $feedback->rating, 'appointment' => $a->appointment_no, 'allow_public' => $feedback->allow_public],
            $feedback->rating <= 2 ? 'warning' : 'info',
            ['customer', $a->customer_id, $a->customer->name ?? null]);

        return $feedback;
    }
}
