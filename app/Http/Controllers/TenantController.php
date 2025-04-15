<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class TenantController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withCount(['users', 'bots'])
            ->latest()
            ->paginate(10);

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Tenants/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants',
            'database' => 'required|string|max:255|unique:tenants',
            'company_name' => 'required|string|max:255',
            'company_email' => 'required|email|max:255',
            'company_phone' => 'nullable|string|max:20',
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255|unique:users,email',
            'admin_password' => 'required|string|min:8',
            'plan_id' => 'required|integer|exists:plans,id',
        ]);

        DB::beginTransaction();

        try {
            // Create tenant
            $tenant = Tenant::create([
                'name' => $validated['name'],
                'domain' => $validated['domain'],
                'database' => $validated['database'],
                'company_name' => $validated['company_name'],
                'company_email' => $validated['company_email'],
                'company_phone' => $validated['company_phone'] ?? null,
                'plan_id' => $validated['plan_id'],
                'subscription_status' => 'active',
                'subscription_ends_at' => now()->addMonth(),
            ]);

            // Make the tenant current to create tables
            $tenant->makeCurrent();

            // Run migrations for this tenant
            $this->runTenantMigrations($tenant);

            // Create admin user for this tenant
            $user = User::create([
                'name' => $validated['admin_name'],
                'email' => $validated['admin_email'],
                'password' => Hash::make($validated['admin_password']),
                'tenant_id' => $tenant->id,
            ]);

            // Assign admin role
            $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $user->assignRole($adminRole);

            // Forget current tenant
            $tenant->forgetCurrent();

            DB::commit();

            return redirect()->route('admin.tenants.index')
                ->with('success', 'Tenant created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error creating tenant: ' . $e->getMessage());
        }
    }

    public function show(Tenant $tenant)
    {
        $tenant->load(['users', 'bots']);
        
        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant
        ]);
    }

    public function edit(Tenant $tenant)
    {
        return Inertia::render('Admin/Tenants/Edit', [
            'tenant' => $tenant
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_name' => 'required|string|max:255',
            'company_email' => 'required|email|max:255',
            'company_phone' => 'nullable|string|max:20',
            'subscription_status' => 'required|string|in:active,inactive,pending',
            'subscription_ends_at' => 'nullable|date',
            'plan_id' => 'required|integer|exists:plans,id',
        ]);

        $tenant->update($validated);

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant updated successfully.');
    }

    public function destroy(Tenant $tenant)
    {
        // This is a critical operation and should include cleanup
        // Consider soft deletes or scheduled deletion
        $tenant->delete();

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant deleted successfully.');
    }

    private function runTenantMigrations(Tenant $tenant)
    {
        $migrator = app('migrator');
        $migrator->path(database_path('migrations/tenant'));
        $migrator->run();
    }
}
