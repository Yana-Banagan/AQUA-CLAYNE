<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Order;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    private function customer(Request $request)
    {
        return $request->user()->customer;
    }

    public function index(Request $request)
    {
        $customer = $this->customer($request);
        if (!$customer) {
            return $this->success([]);
        }

        $complaints = $customer->complaints()
            ->with(['category', 'history' => function ($q) {
                $q->latest('created_at');
            }])
            ->latest('created_at')
            ->get();

        $formatted = $complaints->map(function ($c) {
            $latestHistory = $c->history->first();
            return [
                'id' => (string) $c->id,
                'complaint_category_id' => (string) $c->category_id,
                'category' => $c->category ? [
                    'id' => (string) $c->category->id,
                    'name' => $c->category->name,
                ] : null,
                'subject' => $c->subject,
                'description' => $c->description,
                'status' => strtoupper($c->status ?? 'PENDING'),
                'admin_response' => $latestHistory ? $latestHistory->note : null,
                'created_at' => $c->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ];
        });

        return $this->success($formatted);
    }

    public function show(Request $request, Complaint $complaint)
    {
        $customer = $this->customer($request);
        abort_if(
            !$customer || $complaint->customer_id !== $customer->id,
            403,
            'You are not authorized to view this complaint.'
        );

        $complaint->load(['category', 'history' => function ($q) {
            $q->latest('created_at');
        }]);

        $latestHistory = $complaint->history->first();

        return $this->success([
            'id' => (string) $complaint->id,
            'complaint_category_id' => (string) $complaint->category_id,
            'category' => $complaint->category ? [
                'id' => (string) $complaint->category->id,
                'name' => $complaint->category->name,
            ] : null,
            'subject' => $complaint->subject,
            'description' => $complaint->description,
            'status' => strtoupper($complaint->status ?? 'PENDING'),
            'admin_response' => $latestHistory ? $latestHistory->note : null,
            'created_at' => $complaint->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ]);
    }

    public function store(Request $request)
    {
        $customer = $this->customer($request);
        abort_if(!$customer, 403, 'Authenticated user is not a customer.');

        $data = $request->validate([
            'complaint_category_id' => 'required|uuid|exists:complaint_categories,id',
            'subject' => 'nullable|string|max:150',
            'description' => 'required|string|min:10',
            'order_id' => 'nullable|uuid|exists:orders,id',
        ]);

        $category = ComplaintCategory::find($data['complaint_category_id']);
        $subject = !empty($data['subject'])
            ? $data['subject']
            : ($category ? $category->name . ' Concern' : 'Customer Concern');

        $description = $data['description'];
        if (!empty($data['order_id'])) {
            $order = Order::find($data['order_id']);
            if ($order) {
                $description = '[Related Order #' . ($order->order_number ?? substr($order->id, 0, 8)) . "]\n\n" . $description;
            }
        }

        $complaint = $customer->complaints()->create([
            'category_id' => $data['complaint_category_id'],
            'subject' => $subject,
            'description' => $description,
            'status' => 'pending',
        ]);

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'complaint.submitted',
            'table_name' => 'complaints',
            'record_id' => $complaint->id,
        ]);

        $complaint->load('category');

        return $this->success([
            'id' => (string) $complaint->id,
            'complaint_category_id' => (string) $complaint->category_id,
            'category' => $complaint->category ? [
                'id' => (string) $complaint->category->id,
                'name' => $complaint->category->name,
            ] : null,
            'subject' => $complaint->subject,
            'description' => $complaint->description,
            'status' => 'PENDING',
            'admin_response' => null,
            'created_at' => $complaint->created_at->toIso8601String(),
        ], 'Complaint submitted successfully.', 201);
    }

    public function categories()
    {
        $categories = ComplaintCategory::orderBy('name')->get(['id', 'name']);
        return $this->success($categories);
    }
}
