<?php

namespace App\Console\Commands;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class InitProject extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deneb:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize Deneb project with permissions and Super Admin role';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Initializing Deneb project...');
        $this->newLine();

        // Step 1: Sync permissions
        $this->info('Step 1: Synchronizing permissions...');
        Artisan::call('deneb:permissions-sync');
        $this->line(Artisan::output());

        // Step 2: Create Super Admin role
        $this->info('Step 2: Creating Super Admin role...');

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin'],
            [
                'hierarchy' => Role::SUPERADMIN_HIERARCHY,
                'display_name' => 'Super Admin',
                'description' => 'Has full access to all system features',
            ]
        );

        if ($superAdminRole->wasRecentlyCreated) {
            $this->line('  ✓ Created Super Admin role');
        } else {
            $this->line('  ✓ Super Admin role already exists');
        }

        // Step 3: Assign all permissions to Super Admin
        $this->info('Step 3: Assigning all permissions to Super Admin...');

        $allPermissions = Permission::all();
        $superAdminRole->syncPermissions($allPermissions);

        $this->line("  ✓ Assigned {$allPermissions->count()} permissions to Super Admin");

        // Step 4: Create Super Admin user
        $this->info('Step 4: Creating Super Admin user...');

        $email = $this->ask('Enter Super Admin email', 'contacto@nuwebs.com.co');
        $password = $this->secret('Enter Super Admin password (min 8 characters)');

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters long.');

            return $this::FAILURE;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Super Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );

        if ($user->wasRecentlyCreated) {
            $this->line("  ✓ Created user: {$email}");
        } else {
            $this->line("  ✓ User already exists: {$email}");
        }

        // Assign Super Admin role to user
        if (! $user->hasRole('Super Admin')) {
            $user->assignRole($superAdminRole);
            $this->line('  ✓ Assigned Super Admin role to user');
        } else {
            $this->line('  ✓ User already has Super Admin role');
        }

        $this->newLine();
        $this->info('✨ Deneb initialization complete!');
        $this->line("  - Permissions synchronized: {$allPermissions->count()}");
        $this->line('  - Super Admin role created with all permissions');
        $this->line("  - Super Admin user created: {$email}");

        return $this::SUCCESS;
    }
}
