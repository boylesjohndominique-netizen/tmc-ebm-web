<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $fillable = ['title','message','type','active'];
    protected $casts = ['active' => 'boolean'];
}
