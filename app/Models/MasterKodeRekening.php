<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class MasterKodeRekening extends Model
{
    use LogsActivity;

    protected $table = 'master_kode_rekening';

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(RkasItem::class);
    }
}
