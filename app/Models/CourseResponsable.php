<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseResponsable extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'nombre',
        'cargo',
        'signature_path',
        'orden'
    ];

    /**
     * Relación con el curso
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}