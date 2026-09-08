<?php

namespace Easybdit\LaravelEasyAttendance\Tests\Fixtures;

use Easybdit\LaravelEasyAttendance\Traits\HasAttendance;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Stand-in "subject" model for the test suite — plays the role a
 * consuming app's own User/Employee model would play.
 */
class User extends Authenticatable
{
    use HasAttendance, Notifiable;

    protected $table = 'users';

    protected $guarded = [];
}
