<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderWorkflowService;
use App\Services\RefillQueueService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RefillOperationController extends Controller
{
    public function transition(Request $request, Order $order, OrderWorkflowService $workflow)
    {
        try {
            $data = $request->validate([
                'status' => 'required|in:confirmed,rejected,cancelled',
                'reason' => 'nullable|string|max:1000'
            ]);

            $updatedOrder = $workflow->transition($order, $data['status'], $request->user(), $data['reason'] ?? null);

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success($updatedOrder, "Order status transitioned to {$data['status']}.");
            }

            return back()->with('success', "Order #{$order->order_number} status updated to " . ucwords(str_replace('_', ' ', $data['status'])) . ".");
        } catch (ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($e->validator->errors()->first() ?: 'Unable to update status.', 422, $e->errors());
            }
            throw $e;
        }
    }

    public function receive(Request $request, Order $order, RefillQueueService $queue)
    {
        try {
            $data = $request->validate([
                'received_count' => 'required|integer|min:0',
                'accepted_count' => 'required|integer|min:0',
                'damaged_count' => 'nullable|integer|min:0',
                'rejected_count' => 'nullable|integer|min:0',
                'condition_notes' => 'nullable|string|max:2000',
            ]);

            $intake = $queue->receiveIntake($order, $data, $request->user());

            $hasDiscrepancy = $intake->completed_at === null;
            $refillStarted = false;

            if (!$hasDiscrepancy) {
                try {
                    // The queue service retains the FIFO and one-active-refill rules.
                    // It will start immediately whenever this is the eligible order.
                    $queue->start($order->fresh(), $request->user());
                    $refillStarted = true;
                } catch (ValidationException $e) {
                    // The intake has still succeeded; the order remains safely queued.
                }
            }
            $msg = $hasDiscrepancy
                ? "Physical intake recorded for Order #{$order->order_number}. Discrepancy detected — resolve discrepancy before queue entry."
                : ($refillStarted
                    ? "Intake confirmed for Order #{$order->order_number}. Refill started automatically."
                    : "Container intake confirmed for Order #{$order->order_number}. Order has entered the Refill Queue.");

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success([
                    'intake' => $intake,
                    'has_discrepancy' => $hasDiscrepancy,
                    'refill_started' => $refillStarted,
                ], $msg);
            }

            return back()->with($hasDiscrepancy ? 'warning' : 'success', $msg);
        } catch (ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($e->validator->errors()->first() ?: 'Unable to process intake.', 422, $e->errors());
            }
            throw $e;
        }
    }

    public function resolveIntake(Request $request, Order $order, RefillQueueService $queue)
    {
        try {
            $data = $request->validate([
                'received_count' => 'nullable|integer|min:0',
                'accepted_count' => 'required|integer|min:1',
                'damaged_count' => 'nullable|integer|min:0',
                'rejected_count' => 'nullable|integer|min:0',
                'resolution_reason' => 'required|string|max:1000',
                'condition_notes' => 'nullable|string|max:2000',
            ]);

            $entry = $queue->resolveIntake($order, $data, $request->user());

            $msg = "Intake discrepancy resolved for Order #{$order->order_number}. Order entered Refill Queue at position #{$entry->queue_number}.";

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success($entry, $msg);
            }

            return back()->with('success', $msg);
        } catch (ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($e->validator->errors()->first() ?: 'Unable to resolve discrepancy.', 422, $e->errors());
            }
            throw $e;
        }
    }

    public function start(Request $request, Order $order, RefillQueueService $queue)
    {
        try {
            // The Refill action from an order that has just been picked up
            // records the expected gallon count as the default intake and
            // starts the queue entry directly, without an intake modal.
            if ($order->status === 'picked_up') {
                $expected = (int) $order->expected_empty_gallons;
                if ($expected < 1) {
                    $expected = (int) $order->items()->sum('quantity');
                }
                if ($expected < 1) {
                    throw ValidationException::withMessages([
                        'order' => ['This order has no eligible customer gallons to refill.'],
                    ]);
                }
                $queue->receiveIntake($order, [
                    'received_count' => $expected,
                    'accepted_count' => $expected,
                    'damaged_count' => 0,
                    'rejected_count' => 0,
                    'condition_notes' => 'Automatically recorded at station when refill was started.',
                ], $request->user());
                $order->refresh();
            } elseif ($order->status === 'received_at_station') {
                // Older or previously inspected arrivals may already have an
                // intake record but not yet have a queue entry.
                $queue->queue($order, $request->user());
                $order->refresh();
            }

            $entry = $queue->start($order, $request->user());

            $msg = "Refill started for Order #{$order->order_number}. Status updated to REFILLING.";

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success($entry, $msg);
            }

            return back()->with('success', $msg);
        } catch (ValidationException $e) {
            $errorMessage = $e->validator->errors()->first() ?: 'Unable to start refill.';
            if ($order->refresh()->status === 'waiting_in_queue') {
                $queuedMessage = "Order #{$order->order_number} entered the FIFO refill queue and will start when it is next and the station is available.";
                if ($request->wantsJson() || $request->ajax()) {
                    return $this->success($order->fresh(), $queuedMessage);
                }
                return back()->with('success', $queuedMessage);
            }
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($errorMessage, 409, $e->errors());
            }
            return back()->with('error', $errorMessage);
        }
    }

    public function complete(Request $request, Order $order, RefillQueueService $queue)
    {
        try {
            $entry = $queue->complete($order, $request->user());

            $msg = "Refill completed for Order #{$order->order_number}. Containers sealed and ready for delivery.";

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success($entry, $msg);
            }

            return back()->with('success', $msg);
        } catch (ValidationException $e) {
            $errorMessage = $e->validator->errors()->first() ?: 'Unable to complete refill.';
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($errorMessage, 409, $e->errors());
            }
            return back()->with('error', $errorMessage);
        }
    }

    public function priority(Request $request, Order $order, RefillQueueService $queue)
    {
        try {
            $data = $request->validate([
                'priority' => 'required|integer|min:-100|max:100',
                'reason' => 'required|string|max:1000'
            ]);

            $entry = $queue->changePriority($order, $data['priority'], $data['reason'], $request->user());

            $msg = "Queue priority updated for Order #{$order->order_number}. Action logged in system audit trail.";

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success($entry, $msg);
            }

            return back()->with('success', $msg);
        } catch (ValidationException $e) {
            $errorMessage = $e->validator->errors()->first() ?: 'Unable to update priority.';
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($errorMessage, 422, $e->errors());
            }
            return back()->with('error', $errorMessage);
        }
    }

    public function remove(Request $request, Order $order, RefillQueueService $queue)
    {
        try {
            $data = $request->validate([
                'reason' => 'required|string|max:1000'
            ]);

            $entry = $queue->removeFromQueue($order, $data['reason'], $request->user());

            $msg = "Order #{$order->order_number} removed from refill queue.";

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success($entry, $msg);
            }

            return back()->with('success', $msg);
        } catch (ValidationException $e) {
            $errorMessage = $e->validator->errors()->first() ?: 'Unable to remove order from queue.';
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($errorMessage, 422, $e->errors());
            }
            return back()->with('error', $errorMessage);
        }
    }
}
