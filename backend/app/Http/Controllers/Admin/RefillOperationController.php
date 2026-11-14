<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderWorkflowService;
use App\Services\RefillOperationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RefillOperationController extends Controller
{
    public function transition(Request $request, Order $order, OrderWorkflowService $workflow)
    {
        try {
            $data = $request->validate([
                'status' => 'required|in:confirmed,rejected,cancelled',
                'reason' => 'nullable|string|max:1000',
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

    public function receive(Request $request, Order $order, RefillOperationService $refill)
    {
        try {
            $data = $request->validate([
                'received_count' => 'required|integer|min:0',
                'accepted_count' => 'required|integer|min:0',
                'damaged_count' => 'nullable|integer|min:0',
                'rejected_count' => 'nullable|integer|min:0',
                'condition_notes' => 'nullable|string|max:2000',
                'scanned_uids' => 'nullable|array',
                'scanned_uids.*' => 'string|max:64',
            ]);

            $intake = $refill->receiveIntake($order, $data, $request->user());

            if (!empty($data['scanned_uids'])) {
                $txService = app(\App\Services\RfidTransactionService::class);
                $device = \App\Models\RfidDevice::where('status', 'online')->orderByDesc('last_heartbeat_at')->first()
                    ?? \App\Models\RfidDevice::first();

                if ($device) {
                    foreach ($data['scanned_uids'] as $uid) {
                        $txService->validateAndRecordScan($device, $uid, 'STATION_INTAKE', $order->id, $request->user());
                    }
                }
            }

            $refillStarted = $order->fresh()->status === 'refilling';
            $hasDiscrepancy = $intake->completed_at === null;
            $message = $hasDiscrepancy
                ? "Physical intake recorded for Order #{$order->order_number}. Resolve the intake discrepancy before refilling."
                : ($refillStarted
                    ? "Intake confirmed for Order #{$order->order_number}. Refill started."
                    : "Intake confirmed for Order #{$order->order_number}. It is ready for staff to start refilling.");

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success([
                    'intake' => $intake,
                    'has_discrepancy' => $hasDiscrepancy,
                    'refill_started' => $refillStarted,
                ], $message);
            }

            return back()->with($hasDiscrepancy ? 'warning' : 'success', $message);
        } catch (ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($e->validator->errors()->first() ?: 'Unable to process intake.', 422, $e->errors());
            }
            throw $e;
        }
    }

    public function resolveIntake(Request $request, Order $order, RefillOperationService $refill)
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

            $intake = $refill->resolveIntake($order, $data, $request->user());
            $refillStarted = $order->fresh()->status === 'refilling';
            $message = $refillStarted
                ? "Intake discrepancy resolved for Order #{$order->order_number}. Refill started."
                : "Intake discrepancy resolved for Order #{$order->order_number}. It is ready for staff to start refilling.";

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success([
                    'intake' => $intake,
                    'refill_started' => $refillStarted,
                ], $message);
            }

            return back()->with('success', $message);
        } catch (ValidationException $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($e->validator->errors()->first() ?: 'Unable to resolve discrepancy.', 422, $e->errors());
            }
            throw $e;
        }
    }

    public function start(Request $request, Order $order, RefillOperationService $refill)
    {
        try {
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

                $refill->receiveIntake($order, [
                    'received_count' => $expected,
                    'accepted_count' => $expected,
                    'damaged_count' => 0,
                    'rejected_count' => 0,
                    'condition_notes' => 'Automatically recorded at station when refill was started.',
                ], $request->user());
                $order->refresh();
            }

            $updatedOrder = $order->status === 'refilling'
                ? $order->fresh()
                : $refill->start($order, $request->user());
            $message = "Refill started for Order #{$order->order_number}.";

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success($updatedOrder, $message);
            }

            return back()->with('success', $message);
        } catch (ValidationException $e) {
            $errorMessage = $e->validator->errors()->first() ?: 'Unable to start refill.';
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($errorMessage, 409, $e->errors());
            }
            return back()->with('error', $errorMessage);
        }
    }

    public function complete(Request $request, Order $order, RefillOperationService $refill)
    {
        try {
            $updatedOrder = $refill->complete($order, $request->user());
            $message = "Refill completed for Order #{$order->order_number}. Containers are ready for delivery or pickup.";

            if ($request->wantsJson() || $request->ajax()) {
                return $this->success($updatedOrder, $message);
            }

            return back()->with('success', $message);
        } catch (ValidationException $e) {
            $errorMessage = $e->validator->errors()->first() ?: 'Unable to complete refill.';
            if ($request->wantsJson() || $request->ajax()) {
                return $this->error($errorMessage, 409, $e->errors());
            }
            return back()->with('error', $errorMessage);
        }
    }
}
