<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class KodeBarang extends Model
{
    use LogsActivity;

    protected $table = 'kode_barang';

    protected $guarded = [];
}
