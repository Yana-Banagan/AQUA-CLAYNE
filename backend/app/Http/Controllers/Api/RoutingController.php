<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Delivery;

class RoutingController extends Controller
{
    /** Build a route for this exact delivery; the origin always comes from server configuration. */
    public function delivery(Request $request, Delivery $delivery)
    {
        $user = $request->user();
        $role = $user?->role;
        if ($role === 'driver') {
            abort_unless($user->driver && $delivery->driver_id === $user->driver->id, 403);
        } elseif ($role === 'customer') {
            abort_unless($user->customer && $delivery->order?->customer_id === $user->customer->id, 403);
        } elseif (!in_array($role, ['admin', 'staff', 'super_admin'], true)) {
            abort(403);
        }

        $delivery->loadMissing('order.address');
        if ($delivery->order?->isStationPickup()) {
            return $this->error('Station pickup orders do not require a delivery route.', 422);
        }
        $address = $delivery->order?->address;
        $lat = $address?->latitude;
        $lng = $address?->longitude;
        if (!is_numeric($lat) || !is_numeric($lng) || (float) $lat < 14.22 || (float) $lat > 14.34 || (float) $lng < 121.37 || (float) $lng > 121.47 || ((float) $lat === 0.0 && (float) $lng === 0.0)) {
            return $this->error('This delivery address has no valid map pin. Update the saved address before generating a route.', 422);
        }

        return $this->buildRoute(
            (float) config('services.station.latitude'),
            (float) config('services.station.longitude'),
            (float) $lat,
            (float) $lng,
            (string) config('services.station.address'),
            collect([$address->address_line, $address->barangay, $address->city, $address->province])->filter()->implode(', '),
        );
    }

    /** Return the configured official station origin and Santa Cruz bounds */
    public function station()
    {
        return $this->success([
            'name' => (string) config('services.station.name', 'Aqua Clayne Water Refilling Station'),
            'address' => (string) config('services.station.address', 'Aqua Clayne Water Refilling Station, Batisan, Gatid, Santa Cruz, Laguna'),
            'latitude' => (float) config('services.station.latitude', 14.265111),
            'longitude' => (float) config('services.station.longitude', 121.405602),
            'bounds' => config('services.station.bounds', [
                'min_latitude' => 14.22,
                'max_latitude' => 14.34,
                'min_longitude' => 121.37,
                'max_longitude' => 121.47,
            ]),
        ]);
    }

    public function route(Request $request)
    {
        return $this->error('Use the delivery route endpoint. Route origins are strictly fixed to the official Aqua Clayne station in Batisan, Gatid, Santa Cruz, Laguna. Arbitrary starting pins or driver locations cannot be selected as delivery origins.', 422);
    }

    private function calculateDirectDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    private function buildFallbackRoute(float $originLat, float $originLng, float $destinationLat, float $destinationLng, string $originAddress, string $destinationAddress)
    {
        $directDistance = $this->calculateDirectDistance($originLat, $originLng, $destinationLat, $destinationLng);
        // Santa Cruz Laguna road network multiplier (~1.34x Euclidean distance)
        $roadDistance = round($directDistance * 1.34, 1);
        // Average urban delivery vehicle speed (25 km/h = 6.94 m/s)
        $duration = round($roadDistance / 6.94);

        $coordinates = [];
        $segments = 5;
        for ($i = 0; $i <= $segments; $i++) {
            $t = $i / (float) $segments;
            $coordinates[] = [
                round($originLng + $t * ($destinationLng - $originLng), 6),
                round($originLat + $t * ($destinationLat - $originLat), 6),
            ];
        }

        return $this->success([
            'coordinates' => $coordinates,
            'distance' => $roadDistance,
            'duration' => $duration,
            'estimated' => true,
            'is_fallback' => true,
            'disclaimer' => 'Estimated travel time only. Actual delivery duration may vary based on traffic, weather, and road conditions.',
            'origin' => ['latitude' => $originLat, 'longitude' => $originLng, 'address' => $originAddress],
            'destination' => ['latitude' => $destinationLat, 'longitude' => $destinationLng, 'address' => $destinationAddress],
            'point_a' => [
                'code' => 'A',
                'label' => 'Point A (Station Origin)',
                'name' => (string) config('services.station.name', 'Aqua Clayne Water Refilling Station'),
                'address' => $originAddress,
                'latitude' => $originLat,
                'longitude' => $originLng,
            ],
            'point_b' => [
                'code' => 'B',
                'label' => 'Point B (Customer Destination)',
                'address' => $destinationAddress,
                'latitude' => $destinationLat,
                'longitude' => $destinationLng,
            ],
            'steps' => [
                [
                    'instruction' => 'Start from Aqua Clayne Station, Batisan, Gatid',
                    'distance' => round($roadDistance * 0.1, 1),
                    'duration' => round($duration * 0.1),
                    'type' => 'depart',
                ],
                [
                    'instruction' => 'Head towards customer delivery address in Santa Cruz',
                    'distance' => round($roadDistance * 0.8, 1),
                    'duration' => round($duration * 0.8),
                    'type' => 'continue',
                ],
                [
                    'instruction' => 'Arrive at destination: ' . ($destinationAddress ?: 'Customer Address'),
                    'distance' => round($roadDistance * 0.1, 1),
                    'duration' => round($duration * 0.1),
                    'type' => 'arrive',
                ],
            ],
        ]);
    }

