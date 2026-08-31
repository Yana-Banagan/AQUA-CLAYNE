<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAddress;
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
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_default' => 'boolean',
        ]);

        $address = $this
            ->customer($request)
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
        abort_if(
            $address->customer_id !== $this->customer($request)->id,
            403,
            'You are not authorized to modify this address.'
        );

        $address->update(
            $request->validate([
                'label' => 'nullable|string',
                'address_line' => 'required|string',
            ])
        );

        return $this->success(
            $address,
            'Address updated'
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

        $address->delete();

        return $this->success(
            null,
            'Address deleted'
        );
    }
}
