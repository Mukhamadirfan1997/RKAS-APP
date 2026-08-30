<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RkasItemBulan extends Model
{
    protected $table = 'rkas_item_bulan';

    protected $guarded = [];

    public function item()
    {
        return $this->belongsTo(RkasItem::class, 'rkas_item_id');
    }
}
