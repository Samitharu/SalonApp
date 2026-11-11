<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Feedback;
use App\Services\CustomerAccess;
use App\Services\FeedbackService;
use Illuminate\Http\Request;

/**
 * Public "Rate your visit" page. Customers reach it from the receipt QR
 * (signed link, no typing) or by entering their booking reference + phone.
 */
class ReviewController extends Controller
{
    public function create(Request $request)
    {
        $appointment = CustomerAccess::reviewable($request);

        return view('reviews.create', [
            'appointment' => $appointment,
            'blocked'     => $appointment ? FeedbackService::blockedReason($appointment) : null,
            'recent'      => $appointment ? collect() : Feedback::published()->whereNotNull('comment')->with('customer:id,name')->latest()->take(3)->get(),
        ]);
    }

    /** Signed link from the receipt: unlock this visit for reviewing only. */
    public function link(Request $request, Appointment $appointment)
    {
        CustomerAccess::remember($request, $appointment, CustomerAccess::REVIEW);

        return redirect()->route('review.create'); // clean URL, signature not left in history
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'reference' => 'required|string|max:20',
            'phone'     => 'required|string|max:20',
        ]);

        $appointment = CustomerAccess::find($data['reference'], $data['phone']);

        if (!$appointment) {
            return back()->withInput()->withErrors(['reference' => 'We could not find a booking with that reference and phone number.']);
        }

        CustomerAccess::remember($request, $appointment, CustomerAccess::REVIEW);

        return redirect()->route('review.create');
    }

    public function store(Request $request)
    {
        $appointment = CustomerAccess::reviewable($request);

        if (!$appointment) {
            return redirect()->route('review.create')->with('error', 'Your session expired. Please find your booking again.');
        }

        if ($reason = FeedbackService::blockedReason($appointment)) {
            return redirect()->route('review.create')->with('error', $reason);
        }

        $data = $request->validateWithBag('feedback', FeedbackService::RULES, FeedbackService::MESSAGES);
        FeedbackService::submit($appointment, $data + ['allow_public' => $request->boolean('allow_public')]);

        return redirect()->route('review.create')->with('success', 'Thank you for your feedback!');
    }

    /** Rate another visit: forget the one unlocked for reviewing. */
    public function forget(Request $request)
    {
        CustomerAccess::forget($request, CustomerAccess::REVIEW);

        return redirect()->route('review.create');
    }
}
