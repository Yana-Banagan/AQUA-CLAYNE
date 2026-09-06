<?php

namespace App\Services;

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
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->key}",
            'Content-Type' => $mime,
        ])
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
    }

    public function signedUrl(
        string $bucket,
        string $path,
        int $expiresIn = 3600
    ): ?string {
        $response = Http::withHeaders([
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
    }
}
