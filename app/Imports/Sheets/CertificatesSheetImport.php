<?php

namespace App\Imports\Sheets;

use App\Jobs\GenerateCertificateJob;
use App\Models\ImportHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Throwable;

class CertificatesSheetImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    private $importedCount = 0;

    private $errors = [];

    private $batchId = null;

    public function collection(Collection $rows)
    {
        $jobs = [];

        // =========================================================
        // 🔥 CREAR HISTORIAL IMPORTACIÓN
        // Generar batch_id único ANTES de crear el ImportHistory
        // =========================================================

        $this->batchId = (string) \Illuminate\Support\Str::uuid();
	
        $importHistory = ImportHistory::create([
            'user_id'       => Auth::id(),
            'batch_id'      => $this->batchId,
            'file_name'     => null,
            'total_rows'    => $rows->count(),
            'processed_rows'=> 0,
            'success_rows'  => 0,
            'failed_rows'   => 0,
            'status'        => 'processing',
            'started_at'    => now(),
        ]);

        $importHistoryId = $importHistory->id;

        // =========================================================
        // 🔥 RECORRER FILAS
        // =========================================================

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

                // =========================================================
                // 🔥 VALIDACIÓN
                // =========================================================

                if (
                    empty($data['dni']) ||
                    empty($data['curso']) ||
                    empty($data['cuv'])
                ) {
                    continue;
                }

                // =========================================================
                // 🔥 AGREGAR JOB
                // =========================================================

                $jobs[] = new GenerateCertificateJob(
                    $data,
                    $importHistoryId
                );

                $this->importedCount++;

            } catch (Throwable $e) {

                Log::error("Error en fila {$index}: " . $e->getMessage());

                $this->errors[] = [
                    'row'   => $index,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // =========================================================
        // 🔥 ENVIAR BATCH
        // =========================================================

        if (!empty($jobs)) {

            $batch = Bus::batch($jobs)

                ->then(function (Batch $batch) use ($importHistoryId) {

                    ImportHistory::where('id', $importHistoryId)
                        ->update([

                            'status'          => 'completed',

                            'processed_rows'  => $batch->processedJobs(),

                            'success_rows'    => $batch->processedJobs(),

                            'failed_rows'     => $batch->failedJobs,

                            'finished_at'     => now(),
                        ]);

                    Log::info("✅ BATCH COMPLETADO: " . $batch->id);
                })

                ->catch(function (Batch $batch, Throwable $e) use ($importHistoryId) {

                    ImportHistory::where('id', $importHistoryId)
                        ->update([

                            'status'        => 'failed',

                            'general_error' => $e->getMessage(),

                            'failed_rows'   => $batch->failedJobs,

                            'finished_at'   => now(),
                        ]);

                    Log::error("❌ BATCH ERROR: " . $e->getMessage());
                })

                ->finally(function (Batch $batch) use ($importHistoryId) {

                    ImportHistory::where('id', $importHistoryId)
                        ->update([

                            'processed_rows' => $batch->processedJobs(),

                            'failed_rows'    => $batch->failedJobs,
                        ]);

                    Log::info("📦 BATCH FINALIZADO");
                })

                ->dispatch();

            // =========================================================
            // 🔥 GUARDAR BATCH ID
            // =========================================================

            Log::info('BATCH ID: ' . $batch->id);

            $this->batchId = $batch->id;
            
        }
    }

    // =========================================================
    // 🔥 GETTERS
    // =========================================================

    public function getBatchId()
    {
        return $this->batchId;
    }

    public function getImportedCount()
    {
        return $this->importedCount;
    }

    public function getErrors()
    {
        return $this->errors;
    }
}
