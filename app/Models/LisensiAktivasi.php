<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LisensiAktivasi extends Model
{
    protected $table = 'lisensi_aktivasi';

    protected $guarded = [];

    protected $casts = [
        'activated_at' => 'datetime',
    ];

    public function lisensi(): BelongsTo
    {
        return $this->belongsTo(Lisensi::class, 'lisensi_id');
    }
}
