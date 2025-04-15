<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BotController extends Controller
{
    public function index()
    {
        $bots = Bot::where('tenant_id', Auth::user()->tenant_id)
            ->withCount('conversations')
            ->latest()
            ->paginate(10);

        return Inertia::render('Bots/Index', [
            'bots' => $bots
        ]);
    }

    public function create()
    {
        return Inertia::render('Bots/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'whatsapp_number' => 'required|string|max:20',
            'whatsapp_id' => 'required|string|max:255',
            'ai_model' => 'required|string|max:50',
            'welcome_message' => 'nullable|string',
            'farewell_message' => 'nullable|string',
            'business_hours_only' => 'boolean',
            'business_hours' => 'nullable|array',
            'max_context_messages' => 'integer|min:1|max:50',
            'system_prompt' => 'required|string',
            'api_credentials' => 'required|array',
            'api_credentials.token' => 'required|string',
        ]);

        $validated['tenant_id'] = Auth::user()->tenant_id;
        $validated['is_active'] = true;

        $bot = Bot::create($validated);

        return redirect()->route('bots.show', $bot)
            ->with('success', 'Bot created successfully.');
    }

    public function show(Bot $bot)
    {
        $this->authorize('view', $bot);

        $bot->load('conversations');
        
        return Inertia::render('Bots/Show', [
            'bot' => $bot,
            'stats' => [
                'total_conversations' => $bot->conversations()->count(),
                'active_conversations' => $bot->conversations()->where('status', 'active')->count(),
                'total_messages' => $bot->conversations()->withCount('messages')->sum('messages_count'),
                'webhook_url' => url("/api/webhook/{$bot->id}"),
            ]
        ]);
    }

    public function edit(Bot $bot)
    {
        $this->authorize('update', $bot);
        
        return Inertia::render('Bots/Edit', [
            'bot' => $bot
        ]);
    }

    public function update(Request $request, Bot $bot)
    {
        $this->authorize('update', $bot);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'whatsapp_number' => 'required|string|max:20',
            'whatsapp_id' => 'required|string|max:255',
            'ai_model' => 'required|string|max:50',
            'welcome_message' => 'nullable|string',
            'farewell_message' => 'nullable|string',
            'business_hours_only' => 'boolean',
            'business_hours' => 'nullable|array',
            'max_context_messages' => 'integer|min:1|max:50',
            'system_prompt' => 'required|string',
            'api_credentials' => 'sometimes|array',
            'api_credentials.token' => 'sometimes|required|string',
        ]);

        $bot->update($validated);

        return redirect()->route('bots.show', $bot)
            ->with('success', 'Bot updated successfully.');
    }

    public function destroy(Bot $bot)
    {
        $this->authorize('delete', $bot);
        
        $bot->delete();

        return redirect()->route('bots.index')
            ->with('success', 'Bot deleted successfully.');
    }

    public function activate(Bot $bot)
    {
        $this->authorize('update', $bot);
        
        $bot->update(['is_active' => true]);

        return back()->with('success', 'Bot activated successfully.');
    }

    public function deactivate(Bot $bot)
    {
        $this->authorize('update', $bot);
        
        $bot->update(['is_active' => false]);

        return back()->with('success', 'Bot deactivated successfully.');
    }
}
