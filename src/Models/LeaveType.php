<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    protected $fillable = ['name', 'days_allowed_per_year'];

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }
}
