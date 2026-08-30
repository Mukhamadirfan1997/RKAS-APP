<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class JenisBelanja extends Model
{
    use LogsActivity;

    protected $table = 'jenis_belanja';

    protected $guarded = [];

    public function rekenings()
    {
        return $this->hasMany(MasterKodeRekening::class, 'jenis_belanja_id');
    }
}
