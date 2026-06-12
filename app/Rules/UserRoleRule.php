<?php

namespace App\Rules;

use App\Models\Role;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UserRoleRule implements ValidationRule
{
    protected Role $currentUserRole;

    public function __construct(Role $currentUserRole)
    {
        $this->currentUserRole = $currentUserRole;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return; // Handled by 'nullable' or 'required' rules.
        }

        $roleToAssign = Role::find((int) $value);

        if (! $roleToAssign) {
            // Should be caught by 'exists:roles,id'
            $fail('The selected :attribute is invalid.');

            return;
        }

        // Superadmin (hierarchy 0) can assign any role.
        if ($this->currentUserRole->isSuperAdmin()) {
            return;
        }

        // Non-superadmins can only assign roles with a hierarchy strictly
        // greater (lower precedence) than their own.
        if ($roleToAssign->hierarchy <= $this->currentUserRole->hierarchy) {
            $fail(
                'The selected :attribute must have a lower hierarchy (higher value) than your own role.',
            );
        }
    }
}
