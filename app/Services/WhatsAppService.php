<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\Message;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected Client $client;
    protected Bot $bot;
    protected string $apiUrl;
    protected array $apiCredentials;

    public function __construct(Bot $bot)
    {
        $this->bot = $bot;
        $this->apiCredentials = $bot->api_credentials;
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiCredentials['token'],
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function sendMessage(string $to, string $text, array $attachments = [])
    {
        try {
            $payload = [
                'to' => $this->formatPhoneNumber($to),
                'type' => 'text',
                'text' => [
                    'body' => $text
                ]
            ];
            
            $response = $this->client->post('/messages', [
                'json' => $payload
            ]);
            
            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }
            
            Log::error('WhatsApp API Error', [
                'status' => $response->getStatusCode(),
                'response' => $response->getBody()->getContents()
            ]);
            
            return false;
        } catch (\Exception $e) {
            Log::error('WhatsApp Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }

    public function processIncomingMessage(array $payload)
    {
        // Extract data from webhook payload
        $phone = $payload['from'] ?? null;
        $messageText = $payload['text']['body'] ?? null;
        $messageId = $payload['id'] ?? null;
        
        if (!$phone || !$messageText) {
            return false;
        }
        
        // Find or create conversation
        $conversation = Conversation::firstOrCreate(
            ['bot_id' => $this->bot->id, 'customer_phone' => $phone],
            ['status' => 'active', 'last_message_at' => now()]
        );
        
        // Store incoming message
        $message = new Message([
            'conversation_id' => $conversation->id,
            'direction' => 'incoming',
            'message' => $messageText,
            'message_type' => 'text',
            'external_id' => $messageId,
        ]);
        $message->save();
        
        // Update conversation timestamp
        $conversation->update(['last_message_at' => now()]);
        
        return $conversation;
    }

    private function formatPhoneNumber(string $phone): string
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Ensure it has country code
        if (strlen($phone) < 10) {
            throw new \InvalidArgumentException("Invalid phone number format");
        }
        
        return $phone;
    }
}
