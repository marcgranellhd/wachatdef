<?php

use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Actions\MigrateTenantAction;
use Spatie\Multitenancy\Models\Tenant;

return [
    /*
     * This class is responsible for determining which tenant should be current
     * for the given request.
     */
    'tenant_finder' => \App\Multitenancy\TenantFinder::class,

    /*
     * These tasks will be performed when switching tenants.
     */
    'switch_tenant_tasks' => [
        \Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask::class,
        \Spatie\Multitenancy\Tasks\PrefixCacheTask::class,
    ],

    /*
     * This class is the model used for storing configuration on tenants.
     */
    'tenant_model' => \App\Models\Tenant::class,

    /*
     * If there is a current tenant when dispatching a job, the id of the current tenant
     * will be automatically set on the job. When the job is executed, the set
     * tenant on the job will be made current.
     */
    'queues_are_tenant_aware_by_default' => true,

    /*
     * The connection name to reach the tenant database.
     */
    'tenant_database_connection_name' => 'tenant',

    /*
     * The connection name to reach the landlord database
     */
    'landlord_database_connection_name' => 'landlord',

    /*
     * This key will be used to bind the current tenant in the container.
     */
    'current_tenant_container_key' => 'currentTenant',

    /*
     * These job classes will not be multitenancy aware.
     */
    'jobs_exempt_from_multi_tenancy' => [
        BroadcastEvent::class,
        CallQueuedListener::class,
        SendQueuedMailable::class,
        SendQueuedNotifications::class,
    ],
];
