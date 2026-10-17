<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'paper_size',
        'orientation',
        'width_cm',
        'height_cm',
        'background_image',
        'components',
        'is_public',
        'created_by',
    ];

    protected $casts = [
        'components' => 'array',
        'is_public' => 'boolean',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
    ];

    /**
     * Quién creó esta plantilla
     */
    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Vinculaciones de esta plantilla con áreas/subáreas
     */
    public function areaTemplates()
    {
        return $this->hasMany(AreaTemplate::class, 'template_id');
    }

    /**
     * Cursos que usan esta plantilla
     */
    public function courses()
    {
        return $this->hasMany(Course::class, 'template_id');
    }

    /**
     * Certificados que usan esta plantilla
     */
    public function certificates()
    {
        return $this->hasMany(Certificate::class, 'template_id');
    }
}