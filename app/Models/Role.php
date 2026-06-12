<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @property int $hierarchy
 * @property string|null $display_name
 * @property string|null $description
 */
class Role extends SpatieRole
{
    public const MAX_ROLE_HIERARCHY = 5000;

    public const SUPERADMIN_HIERARCHY = 0;

    protected $guarded = [];

    protected $casts = [
        'hierarchy' => 'integer',
    ];

    /**
     * Summary of getAllDenebPermissions
     *
     * @return Collection<int, Permission>
     */
    public static function getAllDenebPermissions(): Collection
    {
        return Cache::remember('superadmin_permissions', 3600, function () {
            $superAdmin = self::where('hierarchy', self::SUPERADMIN_HIERARCHY)->firstOrFail();

            return $superAdmin->permissions;
        });
    }

    /**
     * @return array<string>
     */
    public static function getAllDenebPermissionsNames(): array
    {
        return Cache::remember('superadmin_permission_names', 3600, function () {
            return self::getAllDenebPermissions()->pluck('name')->toArray();
        });
    }

    public static function getMaxHierarchy(): int
    {
        return (int) (self::max('hierarchy') ?? self::SUPERADMIN_HIERARCHY);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hierarchy === self::SUPERADMIN_HIERARCHY;
    }

    /**
     * Assign the role hierarchy, properly adjusting other roles
     */
    public function assignHierarchy(int $newHierarchy, bool $isNew = false): void
    {
        // Is important that the validation is outside of the transaction, to prevent the savepoint
        $this->validateHierarchy($newHierarchy);

        DB::transaction(function () use ($newHierarchy, $isNew) {
            $currentMaxHierarchy = self::getMaxHierarchy();
            // Set the new hierarchy as exactly the max current hierarchy if it is bigger than the actual.
            $newHierarchy = $newHierarchy > $currentMaxHierarchy ? $currentMaxHierarchy : $newHierarchy;

            if ($isNew) {
                $this->handleNewRoleHierarchy($newHierarchy);
            } else {
                $this->handleExistingRoleHierarchy($newHierarchy);
            }

            $this->hierarchy = $newHierarchy;
            $this->saveQuietly();

            // Clear cache when hierarchy changes
            Cache::forget('superadmin_permissions');
            Cache::forget('superadmin_permission_names');
        });
    }

    private function validateHierarchy(int $hierarchy): void
    {
        if ($hierarchy < 1 || $hierarchy > self::MAX_ROLE_HIERARCHY) {
            throw new HttpException(
                Response::HTTP_BAD_REQUEST,
                'Hierarchy must be between 1 and '.self::MAX_ROLE_HIERARCHY
            );
        }
    }

    private function handleNewRoleHierarchy(int $newHierarchy): void
    {
        // Shift all roles with hierarchy >= newHierarchy up by 1
        self::where('hierarchy', '>=', $newHierarchy)
            ->orderBy('hierarchy', 'desc') // The sort is important to prevent unique index collisions
            ->increment('hierarchy');
    }

    private function handleExistingRoleHierarchy(int $newHierarchy): void
    {
        $currentHierarchy = $this->hierarchy;

        if ($newHierarchy === $currentHierarchy) {
            return; // No change needed
        }

        if ($newHierarchy < $currentHierarchy) {
            // Moving to higher priority (lower number)
            // Shift roles in range [newHierarchy, currentHierarchy] up by 1
            self::whereBetween('hierarchy', [$newHierarchy, $currentHierarchy])
                ->orderBy('hierarchy', 'desc')
                ->increment('hierarchy');
        } else {
            // Makes room (due to the unique index) for the next hierarchy updates
            $this->hierarchy = self::MAX_ROLE_HIERARCHY + 10;
            $this->saveQuietly();
            // Moving to lower priority (higher number)
            // Shift roles in range (currentHierarchy, newHierarchy] down by 1
            self::where('id', '!=', $this->id)
                ->whereBetween('hierarchy', [$currentHierarchy + 1, $newHierarchy])
                ->orderBy('hierarchy', 'asc')
                ->decrement('hierarchy');
        }
    }

    protected static function boot(): void
    {
        parent::boot();

        static::deleted(function (self $role) {
            // When a role is deleted, shift down all roles with higher hierarchy
            self::where('hierarchy', '>', $role->hierarchy)
                ->decrement('hierarchy');

            Cache::forget('superadmin_permissions');
            Cache::forget('superadmin_permission_names');
        });
    }
}
