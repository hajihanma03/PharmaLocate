<?php

namespace App\Services;

use App\Models\Geofence;
use App\Models\Pharmacy;
use Predis\Client;
use Throwable;

class Tile38Service
{
    private ?Client $client = null;

    public function isEnabled(): bool
    {
        return (bool) config('tile38.enabled');
    }

    public function isAvailable(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $response = $this->client()->executeRaw(['PING']);

            return is_string($response) && strtoupper($response) === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }

    public function status(): string
    {
        if (! $this->isEnabled()) {
            return 'disabled';
        }

        return $this->isAvailable() ? 'ok' : 'unavailable';
    }

    public function syncGeofence(Geofence $geofence): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        if (! $geofence->is_active) {
            $this->removeGeofence($geofence->id);

            return;
        }

        $this->client()->executeRaw([
            'SET',
            config('tile38.geofences_collection'),
            (string) $geofence->id,
            'POINT',
            (string) $geofence->center_latitude,
            (string) $geofence->center_longitude,
        ]);
    }

    public function syncPharmacy(Pharmacy $pharmacy): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        if (! $pharmacy->is_active || $pharmacy->latitude === null || $pharmacy->longitude === null) {
            $this->removePharmacy($pharmacy->id);

            return;
        }

        $this->client()->executeRaw([
            'SET',
            config('tile38.pharmacies_collection'),
            (string) $pharmacy->id,
            'POINT',
            (string) $pharmacy->latitude,
            (string) $pharmacy->longitude,
        ]);
    }

    public function removeGeofence(int $geofenceId): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $this->client()->executeRaw([
            'DEL',
            config('tile38.geofences_collection'),
            (string) $geofenceId,
        ]);
    }

    public function removePharmacy(int $pharmacyId): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $this->client()->executeRaw([
            'DEL',
            config('tile38.pharmacies_collection'),
            (string) $pharmacyId,
        ]);
    }

    /**
     * Active geofence IDs whose radius contains the point (Tile38 NEARBY + MySQL radius).
     *
     * @return int[]|null Null when Tile38 is not used (caller should fall back).
     */
    public function geofenceIdsContainingPoint(float $lat, float $lng): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $candidateIds = $this->nearbyGeofenceIds($lat, $lng, 10000);

            if ($candidateIds === null) {
                return null;
            }

            if ($candidateIds === []) {
                return [];
            }

            return Geofence::query()
                ->whereIn('id', $candidateIds)
                ->where('is_active', true)
                ->get()
                ->filter(fn (Geofence $zone) => $zone->containsPoint($lat, $lng))
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return int[]|null
     */
    public function nearbyGeofenceIds(float $lat, float $lng, int $radiusMeters = 10000): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $response = $this->client()->executeRaw([
                'NEARBY',
                config('tile38.geofences_collection'),
                'POINT',
                (string) $lat,
                (string) $lng,
                (string) $radiusMeters,
            ]);

            return array_keys($this->parseNearbyDistancesMeters($response, $lat, $lng));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Pharmacy distances in km keyed by pharmacy ID (Tile38 NEARBY).
     *
     * @return array<int, float>|null Null when Tile38 is not used.
     */
    public function pharmacyDistancesKm(float $lat, float $lng, int $radiusMeters = 50000): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        try {
            $response = $this->client()->executeRaw([
                'NEARBY',
                config('tile38.pharmacies_collection'),
                'POINT',
                (string) $lat,
                (string) $lng,
                (string) $radiusMeters,
            ]);

            return $this->parseNearbyDistancesKm($response, $lat, $lng);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, float> Object ID => distance in meters
     */
    private function parseNearbyDistancesMeters(mixed $response, float $originLat, float $originLng): array
    {
        $distances = [];

        foreach ($this->nearbyResultRows($response) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parsed = $this->parseNearbyRow($item, $originLat, $originLng);
            if ($parsed !== null) {
                [$id, $meters] = $parsed;
                $distances[$id] = $meters;
            }
        }

        return $distances;
    }

    /**
     * @return array<int, array>
     */
    private function nearbyResultRows(mixed $response): array
    {
        if (! is_array($response)) {
            return [];
        }

        if (isset($response[1]) && is_array($response[1])) {
            return $response[1];
        }

        return $response;
    }

    /**
     * @return array{0:int,1:float}|null
     */
    private function parseNearbyRow(array $item, float $originLat, float $originLng): ?array
    {
        $id = null;
        $meters = null;
        $objectJson = null;

        if (isset($item[0]) && is_numeric($item[0]) && isset($item[1]) && is_string($item[1])) {
            $id = (int) $item[0];
            $objectJson = $item[1];
        } else {
            for ($i = 0, $count = count($item); $i < $count - 1; $i++) {
                if (($item[$i] ?? null) === 'id') {
                    $id = (int) $item[$i + 1];
                }
                if (($item[$i] ?? null) === 'object') {
                    $objectJson = is_string($item[$i + 1]) ? $item[$i + 1] : json_encode($item[$i + 1]);
                }
                if (($item[$i] ?? null) === 'distance') {
                    $meters = (float) $item[$i + 1];
                }
            }
        }

        if ($id === null) {
            return null;
        }

        if ($meters === null && $objectJson) {
            $object = json_decode($objectJson, true);
            $coords = $object['coordinates'] ?? null;
            if (is_array($coords) && count($coords) >= 2) {
                $meters = Geofence::haversineKm($originLat, $originLng, (float) $coords[1], (float) $coords[0]) * 1000;
            }
        }

        if ($meters === null) {
            return null;
        }

        return [$id, $meters];
    }

    public function syncAll(): array
    {
        if (! $this->isAvailable()) {
            return ['geofences' => 0, 'pharmacies' => 0, 'status' => $this->status()];
        }

        $geofenceCount = 0;
        $pharmacyCount = 0;

        Geofence::query()->each(function (Geofence $geofence) use (&$geofenceCount) {
            $this->syncGeofence($geofence);
            if ($geofence->is_active) {
                $geofenceCount++;
            }
        });

        Pharmacy::query()->each(function (Pharmacy $pharmacy) use (&$pharmacyCount) {
            $this->syncPharmacy($pharmacy);
            if ($pharmacy->is_active && $pharmacy->latitude !== null && $pharmacy->longitude !== null) {
                $pharmacyCount++;
            }
        });

        return [
            'geofences' => $geofenceCount,
            'pharmacies' => $pharmacyCount,
            'status' => 'ok',
        ];
    }

    private function parseNearbyDistancesKm(mixed $response, float $originLat, float $originLng): array
    {
        $meters = $this->parseNearbyDistancesMeters($response, $originLat, $originLng);
        $km = [];
        foreach ($meters as $id => $distance) {
            $km[$id] = round($distance / 1000, 2);
        }

        return $km;
    }

    private function client(): Client
    {
        if ($this->client === null) {
            $this->client = new Client([
                'scheme' => 'tcp',
                'host' => config('tile38.host'),
                'port' => config('tile38.port'),
            ]);
        }

        return $this->client;
    }
}
