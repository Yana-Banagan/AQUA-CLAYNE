<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\DeliveryRestrictionService;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    private function customer(Request $request): Customer
    {
        return $request->user()->customer;
    }

    public function index(Request $request)
    {
        return $this->success(
            $this->customer($request)->addresses
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:50',
            'address_line' => 'required|string',
            'barangay' => 'required|string|max:100',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:14.22,14.34',
            'longitude' => 'nullable|numeric|between:121.37,121.47',
            'is_default' => 'boolean',
        ]);

        // Default city, province, and smart coordinates based on barangay
        $data['city'] = $data['city'] ?? 'Santa Cruz';
        $data['province'] = $data['province'] ?? 'Laguna';

        if (empty($data['latitude']) || empty($data['longitude'])) {
            $brgyLower = strtolower($data['barangay'] ?? '');
            if (str_contains($brgyLower, 'gatid')) {
                $data['latitude'] = 14.2691;
                $data['longitude'] = 121.4052;
            } elseif (str_contains($brgyLower, 'duhat')) {
                $data['latitude'] = 14.2725;
                $data['longitude'] = 121.4155;
            } elseif (str_contains($brgyLower, 'bubukal')) {
                $data['latitude'] = 14.2650;
                $data['longitude'] = 121.3980;
            } else {
                $data['latitude'] = null;
                $data['longitude'] = null;
            }
        }

        $customer = $this->customer($request);

        if (!DeliveryRestrictionService::isDeliveryAddressAllowed($data)) {
            return $this->error(
                DeliveryRestrictionService::RESTRICTION_MESSAGE,
                422
            );
        }

        if (!empty($data['is_default'])) {
            $customer->addresses()->update(['is_default' => false]);
        } elseif ($customer->addresses()->count() === 0) {
            // If it's the customer's first address, make it default automatically
            $data['is_default'] = true;
        }

        $address = $customer
            ->addresses()
            ->create($data);

        return $this->success(
            $address,
            'Address added',
            201
        );
    }

    public function update(
        Request $request,
        CustomerAddress $address
    ) {
        $customer = $this->customer($request);

        abort_if(
            $address->customer_id !== $customer->id,
            403,
            'You are not authorized to modify this address.'
        );

        $data = $request->validate([
            'label' => 'nullable|string|max:50',
            'address_line' => 'required|string',
            'barangay' => 'required|string|max:100',
            'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:14.22,14.34',
            'longitude' => 'nullable|numeric|between:121.37,121.47',
            'is_default' => 'boolean',
        ]);

        $data['city'] = $data['city'] ?? 'Santa Cruz';
        $data['province'] = $data['province'] ?? 'Laguna';

        if (empty($data['latitude']) || empty($data['longitude'])) {
            $brgyLower = strtolower($data['barangay'] ?? '');
            if (str_contains($brgyLower, 'gatid')) {
                $data['latitude'] = 14.2691;
                $data['longitude'] = 121.4052;
            } elseif (str_contains($brgyLower, 'duhat')) {
                $data['latitude'] = 14.2725;
                $data['longitude'] = 121.4155;
            } elseif (str_contains($brgyLower, 'bubukal')) {
                $data['latitude'] = 14.2650;
                $data['longitude'] = 121.3980;
            } else {
                $data['latitude'] = null;
                $data['longitude'] = null;
            }
        }

        if (!empty($data['is_default'])) {
            $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        if (!DeliveryRestrictionService::isDeliveryAddressAllowed($data)) {
            return $this->error(
                DeliveryRestrictionService::RESTRICTION_MESSAGE,
                422
            );
        }

        $address->update($data);

        return $this->success(
            $address,
            'Address updated'
        );
    }

    public function setDefault(
        Request $request,
        CustomerAddress $address
    ) {
        $customer = $this->customer($request);

        abort_if(
            $address->customer_id !== $customer->id,
            403,
            'You are not authorized to modify this address.'
        );

        $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return $this->success(
            $address,
            'Default address updated'
        );
    }

    public function destroy(
        Request $request,
        CustomerAddress $address
    ) {
        abort_if(
            $address->customer_id !== $this->customer($request)->id,
            403,
            'You are not authorized to delete this address.'
        );

        $customer = $this->customer($request);
        $wasDefault = $address->is_default;
        $address->delete();

        // Keep a sensible default for the next checkout when other saved
        // addresses remain.
        if ($wasDefault) {
            $customer->addresses()
                ->orderByDesc('created_at')
                ->first()
                ?->update(['is_default' => true]);
        }

        return $this->success(
            null,
            'Address deleted'
        );
    }

    private function isWithinSantaCruz(float $latitude, float $longitude): bool
    {
        return DeliveryRestrictionService::isWithinDeliveryZone($latitude, $longitude);
    }
}
