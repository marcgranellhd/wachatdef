<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Services\OpenAIService;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handleWhatsAppWebhook(Request $request, string $botId)
    {
        Log::info('WhatsApp Webhook received', ['payload' => $request->all()]);
        
        // Verify the bot exists and is active
        $bot = Bot::find($botId);
        if (!$bot || !$bot->is_active) {
            Log::warning('Webhook called for inactive or non-existent bot', ['bot_id' => $botId]);
            return response()->json(['status' => 'error', 'message' => 'Bot not found or inactive'], 404);
        }
        
        // Handle verification challenge if present (for WhatsApp webhook setup)
        if ($request->has('hub.challenge')) {
            return response($request->input('hub.challenge'));
        }
        
        try {
            // Initialize services
            $whatsappService = new WhatsAppService($bot);
            $openaiService = new OpenAIService($bot);
            
            // Process incoming message
            $conversation = $whatsappService->processIncomingMessage($request->all());
            if (!$conversation) {
                return response()->json(['status' => 'error', 'message' => 'Invalid message format'], 400);
            }
            
            // Get the latest message from this conversation
            $latestMessage = $conversation->messages()->where('direction', 'incoming')->latest()->first();
            
            // Generate AI response
            $aiResponse = $openaiService->generateResponse($conversation, $latestMessage->message);
            
            // Send response back to WhatsApp
            $whatsappService->sendMessage($conversation->customer_phone, $aiResponse);
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['status' => 'error', 'message' => 'Internal server error'], 500);
        }
    }
}
