<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PengaturanSekolah extends Model
{
    use LogsActivity;

    protected $table = 'pengaturan_sekolah';

    protected $guarded = [];
}
