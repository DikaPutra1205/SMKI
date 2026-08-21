<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Framework extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['nama', 'versi', 'url_file'];

    public function controls(): HasMany
    {
        return $this->hasMany(Control::class, 'framework_id');
    }

    public function checklistSessions(): HasMany
    {
        return $this->hasMany(ChecklistSession::class, 'framework_id');
    }

    public function getUrlFileAttribute(): ?string
    {
        $url = $this->attributes['url_file'] ?? null;

        if (! $url) {
            return null;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        try {
            return Storage::disk('supabase-frameworks')->temporaryUrl($url, now()->addHours(24));
        } catch (\Throwable $e) {
            report($e);

            return Storage::disk('supabase-frameworks')->url($url);
        }
    }

    public function getNamaFileAttribute(): ?string
    {
        $url = $this->attributes['url_file'] ?? null;

        if (! $url) {
            return null;
        }

        return rawurldecode(basename($url));
    }

    protected static function booted(): void
    {
        static::deleting(function (Framework $framework) {
            $framework->controls()->delete();
        });
    }
}
