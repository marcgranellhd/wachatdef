<?php

namespace App\Multitenancy;

use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder as BaseTenantFinder;

class TenantFinder extends BaseTenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        // First try to find by domain
        $host = $request->getHost();
        $tenant = Tenant::where('domain', $host)->first();

        if ($tenant) {
            return $tenant;
        }

        // Para incluir el soporte de subdominios, añadimos esta lógica
        $parts = explode('.', $host);
        if (count($parts) > 2) {
            $subdomain = $parts[0];
            $domain = implode('.', array_slice($parts, 1));
            
            $tenant = Tenant::where('domain', $domain)
                            ->where('subdomain', $subdomain)
                            ->first();
            
            if ($tenant) {
                return $tenant;
            }
        }

        // Second, for web routes, check if user is authenticated and has tenant_id
        if ($request->user() && $request->user()->tenant_id) {
            return Tenant::find($request->user()->tenant_id);
        }
        
        // For API routes, check if tenant_id is in header
        $tenantHeader = $request->header('X-Tenant-ID');
        if ($tenantHeader) {
            return Tenant::find($tenantHeader);
        }

        // For webhook routes, extract tenant from route parameter
        $route = $request->route();
        if ($route && $route->hasParameter('botId')) {
            $botId = $route->parameter('botId');
            $bot = \App\Models\Bot::find($botId);
            
            if ($bot) {
                return Tenant::find($bot->tenant_id);
            }
        }

        return null;
    }
}
