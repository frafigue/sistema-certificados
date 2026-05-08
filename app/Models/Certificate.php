<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'person_id',
        'condition', // 'Tipo de certificado'
        'nota',
        'codigo_incremental',
        'anio',
        'tipo_certificado',
        'iniciales',
        'tres_ultimos_digitos_dni',
        'unique_code', // nuestro 'CUV'
        'qr_path',
        'pdf_path',
        'unidad_academica', 
        'area_excel',  
        'subarea',
        'email_status',
        'email_sent_at',
        'email_error',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    // Scope para filtrar por área del usuario
    public function scopeForUserArea($query, $user)
    {
        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            return $query->whereHas('course', function($q) use ($user) {
                $q->where('area_id', $user->area_id);
            });
        }
        
        return $query;
    }

    // Scope para certificados de una persona específica
    public function scopeForPerson($query, $personId)
    {
        return $query->where('person_id', $personId);
    }

    // Accessor para obtener el nombre completo del área
    public function getAreaNameAttribute()
    {
        return $this->course->area->nombre ?? 'Sin Área';
    }

    // Accessor para verificar si el PDF existe
    public function getPdfExistsAttribute()
    {
        return $this->pdf_path && file_exists(storage_path('app/public/' . $this->pdf_path));
    }

    // Método para verificar si un usuario puede acceder a este certificado
    public function canBeAccessedBy($user)
    {
        // Si es administrador, debe ser de su área
        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            return $this->course->area_id == $user->area_id;
        }
        
        // Si es persona, debe ser su propio certificado
        if ($user->person) {
            return $this->person_id == $user->person->id;
        }
        
        return false;
    }
}