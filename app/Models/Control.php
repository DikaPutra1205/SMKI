<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Control extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'framework_id',
        'kode_klausul',
        'judul',
        'deskripsi',
        'kategori',
        'domain_peran',
    ];

    public const KATEGORIS = ['organisasional', 'orang', 'fisik', 'teknologi'];

    public const KATEGORI_LABELS = [
        'organisasional' => 'Organisasional',
        'orang' => 'Orang',
        'fisik' => 'Fisik',
        'teknologi' => 'Teknologi',
    ];

    public const PERANS = ['controller', 'processor'];

    public static function kategoriLabel(string $kategori): string
    {
        return self::KATEGORI_LABELS[$kategori] ?? $kategori;
    }

    protected $appends = ['framework_name', 'framework_versi'];

    public function framework(): BelongsTo
    {
        return $this->belongsTo(Framework::class);
    }

    public function checklistEntries(): HasMany
    {
        return $this->hasMany(ChecklistEntry::class, 'control_id');
    }

    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class, 'control_id');
    }

    /**
     * Risiko-risiko yang terkait dengan kontrol ini (many-to-many via pivot control_risk).
     * Satu kontrol dapat digunakan untuk memitigasi lebih dari satu risiko sesuai PRD.
     */
    public function risks(): BelongsToMany
    {
        return $this->belongsToMany(Risk::class, 'control_risk')
            ->withTimestamps();
    }

    public function getFrameworkNameAttribute(): string
    {
        return $this->framework?->nama ?? '';
    }

    public function getFrameworkVersiAttribute(): string
    {
        return $this->framework?->versi ?? '';
    }
}
