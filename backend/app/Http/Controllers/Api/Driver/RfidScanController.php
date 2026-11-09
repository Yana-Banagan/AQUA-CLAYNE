<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\RfidTag;
use App\Models\RfidTransaction;
use Illuminate\Http\Request;

class RfidScanController extends Controller
{
    public function store(Request $request, Delivery $delivery)
    {
        $driver = $request->user()->driver;
        abort_unless($driver && $delivery->driver_id === $driver->id, 403);

        $data = $request->validate([
            'tag_uid' => 'required|string|max:64',
            'action' => 'required|in:pickup,delivery',
        ]);

        if (in_array($delivery->status, ['delivered', 'failed'], true)) {
            return $this->error('This delivery is already closed and cannot accept RFID scans.', 409);
        }

        $tag = RfidTag::query()->where('tag_uid', trim($data['tag_uid']))->first();
        if (!$tag) {
            return $this->error('That RFID tag is not registered to a gallon.', 404);
        }

        // RFID readers can emit the same UID several times while a tag is in
        // range. Treat a repeat on the same order/action within 30 seconds as
        // one scan rather than creating noisy duplicate audit rows.
        $transaction = RfidTransaction::query()
            ->where('rfid_tag_id', $tag->id)
            ->where('order_id', $delivery->order_id)
            ->where('action', $data['action'])
            ->where('created_at', '>=', now()->subSeconds(30))
            ->latest('created_at')
            ->first();
        if ($transaction) {
            return $this->success([
                'id' => $transaction->id,
                'tag_uid' => $tag->tag_uid,
                'gallon_id' => $tag->gallon_id,
                'order_id' => $delivery->order_id,
                'action' => $transaction->action,
                'created_at' => $transaction->created_at,
                'duplicate_suppressed' => true,
            ], 'This RFID scan was already recorded.');
        }

        $transaction = RfidTransaction::create([
            'rfid_tag_id' => $tag->id,
            'order_id' => $delivery->order_id,
            'action' => $data['action'],
            'scanned_by' => $request->user()->id,
        ]);
        $transaction->refresh();

        return $this->success([
            'id' => $transaction->id,
            'tag_uid' => $tag->tag_uid,
            'gallon_id' => $tag->gallon_id,
            'order_id' => $delivery->order_id,
            'action' => $transaction->action,
            'created_at' => $transaction->created_at,
        ], 'RFID scan recorded.');
    }
}
