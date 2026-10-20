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
        'subarea_id',   // ← AGREGAR
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
        /**
     * Subárea asignada (para gestores)
     */
    public function subarea()
    {
        return $this->belongsTo(Subarea::class);
    }

    /**
     * Subáreas que este usuario gestiona
     */
    public function subareasGestionadas()
    {
        return $this->hasMany(Subarea::class, 'gestor_id');
    }

    /**
     * Subáreas que este usuario creó
     */
    public function subareasCreadas()
    {
        return $this->hasMany(Subarea::class, 'created_by');
    }
    /**
     * Plantillas creadas por este usuario
     */
    public function templates()
    {
        return $this->hasMany(CertificateTemplate::class, 'created_by');
    }
}