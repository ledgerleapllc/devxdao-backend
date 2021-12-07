<?php

namespace App;

use Laravel\Passport\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'twoFA_login_code',
        'profile',
        'shuftipro',
        'shuftiproTemp',
        'accessToken',
        'confirmation_code',
        'last_login_ip_address',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = ['accessToken'];

    public function getAccessTokenAttribute() {
        return session('accessToken');
    }

    public function profile() {
        return $this->hasOne('App\Profile', 'user_id');
    }

    public function shuftipro() {
        return $this->hasOne('App\Shuftipro', 'user_id');
    }

    public function shuftiproTemp() {
        return $this->hasOne('App\ShuftiproTemp', 'user_id');
    }

    public function ipHistories() {
        return $this->hasMany('App\Models\IpHistory', 'user_id');
    }
    public function permissions() {
        return $this->hasMany('Spatie\Permission\Models\Permission', 'user_id');
    }
}
