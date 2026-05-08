<?php

namespace App\Imports\Sheets;

use App\Jobs\GenerateCertificateJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use Throwable;


class CertificatesSheetImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    private int $importedCount = 0;
    private array $errors = [];

    // 🔥 NUEVO
    private ?string $batchId = null;

    public function collection(Collection $rows)
    {
        $jobs = [];

        foreach ($rows as $index => $row) {

            try {
                $data = [
                    'dni'                   => $row['dni'] ?? null,
                    'curso'                 => $row['curso'] ?? null,
                    'nota'                  => $row['nota'] ?? null,
                    'unidad_academica'      => $row['unidad_academica'] ?? null,
                    'area'                  => $row['area'] ?? null,
                    'subarea'               => $row['subarea'] ?? null,
                    'codigo_incremental'    => $row['codigo_incremental'] ?? null,
                    'ano'                   => $row['ano'] ?? null,
                    'tipo_certificado'      => $row['tipo_certificado'] ?? null,
                    'iniciales'             => $row['iniciales'] ?? null,
                    '3_ultimos_del_dni'     => $row['3_ultimos_del_dni'] ?? null,
                    'cuv'                   => $row['cuv'] ?? null,
                ];

                if (empty($data['dni']) || empty($data['curso']) || empty($data['cuv'])) {
                    continue;
                }

                $jobs[] = new GenerateCertificateJob($data);

                $this->importedCount++;

            } catch (Throwable $e) {

                Log::error("Error en fila {$index}: " . $e->getMessage());

                $this->errors[] = [
                    'row' => $index,
                    'error' => $e->getMessage()
                ];
            }
        }

        if (!empty($jobs)) {

            $batch = Bus::batch($jobs)
                ->then(function (Batch $batch) {
                    Log::info("✅ BATCH COMPLETADO: " . $batch->id);
                })
                ->catch(function (Batch $batch, Throwable $e) {
                    Log::error("❌ BATCH ERROR: " . $e->getMessage());
                })
                ->finally(function (Batch $batch) {
                    Log::info("📦 BATCH FINALIZADO");
                })
                ->dispatch();
            Log::info('BATCH ID: ' . $batch->id);
            // ✅ GUARDAR EN LA CLASE (NO EN SESSION)
            $this->batchId = $batch->id;
        }
    }

    // 🔥 NUEVO
    public function getBatchId(): ?string
    {
        return $this->batchId;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}