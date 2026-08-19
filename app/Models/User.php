<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'role_id', 'unit_id'];

    protected $hidden = ['password', 'remember_token'];

    // Konstanta role agar tidak typo di controller
    const ROLE_SUPERADMIN = 'superadmin';

    const ROLE_ADMIN_KEPATUHAN = 'admin_kepatuhan';

    const ROLE_KOORDINATOR_SMKI = 'koordinator_smki';

    const ROLE_AUDITOR = 'auditor';

    const ROLE_PIC = 'pic';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Backward-compat: JSON responses keep the legacy `role` string.
    protected $appends = ['role'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * Legacy read-compat: `$user->role` returns the role name string.
     */
    public function getRoleAttribute(): ?string
    {
        return $this->relationLoaded('role') ? $this->getRelation('role')?->name : $this->role()->value('name');
    }

    /**
     * Legacy write-compat: `create(['role' => 'pic'])` keeps working.
     * New code should assign `role_id` directly instead.
     */
    public function setRoleAttribute(string $name): void
    {
        $this->attributes['role_id'] = Role::where('name', $name)->firstOrFail()->id;
    }

    public function hasPermissionTo(string $key): bool
    {
        return in_array($key, $this->cachedPermissionKeys(), true);
    }

    public function cachedPermissionKeys(): array
    {
        if ($this->role_id === null) {
            return [];
        }

        return once(fn () => Cache::remember(Role::permissionsCacheKey($this->role_id), now()->addHour(), fn () => $this->role()->first()?->permissions()->pluck('permissions.key')->all() ?? []));
    }

    // The eager-loaded `role` relation may not shadow the appended `role` string in JSON.
    protected function getArrayableRelations()
    {
        $relations = parent::getArrayableRelations();
        unset($relations['role']);

        return $relations;
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class, 'unit_id');
    }

    public function workUnit(): BelongsTo
    {
        return $this->belongsTo(WorkUnit::class, 'unit_id');
    }

    public function checklistEntriesAsPic(): HasMany
    {
        return $this->hasMany(ChecklistEntry::class, 'pic_id');
    }

    public function checklistEntriesAsAdmin(): HasMany
    {
        return $this->hasMany(ChecklistEntry::class, 'admin_id');
    }

    public function createdChecklistSessions(): HasMany
    {
        return $this->hasMany(ChecklistSession::class, 'created_by');
    }

    public function updatedChecklistSessions(): HasMany
    {
        return $this->hasMany(ChecklistSession::class, 'updated_by');
    }

    public function uploadedEvidences(): HasMany
    {
        return $this->hasMany(ComplianceEvidence::class, 'uploaded_by');
    }

    public function findingsAsPic(): HasMany
    {
        return $this->hasMany(Finding::class, 'pic_id');
    }

    public function findingsAsAdmin(): HasMany
    {
        return $this->hasMany(Finding::class, 'admin_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    // Helper: cek role
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN_KEPATUHAN;
    }

    public function isPic(): bool
    {
        return $this->role === self::ROLE_PIC;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isAuditor(): bool
    {
        return $this->role === self::ROLE_AUDITOR;
    }

    public function isKoordinator(): bool
    {
        return $this->role === self::ROLE_KOORDINATOR_SMKI;
    }
}
