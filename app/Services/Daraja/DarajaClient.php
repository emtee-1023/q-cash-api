<?php

namespace App\Services\Daraja;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DarajaClient
{
    public function __construct(
        private readonly ?string $baseUrl = null,
        private readonly ?string $consumerKey = null,
        private readonly ?string $consumerSecret = null,
    ) {}

    public function accessToken(): string
    {
        if (blank($this->consumerKey) || blank($this->consumerSecret)) {
            throw new RuntimeException('Daraja consumer credentials are not configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($this->consumerKey, $this->consumerSecret)
            ->connectTimeout(5)
            ->timeout(15)
            ->get($this->baseUrl().'/oauth/v1/generate?grant_type=client_credentials')
            ->throw();

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Daraja did not return an access token.');
        }

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function initiateStkPush(array $payload): array
    {
        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(30)
            ->post($this->baseUrl().'/mpesa/stkpush/v1/processrequest', $payload)
            ->throw();

        return $response->json() ?? [];
    }

    private function baseUrl(): string
    {
        if (filled($this->baseUrl)) {
            return rtrim((string) $this->baseUrl, '/');
        }

        return config('services.mpesa.environment') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }
}
