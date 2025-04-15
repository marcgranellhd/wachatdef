<?php

namespace App\Services;

use App\Models\Bot;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    protected Client $client;
    protected string $apiKey;
    protected string $model;
    protected Bot $bot;

    public function __construct(Bot $bot)
    {
        $this->bot = $bot;
        $this->apiKey = config('services.openai.api_key');
        $this->model = $this->bot->ai_model ?? 'gpt-4o';
        
        $this->client = new Client([
            'base_uri' => 'https://api.openai.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function generateResponse(Conversation $conversation, string $userMessage)
    {
        try {
            // Get conversation history (limited to max_context_messages)
            $messages = $this->prepareConversationHistory($conversation);
            
            // Add product context if needed
            $messages = $this->addProductContext($messages, $userMessage);
            
            // Add the system prompt from the bot configuration
            array_unshift($messages, [
                'role' => 'system',
                'content' => $this->bot->system_prompt
            ]);
            
            // Make the API call
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model' => $this->model,
                    'messages' => $messages,
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                ]
            ]);
            
            $result = json_decode($response->getBody()->getContents(), true);
            $aiResponse = $result['choices'][0]['message']['content'] ?? null;
            
            if ($aiResponse) {
                // Store the AI response
                $message = new Message([
                    'conversation_id' => $conversation->id,
                    'direction' => 'outgoing',
                    'message' => $aiResponse,
                    'message_type' => 'text',
                ]);
                $message->save();
                
                return $aiResponse;
            }
            
            return "Lo siento, no pude procesar tu solicitud en este momento.";
            
        } catch (\Exception $e) {
            Log::error('OpenAI Service Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return "Lo siento, tuvimos un problema técnico. Por favor, intenta nuevamente más tarde.";
        }
    }

    private function prepareConversationHistory(Conversation $conversation): array
    {
        $maxMessages = $this->bot->max_context_messages ?? 10;
        $recentMessages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->take($maxMessages)
            ->get()
            ->reverse();
        
        $messages = [];
        foreach ($recentMessages as $message) {
            $role = $message->direction === 'incoming' ? 'user' : 'assistant';
            $messages[] = [
                'role' => $role,
                'content' => $message->message
            ];
        }
        
        return $messages;
    }

    private function addProductContext(array $messages, string $userMessage): array
    {
        // Search for product references in the user message
        $products = Product::where('tenant_id', $this->bot->tenant_id)
            ->where(function($query) use ($userMessage) {
                $keywords = $this->extractKeywords($userMessage);
                foreach ($keywords as $keyword) {
                    $query->orWhere('name', 'like', "%{$keyword}%")
                          ->orWhere('description', 'like', "%{$keyword}%")
                          ->orWhere('categories', 'like', "%{$keyword}%");
                }
            })
            ->limit(3)
            ->get();
        
        // If products found, add them to the context
        if ($products->count() > 0) {
            $productContext = "Información sobre productos relevantes:\n\n";
            
            foreach ($products as $product) {
                $productContext .= "Nombre: {$product->name}\n";
                $productContext .= "Precio: {$product->price}\n";
                $productContext .= "Descripción: {$product->description}\n";
                $productContext .= "SKU: {$product->sku}\n";
                $productContext .= "Stock: {$product->stock}\n\n";
            }
            
            // Insert product context before the user message
            array_unshift($messages, [
                'role' => 'system',
                'content' => $productContext
            ]);
        }
        
        return $messages;
    }

    private function extractKeywords(string $text): array
    {
        // Simple keyword extraction (this could be enhanced with NLP)
        $text = strtolower($text);
        $words = preg_split('/\W+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filter out common words (could be expanded)
        $stopWords = ['el', 'la', 'los', 'las', 'un', 'una', 'unos', 'unas', 'y', 'o', 'a', 'de', 'en', 'por', 'con', 'para'];
        $keywords = array_diff($words, $stopWords);
        
        // Return only words with 3+ characters
        return array_filter($keywords, fn($word) => strlen($word) >= 3);
    }
}
