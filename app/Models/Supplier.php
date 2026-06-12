<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'contact_name',
        'phone',
        'email',
    ];

    /**
     * @return HasMany<Purchase>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }
}
