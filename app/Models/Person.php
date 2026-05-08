<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Certificate;

class Person extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'persons';

    protected $fillable = [
        'user_id',
        'dni',
        'apellido',
        'nombre',
        'titulo',
        'domicilio',
        'telefono',
        'email',
        'area_id',
    ];

    public function getFullNameAttribute()
    {
        return "{$this->nombre} {$this->apellido}";
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    // Área principal (compatibilidad)
    public function area()
    {
        return $this->belongsTo(\App\Models\Area::class);
    }

    // ✅ Todas las áreas via tabla pivot
    public function areas()
    {
        return $this->belongsToMany(\App\Models\Area::class, 'area_person');
    }
}