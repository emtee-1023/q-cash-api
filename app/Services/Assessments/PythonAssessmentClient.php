<?php

namespace App\Services\Assessments;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PythonAssessmentClient
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $apiKey = null,
    ) {}

    /**
     * Send assessment inputs to the Python ML/Decision API and return the
     * decoded JSON response body.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function assess(array $payload): array
    {
        if ($this->baseUrl === null || $this->baseUrl === '') {
            throw new RuntimeException('The Python ML API base URL is not configured (QCASH_ML_API_URL).');
        }

        $request = Http::acceptJson()->timeout(60);

        if ($this->apiKey !== null && $this->apiKey !== '') {
            $request->withHeader('X-API-Key', $this->apiKey);
        }

        $response = $request->post(rtrim($this->baseUrl, '/').'/api/v1/assessments', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Python ML API request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
