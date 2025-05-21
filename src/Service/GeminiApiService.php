<?php
// src/Service/GeminiAPI.php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiAPI
{
    private $client;
    private $apiKey;

    public function __construct(HttpClientInterface $client, string $apiKey)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    public function getChatResponse(string $message): string
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $this->apiKey;

        $response = $this->client->request('POST', $url, [
            'json' => [
                'contents' => [[
                    'parts' => [[ 'text' => $message ]]
                ]]
            ]
        ]);

        $data = $response->toArray(false);

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Pas de réponse.';
    }
}
