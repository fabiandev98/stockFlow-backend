<?php

namespace App\Console\Commands;

use App\Enums\DenebPermission;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Console\Command;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deneb:permissions-sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize permissions from DenebPermission enum to database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Synchronizing permissions from DenebPermission enum...');

        $permissionsCases = DenebPermission::cases();
        $createdCount = 0;
        $existingCount = 0;
        $deletedCount = 0;

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Get all permission values from enum
        $enumPermissions = collect($permissionsCases)->map(fn ($case) => $case->value)->all();

        // Create or update permissions from enum
        $newPermissions = [];
        foreach ($permissionsCases as $permissionCase) {
            $permissionValue = $permissionCase->value;

            $permission = Permission::firstOrCreate(
                ['name' => $permissionValue],
            );

            if ($permission->wasRecentlyCreated) {
                $createdCount++;
                $newPermissions[] = $permission;
                $this->line("  ✓ Created: {$permissionValue}");
            } else {
                $existingCount++;
            }
        }

        // Delete permissions that no longer exist in enum
        $orphanedPermissions = Permission::whereNotIn('name', $enumPermissions)->get();
        foreach ($orphanedPermissions as $permission) {
            $this->line("  ✗ Deleted: {$permission->name}");
            $permission->delete();
            $deletedCount++;
        }

        // Assign new permissions to Super Admin role (hierarchy 0)
        if (count($newPermissions) > 0) {
            $superAdminRole = Role::where('hierarchy', Role::SUPERADMIN_HIERARCHY)->first();

            if ($superAdminRole) {
                $superAdminRole->givePermissionTo($newPermissions);
                $this->line("  ✓ Assigned {$createdCount} new permissions to Super Admin role");
            }
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->newLine();
        $this->info('Synchronization complete!');
        $this->line("  Created: {$createdCount}");
        $this->line("  Deleted: {$deletedCount}");
        $this->line("  Already existing: {$existingCount}");
        $this->line('  Total: '.count($permissionsCases));

        return $this::SUCCESS;
    }
}
