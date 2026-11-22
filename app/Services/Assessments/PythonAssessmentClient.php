<?php

namespace App\Services\Assessments;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class PythonAssessmentClient
{
    public function __construct(
        private readonly ?string $baseUrl,
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

        $response = Http::acceptJson()
            ->timeout(60)
            ->post(rtrim($this->baseUrl, '/').'/assess', $payload);

        if ($response->failed()) {
            throw new RuntimeException('Python ML API request failed: '.$response->body());
        }

        return $response->json() ?? [];
    }
}
