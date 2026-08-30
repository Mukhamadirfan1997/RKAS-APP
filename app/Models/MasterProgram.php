<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class MasterProgram extends Model
{
    use LogsActivity;

    protected $table = 'master_program';

    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(RkasItem::class);
    }
}
