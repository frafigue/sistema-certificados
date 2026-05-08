<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Role;
use App\Models\Person;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'area_id',
        'activo', // ✅ NUEVO
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'activo'            => 'boolean', // ✅ NUEVO
        ];
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function person()
    {
        return $this->hasOne(Person::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    // ✅ NUEVO: verificar si el usuario está activo
    public function estaActivo(): bool
    {
        return $this->activo === true;
    }
}