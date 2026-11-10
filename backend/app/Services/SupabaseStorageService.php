<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SupabaseStorageService
{
    private string $url;
    private string $key;

    public function __construct()
    {
        $this->url = config('services.supabase.url');
        $this->key = config('services.supabase.service_role_key');
    }

    public function upload(
        string $bucket,
        string $path,
        string $contents,
        string $mime
    ): string {
        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->key}",
                'Content-Type' => $mime,
            ])
                ->connectTimeout(5)
                ->timeout(20)
                ->withBody($contents, $mime)
                ->put(
                    "{$this->url}/storage/v1/object/{$bucket}/{$path}"
                );

            if (!$response->successful()) {
                throw new \Exception(
                    'Storage upload failed: ' . $response->body()
                );
            }

            return $path;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Supabase storage upload failed for {$bucket}/{$path}: " . $e->getMessage());
            throw $e;
        }
    }

    public function ensurePrivateBucket(string $bucket): void
    {
        $headers = ['Authorization' => "Bearer {$this->key}"];
        $existing = Http::withHeaders($headers)
            ->get("{$this->url}/storage/v1/bucket/{$bucket}");

        if ($existing->successful()) {
            return;
        }

        $bucketMissing = $existing->status() === 404 ||
            (int) $existing->json('statusCode') === 404;

        if (!$bucketMissing) {
            throw new \Exception('Unable to check storage bucket: ' . $existing->body());
        }

        $created = Http::withHeaders($headers)
            ->post("{$this->url}/storage/v1/bucket", [
                'id' => $bucket,
                'name' => $bucket,
                'public' => false,
            ]);

        if (!$created->successful() && $created->status() !== 409) {
            throw new \Exception('Unable to create storage bucket: ' . $created->body());
        }
    }

    public function signedUrl(
        string $bucket,
        string $path,
        int $expiresIn = 3600
    ): ?string {
        try {
            $response = Http::timeout(5)->withHeaders([
                'Authorization' => "Bearer {$this->key}",
            ])->post(
                "{$this->url}/storage/v1/object/sign/{$bucket}/{$path}",
                [
                    'expiresIn' => $expiresIn,
                ]
            );

            if (!$response->successful()) {
                return null;
            }

            return $this->url .
                '/storage/v1' .
                $response->json('signedURL');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("SupabaseStorageService signedUrl failed for {$bucket}/{$path}: " . $e->getMessage());
            return null;
        }
    }

    public function signedProfilePhotoUrl(string $userId, ?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $cacheKey = 'profile-photo-url:' . $userId . ':' . sha1($path);
        return Cache::store('file')->remember($cacheKey, now()->addHours(6), function () use ($path) {
            return $this->signedUrl('profile-photos', $path, 86400);
        });
    }
}
