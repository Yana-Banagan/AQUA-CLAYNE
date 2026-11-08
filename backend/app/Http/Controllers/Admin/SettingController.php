<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SupabaseStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function index(SupabaseStorageService $storage)
    {
        $settings = [
            'station_name' => 'AQUA CLAYNE',
            'tagline' => 'Pure Water. Healthier Life.',
            'contact_phone' => '+63 917 555 0199',
            'contact_email' => 'support@aquaclayne.ph',
            'station_address' => config('services.station.address'),
            'station_latitude' => config('services.station.latitude'),
            'station_longitude' => config('services.station.longitude'),
            'operating_hours' => 'Mon - Sat: 7:00 AM - 7:00 PM | Sun: 8:00 AM - 5:00 PM',
            'business_model' => 'Customer-Owned Gallon Refill Service (FIFO Queue)',
            'daily_refill_capacity' => '500',
            'fcm_enabled' => true,
            'realtime_enabled' => true,
        ];

        $user = auth()->user();
        $adminAvatarUrl = $user?->avatar_path
            ? $storage->signedProfilePhotoUrl($user->id, $user->avatar_path)
            : null;

        return view('admin.settings.index', compact('settings', 'adminAvatarUrl'));
    }

    public function update(Request $request, SupabaseStorageService $storage)
    {
        $data = $request->validate([
            'station_name' => 'required|string|max:255',
            'contact_phone' => 'required|string|max:50',
            'contact_email' => 'required|email',
            'station_address' => 'required|string|max:500',
            'operating_hours' => 'required|string|max:255',
            'profile_photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        if ($request->hasFile('profile_photo')) {
            $photo = $data['profile_photo'];
            $path = "profiles/{$request->user()->id}/avatar-" . Str::uuid() . ".{$photo->extension()}";

            $storage->upload(
                'profile-photos',
                $path,
                file_get_contents($photo->getRealPath()),
                $photo->getMimeType(),
            );

            $request->user()->update(['avatar_path' => $path]);
        }

        return back()->with('success', $request->hasFile('profile_photo')
            ? 'Profile picture and station settings updated.'
            : 'Station configuration and operational settings updated.');
    }
}
