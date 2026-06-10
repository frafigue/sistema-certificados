<?php

namespace App\Http\Controllers;

use App\Imports\Sheets\CertificatesSheetImport;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;

class CertificateExcelGeneratorController extends Controller
{
    public function batchStatus($id)
    {
        $batch = Bus::findBatch($id);
        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Batch no encontrado'
            ], 404);
        }
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $batch->id,
                'name' => $batch->name,
                'total_jobs' => $batch->totalJobs,
                'pending_jobs' => $batch->pendingJobs,
                'processed_jobs' => $batch->processedJobs(),
                'failed_jobs' => $batch->failedJobs,
                'progress' => $batch->progress(),
                'finished' => $batch->finished(),
                'cancelled' => $batch->cancelled(),
                'created_at' => $batch->createdAt,
                'finished_at' => $batch->finishedAt,
            ]
        ]);
    }

    public function showForm()
    {
        $user = Auth::user();

        if ($user->role?->name === 'Root') {
            $courses = Course::with('area')->orderBy('nombre')->get();
        } else {
            $courses = Course::with('area')
                ->where('area_id', $user->area_id)
                ->orderBy('nombre')
                ->get();
        }

        return view('certificate-generator.form', compact('courses'));
    }

    public function generate(Request $request)
    {
        $request->validate([
            'course_id'        => 'required|exists:courses,id',
            'unidad_academica' => 'required|string|max:150',
            'area_siglas'      => 'required|string|max:150',
            'subarea'          => 'required|string|max:150',
            'ano'              => 'required|digits:4',
            'tipo_certificado' => 'required|string|regex:/^[\pL\s\-]+$/u',
        ]);

        $user   = Auth::user();
        $course = Course::with('area')->findOrFail($request->course_id);

        if ($user->role?->name !== 'Root' && $course->area_id != $user->area_id) {
            return back()->withErrors(['course_id' => 'No tenés permisos para este curso.']);
        }

        $persons = Person::whereHas('areas', function ($q) use ($course) {
                $q->where('areas.id', $course->area_id);
            })
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->get();

        $personasConCertificado = Certificate::where('course_id', $course->id)
            ->pluck('person_id')
            ->toArray();

        $totalArea   = $persons->count();
        $yaGenerados = count($personasConCertificado);

        try {
            $filePath = $this->buildExcel(
                $persons,
                $course,
                strtoupper(trim($request->unidad_academica)),
                strtoupper(trim($request->area_siglas)),
                strtoupper(trim($request->subarea)),
                $request->ano,
                $request->tipo_certificado,
                $yaGenerados,
                $totalArea
            );

            $fileName = 'certificados_' . Str::slug($course->nombre) . '_' . $request->ano . '.xlsx';

            return response()->download($filePath, $fileName)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('[CertificateGenerator] Error generate: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return back()->with('error', 'Error al generar el Excel: ' . $e->getMessage());
        }
    }

    public function getCourseInfo(Course $course)
    {
        $course->load('area');
        return response()->json([
            'area_nombre' => $course->area->nombre ?? '',
            'area_id'     => $course->area_id,
        ]);
    }

    public function importCertificates(Request $request)
    {
        set_time_limit(0);

        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        try {
            $importer = new CertificatesSheetImport();

            Excel::import($importer, $request->file('excel_file'));

            $errors         = $importer->getErrors();
            $generatedCount = $importer->getImportedCount();
            $batchId        = $importer->getBatchId(); // 🔥 CLAVE

            $summary = [
                'certificados' => [
                    'enviados_a_cola' => $generatedCount,
                    'errores'         => count($errors),
                ],
            ];

            Log::info('[CertificateGenerator] Importación enviada a cola.', $summary);

            return redirect()->route('certificates.index')
                ->with('success', "Se enviaron {$generatedCount} certificados a la cola.")
                ->with('batch_id', $batchId); // 🔥 CLAVE

        } catch (\Exception $e) {
            Log::error('[CertificateGenerator] Error crítico import: ' . $e->getMessage());

            return redirect()->route('certificate-generator.form')
                ->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    // =========================================================
    // 🔥 CONSTRUCCIÓN DEL EXCEL
    // =========================================================

    private function buildExcel(
        $persons,
        Course $course,
        string $unidadAcademica,
        string $areaSiglasInput,
        string $subarea,
        string $ano,
        string $tipoCertificado,
        int $yaGenerados = 0,
        int $totalArea = 0
    ): string {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'dni', 'curso', 'nota', 'unidad_academica', 'area', 'subarea',
            'codigo_incremental', 'ano', 'tipo_certificado', 'iniciales',
            '3_ultimos_del_dni', 'cuv',
        ];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        $this->applyHeaderStyle($sheet, 'A1:L1');

        $areaNombreCompleto = strtoupper(trim($course->area->nombre ?? $areaSiglasInput));

        // 🔥 SIGLAS CORRECTAS
        $uaSiglas   = $this->getSiglas($unidadAcademica);
        $areaSiglas = $this->getSiglas($areaNombreCompleto);
        $subSiglas  = $this->getSiglas($subarea);
        $tipoSiglas = $this->getTipoSiglas($tipoCertificado);

        $anioCorto = substr($ano, -2);

        $row = 2;
        foreach ($persons as $person) {

            $dni         = $person->dni;
            $iniciales   = $this->getIniciales($person->apellido, $person->nombre);
            $tresUltimos = substr($dni, -3);
            $codigoInc   = $person->id;

            $cuv =
                $uaSiglas .
                $areaSiglas .
                $subSiglas .
                $codigoInc .
                $anioCorto .
                $tipoSiglas .
                $iniciales .
                $tresUltimos;

            $rowData = [
                $dni,
                $course->nombre,
                '',
                $unidadAcademica,
                $areaNombreCompleto,
                $subarea,
                $codigoInc,
                $ano,
                $tipoCertificado,
                $iniciales,
                $tresUltimos,
                $cuv,
            ];

            foreach ($rowData as $col => $value) {
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $value);
            }

            $row++;
        }

        $filePath = storage_path('app/temp_excel/certificados_' . time() . '.xlsx');
        (new Xlsx($spreadsheet))->save($filePath);

        return $filePath;
    }

    // =========================================================
    // 🔥 FUNCIONES CLAVE
    // =========================================================

    private function getSiglas(string $texto, int $max = 3): string
    {
        $texto = strtoupper(trim($texto));

        $ignorar = ['DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y'];

        $palabras = preg_split('/\s+/', $texto);

        if (count($palabras) === 1) {
            return substr($texto, 0, $max);
        }

        $siglas = '';

        foreach ($palabras as $palabra) {
            if (!in_array($palabra, $ignorar)) {
                $siglas .= substr($palabra, 0, 1);
            }
        }

        return substr($siglas, 0, $max);
    }

    private function getTipoSiglas(string $texto): string
    {
        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $texto), 0, 3));
    }

    private function getIniciales(string $apellido, string $nombre): string
    {
        return strtoupper(mb_substr(trim($apellido), 0, 1)) .
               strtoupper(mb_substr(trim($nombre), 0, 1));
    }

    private function applyHeaderStyle($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF70AD47']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
    }
}