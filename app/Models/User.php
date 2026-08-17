<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'unit_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    public function unit(): BelongsTo
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
