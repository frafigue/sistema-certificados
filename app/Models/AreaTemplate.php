<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AreaTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'area_id',
        'subarea_id',
        'template_id',
        'custom_design',
        'created_by',
    ];

    protected $casts = [
        'custom_design' => 'array',
    ];

    /**
     * El área a la que pertenece esta vinculación
     */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * La subárea (nullable: si es null, es del área)
     */
    public function subarea()
    {
        return $this->belongsTo(Subarea::class);
    }

    /**
     * La plantilla base del repositorio
     */
    public function template()
    {
        return $this->belongsTo(CertificateTemplate::class, 'template_id');
    }

    /**
     * Quién creó esta vinculación
     */
    public function creador()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}