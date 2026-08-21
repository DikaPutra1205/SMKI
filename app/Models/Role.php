<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Role extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'label', 'description'];

    public static function permissionsCacheKey(int|string $roleId): string
    {
        return "rbac:grants:{$roleId}";
    }

    public static function flushPermissionsCache(int|string $roleId): void
    {
        Cache::forget(self::permissionsCacheKey($roleId));
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
