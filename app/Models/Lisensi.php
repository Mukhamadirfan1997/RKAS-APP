<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lisensi extends Model
{
    protected $table = 'lisensi';
    protected $guarded = [];
    protected $casts = [
        'installed_at' => 'datetime',
    ];

    public function aktivasi(): HasMany
    {
        return $this->hasMany(LisensiAktivasi::class, 'lisensi_id');
    }
}
