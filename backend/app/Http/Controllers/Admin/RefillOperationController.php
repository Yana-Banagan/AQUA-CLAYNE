<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderWorkflowService;
use App\Services\RefillQueueService;
use Illuminate\Http\Request;

class RefillOperationController extends Controller
{
    public function transition(Request $request, Order $order, OrderWorkflowService $workflow)
    {
        $data = $request->validate(['status' => 'required|in:confirmed,rejected,cancelled', 'reason' => 'nullable|string|max:1000']);
        return $this->success($workflow->transition($order, $data['status'], $request->user(), $data['reason'] ?? null));
    }

    public function receive(Request $request, Order $order, RefillQueueService $queue)
    {
        $data = $request->validate([
            'received_count' => 'required|integer|min:0', 'accepted_count' => 'required|integer|min:0',
            'damaged_count' => 'nullable|integer|min:0', 'rejected_count' => 'nullable|integer|min:0',
            'condition_notes' => 'nullable|string|max:2000',
        ]);
        return $this->success($queue->receiveIntake($order, $data, $request->user()), 'Intake received and order queued.');
    }

    public function start(Request $request, Order $order, RefillQueueService $queue) { return $this->success($queue->start($order, $request->user()), 'Refill started.'); }
    public function complete(Request $request, Order $order, RefillQueueService $queue) { return $this->success($queue->complete($order, $request->user()), 'Refill completed.'); }

    public function priority(Request $request, Order $order, RefillQueueService $queue)
    {
        $data = $request->validate(['priority' => 'required|integer|min:-100|max:100', 'reason' => 'required|string|max:1000']);
        return $this->success($queue->changePriority($order, $data['priority'], $data['reason'], $request->user()), 'Queue priority updated.');
    }
}
