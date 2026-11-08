<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LandingController extends Controller
{
    /**
     * Display the public landing page for Aqua Clayne.
     */
    public function index()
    {
        $station = [
            'name' => config('services.station.name', 'Aqua Clayne Water Refilling Station'),
            'tagline' => 'Purified Drinking Water',
            'headline' => 'Clean Water, Healthier Lives.',
            'address' => config('services.station.address', 'Aqua Clayne Water Refilling Station, 007 Batisan St., Brgy. Gatid, Sta Cruz Laguna'),
            'phone' => '+63 917 555 0199',
            'email' => 'support@aquaclayne.ph',
            'operating_hours' => 'Mon - Sat: 7:00 AM - 7:00 PM | Sun: 8:00 AM - 5:00 PM',
        ];

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('products')) {
                $products = \App\Models\Product::orderBy('is_active', 'desc')
                    ->orderBy('price', 'desc')
                    ->get();
            } else {
                $products = collect();
            }
        } catch (\Throwable $e) {
            Log::warning('Could not retrieve products from database: ' . $e->getMessage());
            $products = collect();
        }

        // Graceful fallback for unmigrated environments / testing
        if ($products->isEmpty()) {
            $products = collect([
                (object) [
                    'id' => 'prod-new-gallon',
                    'name' => 'New Gallon with Water',
                    'description' => 'A brand-new 5-gallon container filled with purified drinking water.',
                    'price' => 200.00,
                    'image_url' => null,
                    'product_type' => 'new_gallon',
                    'is_active' => true,
                ],
                (object) [
                    'id' => 'prod-purified-refill',
                    'name' => 'Purified Water Refill',
                    'description' => "Purified drinking water processed through the station's filtration system.",
                    'price' => 35.00,
                    'image_url' => null,
                    'product_type' => 'refill',
                    'is_active' => true,
                ],
                (object) [
                    'id' => 'prod-mineral-refill',
                    'name' => 'Mineral Water Refill',
                    'description' => 'Mineral water refill for customers who prefer mineral-enriched drinking water.',
                    'price' => 40.00,
                    'image_url' => null,
                    'product_type' => 'refill',
                    'is_active' => true,
                ],
                (object) [
                    'id' => 'prod-alkaline-refill',
                    'name' => 'Alkaline Water Refill',
                    'description' => 'High pH antioxidant water with enhanced alkalinity and superior cellular hydration.',
                    'price' => 50.00,
                    'image_url' => null,
                    'product_type' => 'refill',
                    'is_active' => false,
                ],
            ]);
        }

        return view('landing', compact('station', 'products'));
    }

    /**
     * Handle public contact inquiries.
     */
    public function submitContact(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'email' => 'required|email|max:150',
            'subject' => 'required|string|min:3|max:200',
            'message' => 'required|string|min:10|max:2000',
        ]);

        Log::info('Public contact inquiry received from landing page:', [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'ip' => $request->ip(),
            'timestamp' => now()->toIso8601String(),
        ]);

        $successMessage = 'Thank you for contacting Aqua Clayne! Your message has been received. Our team will get back to you shortly.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
            ]);
        }

        return redirect('/#contact')->with('contact_success', $successMessage);
    }
}

