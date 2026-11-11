<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Staff;
use App\Models\SystemLog;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'rating'   => 'nullable|integer|between:1,5',
            'staff_id' => 'nullable|integer',
            'show'     => 'nullable|in:published,publishable,private',
        ]);

        $query = Feedback::with(['customer', 'staff', 'service', 'appointment'])->latest();

        if (!empty($filters['rating'])) {
            $query->where('rating', $filters['rating']);
        }
        if (!empty($filters['staff_id'])) {
            $query->where('staff_id', $filters['staff_id']);
        }
        match ($filters['show'] ?? null) {
            'published'   => $query->published(),
            'publishable' => $query->where('allow_public', true)->where('is_public', false),
            'private'     => $query->where('allow_public', false),
            default       => null,
        };

        $feedback = $query->paginate(20)->withQueryString();

        $summary = [
            'count'   => Feedback::count(),
            'average' => round((float) Feedback::avg('rating'), 1),
            'dist'    => Feedback::selectRaw('rating, COUNT(*) as n')->groupBy('rating')->pluck('n', 'rating')->all(),
            'waiting' => Feedback::where('allow_public', true)->where('is_public', false)->count(),
        ];

        $staffList = Staff::orderBy('name')->get(['id', 'name']);

        return view('admin.feedback.index', compact('feedback', 'summary', 'staffList', 'filters'));
    }

    /** Show or hide a review on the website (only when the customer agreed). */
    public function togglePublish(Feedback $feedback)
    {
        if (!$feedback->allow_public) {
            return back()->with('error', 'This customer didn’t agree to their review being shown publicly.');
        }

        $feedback->update(['is_public' => !$feedback->is_public]);
        SystemLog::record($feedback->is_public ? 'feedback.published' : 'feedback.hidden',
            "{$feedback->rating}-star review " . ($feedback->is_public ? 'published on' : 'hidden from') . ' the website', $feedback);

        return back()->with('success', $feedback->is_public ? 'Review is now shown on the website.' : 'Review hidden from the website.');
    }

    public function destroy(Feedback $feedback)
    {
        SystemLog::record('feedback.deleted', "{$feedback->rating}-star review deleted", $feedback,
            ['rating' => $feedback->rating, 'comment' => $feedback->comment], 'warning');
        $feedback->delete();

        return back()->with('success', 'Feedback deleted.');
    }
}
