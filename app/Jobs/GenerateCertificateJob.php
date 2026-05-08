<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\Person;
use App\Services\CertificateService;
use App\Jobs\SendCertificateEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;

class GenerateCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels,  Batchable;

    public $row;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct($row)
    {
        $this->row = $row;
        $this->onQueue('certificates');
    }

    public function handle(CertificateService $service)
    {
        try {
            // 🔍 Validación básica
            if (empty($this->row['dni']) || empty($this->row['curso'])) {
                Log::warning('Fila inválida (faltan datos)', $this->row);
                return;
            }

            // 🔍 Buscar datos
            $course = Course::where('nombre', $this->row['curso'])->first();
            $person = Person::where('dni', $this->row['dni'])->first();

            if (!$course || !$person) {
                Log::warning('Course o Person no encontrados', [
                    'curso' => $this->row['curso'],
                    'dni'   => $this->row['dni']
                ]);
                return;
            }

            // 🔥 GENERAR CERTIFICADO (CORE)
            $certificate = $service->generate([
                'course_id'         => $course->id,
                'person_id'         => $person->id,
                'tipo_certificado'  => $this->row['tipo_certificado'],
                'cuv'               => $this->row['cuv'],
                'nota'              => $this->row['nota'] ?? null,
                'unidad_academica'  => $this->row['unidad_academica'] ?? null,
                'area'              => $this->row['area'] ?? null,
                'subarea'           => $this->row['subarea'] ?? null,
                'codigo_incremental'=> $this->row['codigo_incremental'] ?? null,
                'ano'               => $this->row['ano'] ?? date('Y'),
                'iniciales'         => $this->row['iniciales'] ?? null,
                '3_ultimos_del_dni' => $this->row['3_ultimos_del_dni'] ?? null,
            ]);
        // 🔥 ENCOLAR EMAIL
        if (
            $certificate->person &&
            $certificate->person->email &&
            !str_ends_with($certificate->person->email, '@tmp.com')
        ) {
            SendCertificateEmail::dispatch($certificate)
                ->onQueue('emails');
        }
        } catch (\Throwable $e) {
            Log::error('Error en GenerateCertificateJob: ' . $e->getMessage(), [
                'row' => $this->row
            ]);
        }
    }
}