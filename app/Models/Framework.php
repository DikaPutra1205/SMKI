<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Framework extends Model
{
    use SoftDeletes;

    protected $fillable = ['nama', 'versi', 'url_file'];

    public function controls(): HasMany
    {
        return $this->hasMany(Control::class, 'framework_id');
    }
}
