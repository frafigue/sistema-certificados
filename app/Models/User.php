<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
            'password' => 'hashed',
            'activo' => 'boolean', // ✅ NUEVO
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

    /*
    |--------------------------------------------------------------------------
    | ALCANCE POR ROL
    |--------------------------------------------------------------------------
    | Reglas centralizadas para saber qué registros puede gestionar cada rol.
    | Root: todo. Administrador: su área. Gestor: su subárea. Persona: lo suyo.
    */

    public function esRoot(): bool
    {
        return $this->role?->name === 'Root';
    }

    public function esAdministrador(): bool
    {
        return $this->role?->name === 'Administrador';
    }

    public function esGestor(): bool
    {
        return $this->role?->name === 'Gestor';
    }

    public function esPersona(): bool
    {
        return $this->role?->name === 'Persona';
    }

    public function puedeGestionarArea(?int $areaId): bool
    {
        if ($this->esRoot()) {
            return true;
        }

        if ($this->esAdministrador() && $this->area_id) {
            return $areaId && (int) $areaId === (int) $this->area_id;
        }

        if ($this->esGestor() && $this->subarea) {
            return $areaId && (int) $areaId === (int) $this->subarea->area_id;
        }

        return false;
    }

    public function puedeGestionarPersona(Person $person): bool
    {
        if ($this->esRoot()) {
            return true;
        }

        if ($this->esAdministrador() && $this->area_id) {
            // El pivote area_person es la fuente principal. Si el registro
            // todavía no tiene fila (datos viejos o importaciones), caemos
            // a la columna persons.area_id para no dejarlo inaccesible.
            if ($person->areas()->where('areas.id', $this->area_id)->exists()) {
                return true;
            }

            return $person->area_id && (int) $person->area_id === (int) $this->area_id;
        }

        if ($this->esGestor() && $this->subarea_id) {
            return $person->subarea_id && (int) $person->subarea_id === (int) $this->subarea_id;
        }

        return false;
    }

    public function puedeGestionarCurso(Course $course): bool
    {
        if ($this->esRoot()) {
            return true;
        }

        if ($this->esAdministrador() && $this->area_id) {
            return (int) $course->area_id === (int) $this->area_id;
        }

        if ($this->esGestor() && $this->subarea_id) {
            return $course->subarea_id && (int) $course->subarea_id === (int) $this->subarea_id;
        }

        return false;
    }

    public function puedeGestionarCertificado(Certificate $certificate): bool
    {
        if ($this->esRoot()) {
            return true;
        }

        if ($this->esAdministrador() && $this->area_id) {
            return $certificate->course && (int) $certificate->course->area_id === (int) $this->area_id;
        }

        if ($this->esGestor() && $this->subarea_id) {
            return $certificate->course
                && $certificate->course->subarea_id
                && (int) $certificate->course->subarea_id === (int) $this->subarea_id;
        }

        if ($this->esPersona() && $this->person) {
            return (int) $certificate->person_id === (int) $this->person->id;
        }

        return false;
    }

    public function puedeVerImportacion(ImportHistory $importHistory): bool
    {
        if ($this->esRoot()) {
            return true;
        }

        if ($this->esAdministrador() && $this->area_id) {
            return $importHistory->user
                && (int) $importHistory->user->area_id === (int) $this->area_id;
        }

        if ($this->esGestor()) {
            return (int) $importHistory->user_id === (int) $this->id;
        }

        return false;
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
