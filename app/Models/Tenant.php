<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use HasFactory;
    use UsesTenantConnection;

    protected $fillable = [
        'name',
        'domain',
        'database',
        'plan_id',
        'subscription_status',
        'subscription_ends_at',
        'company_name',
        'company_email',
        'company_phone',
        'company_address',
        'logo',
    ];

    protected $casts = [
        'subscription_ends_at' => 'datetime',
    ];

    public function bots(): HasMany
    {
        return $this->hasMany(Bot::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
