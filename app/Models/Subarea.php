<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subarea extends Model
{
    use HasFactory;

    protected $fillable = [
        'area_id',
        'nombre',
        'descripcion',
        'gestor_id',
        'estado',
        'created_by',
    ];

    /**
     * El área a la que pertenece esta subárea
     */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * El gestor asignado a esta subárea (nullable)
     */
    public function gestor()
    {
        return $this->belongsTo(User::class, 'gestor_id');
    }

    /**
     * Quién creó esta subárea
     */
    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Cursos de esta subárea
     */
    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    /**
     * Personas de esta subárea
     */
    public function persons()
    {
        return $this->hasMany(Person::class);
    }

    /**
     * Certificados de esta subárea
     */
    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * Plantillas vinculadas a esta subárea
     */
    public function areaTemplates()
    {
        return $this->hasMany(AreaTemplate::class);
    }

    /**
     * Usuarios (gestores) asignados a esta subárea
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}