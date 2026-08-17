<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Framework extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['nama', 'versi', 'url_file'];

    public function controls(): HasMany
    {
        return $this->hasMany(Control::class, 'framework_id');
    }

    protected static function booted(): void
    {
        static::deleting(function (Framework $framework) {
            $framework->controls()->delete();
        });
    }
}
