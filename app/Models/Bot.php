<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bot extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'whatsapp_number',
        'whatsapp_id',
        'ai_model',
        'is_active',
        'welcome_message',
        'farewell_message',
        'business_hours_only',
        'business_hours',
        'max_context_messages',
        'system_prompt',
        'api_credentials',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'business_hours_only' => 'boolean',
        'business_hours' => 'array',
        'api_credentials' => 'encrypted:array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function flows(): HasMany
    {
        return $this->hasMany(Flow::class);
    }
}
