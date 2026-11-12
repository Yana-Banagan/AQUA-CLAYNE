<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check()
            && in_array(auth()->user()->role, ['admin']);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',

            'email' => 'required|email|max:150|unique:users,email',

            'phone' => 'required|string|max:30',

            'license_number' => 'nullable|string|max:50',

            'vehicle_plate' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ];
    }
}
