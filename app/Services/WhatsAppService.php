<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    private string $token;
    private string $phoneId;

    public function __construct()
    {
        $this->token = config('WHATSAPP_TOKEN');
        $this->phoneId = config('WHATSAPP_PHONE_ID'); // ID номера из Meta
    }

    public function sendMessage(string $to, string $message): array
    {
        $url = 'https://graph.facebook.com/v22.0/' . $this->phoneId . '/messages';

        $response = Http::withToken($this->token)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        return $response->json();
    }
}
