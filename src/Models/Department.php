<?php

namespace Easybdit\LaravelEasyAttendance\Models;

use Easybdit\LaravelEasyAttendance\Models\Concerns\HasPackageTable;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasPackageTable;

    protected function tableConfigKey(): string
    {
        return 'departments';
    }

    protected $fillable = ['name', 'description'];

    public function designations()
    {
        return $this->hasMany(Designation::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
