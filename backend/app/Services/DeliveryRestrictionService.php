<?php

namespace App\Services;

class DeliveryRestrictionService
{
    public const ALLOWED_BARANGAYS = ['gatid', 'duhat', 'bubukal'];

    public const RESTRICTION_MESSAGE = 'Sorry! Aqua Clayne currently provides home delivery only in Gatid, Duhat, and Bubukal, Santa Cruz, Laguna. Please select an address within our delivery area.';

    // Bounding coordinates encompassing Gatid, Duhat, and Bubukal in Santa Cruz, Laguna
    public const MIN_LAT = 14.250;
    public const MAX_LAT = 14.295;
    public const MIN_LNG = 121.385;
    public const MAX_LNG = 121.435;

    // Santa Cruz municipality outer boundary
    public const SC_MIN_LAT = 14.22;
    public const SC_MAX_LAT = 14.34;
    public const SC_MIN_LNG = 121.37;
    public const SC_MAX_LNG = 121.47;

    /**
     * Determine if a given barangay name or full address string matches
     * one of our 3 permitted delivery barangays: Gatid, Duhat, Bubukal.
     */
    public static function isAllowedBarangay(?string $barangay, ?string $addressLine = null): bool
    {
        $normalized = self::normalizeString($barangay);
        foreach (self::ALLOWED_BARANGAYS as $allowed) {
            if ($normalized !== '' && (str_contains($normalized, $allowed) || $normalized === $allowed)) {
                return true;
            }
        }

        if ($addressLine) {
            $normalizedLine = self::normalizeString($addressLine);
            foreach (self::ALLOWED_BARANGAYS as $allowed) {
                if (str_contains($normalizedLine, $allowed)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Verify whether geographic coordinates fall within the Gatid, Duhat, Bubukal delivery zone.
     */
    public static function isWithinDeliveryZone(float $lat, float $lng): bool
    {
        if ($lat == 0.0 && $lng == 0.0) {
            return false;
        }

        return $lat >= self::MIN_LAT && $lat <= self::MAX_LAT
            && $lng >= self::MIN_LNG && $lng <= self::MAX_LNG;
    }

    /**
     * Validate an address record or data array against the 3-barangay delivery restriction.
     * Must be in Gatid, Duhat, or Bubukal, Santa Cruz, Laguna.
     */
    public static function isDeliveryAddressAllowed($address): bool
    {
        if (!$address) {
            return false;
        }

        $barangay = is_array($address) ? ($address['barangay'] ?? '') : ($address->barangay ?? '');
        $addressLine = is_array($address) ? ($address['address_line'] ?? '') : ($address->address_line ?? '');
        $lat = is_array($address) ? (float) ($address['latitude'] ?? 0) : (float) ($address->latitude ?? 0);
        $lng = is_array($address) ? (float) ($address['longitude'] ?? 0) : (float) ($address->longitude ?? 0);

        // Check barangay text match
        $barangayMatches = self::isAllowedBarangay($barangay, $addressLine);

        // Check coordinate zone
        $coordsInZone = self::isWithinDeliveryZone($lat, $lng);

        // Check Santa Cruz municipality bounds
        $coordsInSantaCruz = $lat >= self::SC_MIN_LAT && $lat <= self::SC_MAX_LAT
            && $lng >= self::SC_MIN_LNG && $lng <= self::SC_MAX_LNG;

        // Delivery service is strictly restricted to Gatid, Duhat, and Bubukal.
        // The barangay (or address line) MUST explicitly match one of these 3 allowed areas.
        if (!$barangayMatches) {
            return false;
        }

        // In addition, if coordinates are present, they must be within Santa Cruz bounds.
        if ($lat != 0.0 && $lng != 0.0 && !$coordsInSantaCruz) {
            return false;
        }

        return true;
    }

    private static function normalizeString(?string $str): string
    {
        if ($str === null) return '';
        $clean = strtolower($str);
        $clean = str_replace(['brgy.', 'brgy', 'barangay', ',', '.', '-', '_'], ' ', $clean);
        return trim(preg_replace('/\s+/', ' ', $clean));
    }
}