    private function buildRoute(float $originLat, float $originLng, float $destinationLat, float $destinationLng, string $originAddress, string $destinationAddress)
    {
        if ($originLat < 14.22 || $originLat > 14.34 || $originLng < 121.37 || $originLng > 121.47) {
            return $this->error('The Aqua Clayne station route origin is not configured with valid Santa Cruz coordinates.', 503);
        }

        $baseUrl = rtrim((string) config('services.routing.base_url', 'https://router.project-osrm.org'), '/');
        if ($baseUrl === '') {
            $baseUrl = 'https://router.project-osrm.org';
        }

        try {
            $response = Http::acceptJson()->timeout(6)->get(
                $baseUrl . '/route/v1/driving/' .
                $originLng . ',' . $originLat . ';' . $destinationLng . ',' . $destinationLat,
                ['overview' => 'full', 'geometries' => 'geojson', 'steps' => 'true', 'continue_straight' => 'false'],
            );
            if (!$response->successful() || !is_array($response->json('routes.0.geometry.coordinates')) || count($response->json('routes.0.geometry.coordinates')) < 2) {
                return $this->buildFallbackRoute($originLat, $originLng, $destinationLat, $destinationLng, $originAddress, $destinationAddress);
            }

            $route = $response->json('routes.0');
            return $this->success([
                'coordinates' => $route['geometry']['coordinates'],
                'distance' => $route['distance'] ?? null,
                'duration' => $route['duration'] ?? null,
                'estimated' => true,
                'is_fallback' => false,
                'disclaimer' => 'Estimated travel time only. Actual delivery duration may vary based on traffic, weather, and road accessibility.',
                'origin' => ['latitude' => $originLat, 'longitude' => $originLng, 'address' => $originAddress],
                'destination' => ['latitude' => $destinationLat, 'longitude' => $destinationLng, 'address' => $destinationAddress],
                'point_a' => [
                    'code' => 'A',
                    'label' => 'Point A (Station Origin)',
                    'name' => (string) config('services.station.name', 'Aqua Clayne Water Refilling Station'),
                    'address' => $originAddress,
                    'latitude' => $originLat,
                    'longitude' => $originLng,
                ],
                'point_b' => [
                    'code' => 'B',
                    'label' => 'Point B (Customer Destination)',
                    'address' => $destinationAddress,
                    'latitude' => $destinationLat,
                    'longitude' => $destinationLng,
                ],
                'steps' => collect($route['legs'] ?? [])->flatMap(fn ($leg) => $leg['steps'] ?? [])->map(function ($step) {
                    $type = $step['maneuver']['type'] ?? 'continue';
                    $modifier = $step['maneuver']['modifier'] ?? null;
                    $verb = match ($type) {
                        'depart' => 'Start',
                        'turn' => 'Turn',
                        'new name' => 'Continue onto',
                        'continue' => 'Continue',
                        'merge' => 'Merge',
                        'roundabout', 'rotary' => 'Enter roundabout',
                        'arrive' => 'Arrive at',
                        default => ucfirst($type),
                    };
                    $instruction = trim($verb . ($modifier ? ' ' . $modifier : '') . (!empty($step['name']) ? ' onto ' . $step['name'] : ''));
                    return [
                        'instruction' => $instruction,
                        'distance' => $step['distance'] ?? null,
                        'duration' => $step['duration'] ?? null,
                        'type' => $type,
                    ];
                })->values(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            return $this->buildFallbackRoute($originLat, $originLng, $destinationLat, $destinationLng, $originAddress, $destinationAddress);
        }
    }
}
