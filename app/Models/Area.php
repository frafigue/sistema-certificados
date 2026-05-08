<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Course;

class Area extends Model
{
    protected $fillable = ['nombre', 'descripcion', 'template_front', 'template_back'];

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function administrators()
    {
        return $this->hasMany(User::class)->whereHas('role', function ($query) {
            $query->where('name', 'admin');
        });
    }

    // ✅ Todas las personas via tabla pivot
    public function persons()
    {
        return $this->belongsToMany(\App\Models\Person::class, 'area_person');
    }
}