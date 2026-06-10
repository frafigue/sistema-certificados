<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Certificate;


class ImportHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'batch_id',
        'file_name',
        'total_rows',
        'processed_rows',
        'success_rows',
        'failed_rows',
        'status',
        'started_at',
        'finished_at',
        'general_error',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isFinished(): bool
    {
        return in_array($this->status, [
            'completed',
            'failed',
        ]);
    }

    public function progress(): int
    {
        if ($this->total_rows <= 0) {
            return 0;
        }

        return (int) round(
            ($this->processed_rows / $this->total_rows) * 100
        );
    }
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}