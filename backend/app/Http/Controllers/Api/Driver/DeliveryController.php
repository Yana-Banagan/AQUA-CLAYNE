<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Driver;
use App\Services\DeliveryService;
use Illuminate\Http\Request;
use App\Services\SupabaseStorageService;

class DeliveryController extends Controller
{
    public function __construct(
        private DeliveryService $deliveryService
    ) {
    }

    /**
     * Get the authenticated driver's Driver record.
     */
    private function driver(Request $request): Driver
    {
        return $request->user()->driver;
    }

    /**
     * Get active/non-completed deliveries
     * belonging ONLY to the authenticated driver.
     */
    public function index(Request $request)
    {
        $driver = $this->driver($request);

        $deliveries = $driver
            ->deliveries()
            ->whereNotIn(
                'status',
                ['delivered', 'failed']
            )
            ->with([
                'order.customer.user',
                'order.address',
            ])
            ->latest()
            ->get();

        return $this->success($deliveries);
    }

    /**
     * Get completed delivery history
     * belonging ONLY to the authenticated driver.
     */
    public function history(Request $request)
    {
        $driver = $this->driver($request);

        $deliveries = $driver
            ->deliveries()
            ->whereIn(
                'status',
                ['delivered', 'failed']
            )
            ->with([
                'order',
            ])
            ->latest()
            ->get();

        return $this->success($deliveries);
    }

    /**
     * Get one delivery.
     *
     * The delivery MUST belong to the authenticated driver.
     */
    public function show(
        Request $request,
        Delivery $delivery,
        SupabaseStorageService $storage
    ) {
        abort_if(
            $delivery->driver_id !== $this->driver($request)->id,
            403
        );

        $data = $delivery
            ->load(
                'order.items.product',
                'order.customer.user',
                'order.address'
            )
            ->toArray();

        if ($delivery->proof_photo_url) {
            $data['proof_photo_signed_url'] =
                $storage->signedUrl(
                    'delivery-proofs',
                    $delivery->proof_photo_url
                );
        }

        if ($delivery->signature_url) {
            $data['signature_signed_url'] =
                $storage->signedUrl(
                    'delivery-proofs',
                    $delivery->signature_url
                );
        }

        return $this->success($data);
    }
    /**
     * Update delivery status.
     *
     * The delivery MUST belong to the authenticated driver.
     */
    public function updateStatus(
        Request $request,
        Delivery $delivery
    ) {
        $driver = $this->driver($request);

        abort_if(
            $delivery->driver_id !== $driver->id,
            403,
            'You are not authorized to update this delivery.'
        );

        $data = $request->validate([
            'status' => [
                'required',
                'in:en_route_for_pickup,picked_up,at_station,out_for_delivery,delivered,failed',
            ],

            'note' => [
                'nullable',
                'string',
            ],

            'eta' => [
                'nullable',
                'date',
            ],
        ]);

        $updated = $this->deliveryService->updateStatus(
            $delivery,
            $data['status'],
            $data['note'] ?? null,
            $data['eta'] ?? null,
            $request->user()
        );

        return $this->success($updated);
    }

    public function uploadProof(
        Request $request,
        Delivery $delivery,
        SupabaseStorageService $storage
    ) {
        abort_if(
            $delivery->driver_id !== $this->driver($request)->id,
            403
        );

        $request->validate([
            'photo' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png',
                'max:5120',
            ],

            'signature' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png',
                'max:2048',
            ],
        ]);

        $photo = $request->file('photo');

        $photoPath = "{$delivery->id}/photo." . $photo->extension();

        $storage->upload(
            'delivery-proofs',
            $photoPath,
            file_get_contents($photo->getRealPath()),
            $photo->getMimeType()
        );

        $signaturePath = null;

        if ($request->hasFile('signature')) {
            $signature = $request->file('signature');

            $signaturePath =
                "{$delivery->id}/signature." .
                $signature->extension();

            $storage->upload(
                'delivery-proofs',
                $signaturePath,
                file_get_contents($signature->getRealPath()),
                $signature->getMimeType()
            );
        }

        $delivery->update([
            'proof_photo_url' => $photoPath,
            'signature_url' => $signaturePath,
        ]);

        return $this->success(
            $delivery->fresh(),
            'Proof uploaded'
        );
    }

    public function collectCash(Request $request, Delivery $delivery)
    {
        $driver = $this->driver($request);
        abort_if($delivery->driver_id !== $driver->id, 403, 'You are not authorized to update this delivery.');
        if ($delivery->status !== 'out_for_delivery' || $delivery->order->payment_method !== 'cash')
            return $this->error('Cash cannot be collected at this stage.', 422);
        $data = $request->validate(['amount' => 'required|numeric|min:0']);
        $payment = $delivery->order->payments()->where('method', 'cash')->latest()->firstOrFail();
        if ((float) $data['amount'] < (float) $payment->amount)
            return $this->error('The full cash amount is required.', 422);
        $payment->update(['status' => 'paid', 'paid_at' => now()]);
        return $this->success($payment, 'Cash payment recorded.');
    }
}
