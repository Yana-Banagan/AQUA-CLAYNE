<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintHistory;
use App\Models\AuditLog;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status');

        $query = Complaint::with(['customer.user', 'category'])->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $complaints = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => Complaint::count(),
            'pending' => Complaint::where('status', 'pending')->count(),
            'resolved' => Complaint::where('status', 'resolved')->count(),
        ];

        return view('admin.complaints.index', compact('complaints', 'stats', 'status'));
    }

    public function show(Complaint $complaint)
    {
        $complaint->load(['customer.user', 'category', 'history.changedBy']);
        return view('admin.complaints.show', compact('complaint'));
    }

    public function respond(Request $request, Complaint $complaint, NotificationService $notifications)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,in_investigation,resolved,dismissed',
            'note' => 'required|string|max:2000',
        ]);

        $complaint->update(['status' => $data['status']]);

        ComplaintHistory::create([
            'complaint_id' => $complaint->id,
            'note' => $data['note'],
            'changed_by' => $request->user()->id,
        ]);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'complaint.responded',
            'table_name' => 'complaints',
            'record_id' => $complaint->id,
        ]);

        $complaint->loadMissing('customer.user');
        if ($complaint->customer?->user) {
            $notifications->sendToUser(
                $complaint->customer->user,
                'Complaint Update',
                'Your complaint has been updated: ' . str_replace('_', ' ', $data['status']) . '.',
                ['entity_type' => 'complaint', 'entity_id' => (string) $complaint->id]
            );
        }

        return back()->with('success', 'Response recorded and complaint status updated.');
    }
}
