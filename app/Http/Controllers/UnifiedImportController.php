<?php

namespace App\Http\Controllers;

use App\Imports\UnifiedImport;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class UnifiedImportController extends Controller
{
    public function showForm()
    {
        return view('unified-import.index');
    }

    public function import(Request $request)
    {
        set_time_limit(0);

        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls|max:20480',
        ]);

        try {
            $import = new UnifiedImport();
            Excel::import($import, $request->file('excel_file'));

            $import->forceFinalFlush();
            $import->onImportCompleted();

            $personsErrors  = $import->getPersonsErrors();
            $certsErrors    = $import->getCertificatesErrors();
            $allErrors      = array_merge($personsErrors, $certsErrors);

            $summary = [
                'personas' => [
                    'creadas'    => $import->getPersonsImportedCount(),
                    'existentes' => $import->getPersonsSkippedCount(),
                    'errores'    => count($personsErrors),
                ],
                'certificados' => [
                    'generados' => $import->getCertificatesImportedCount(),
                    'errores'   => count($certsErrors),
                ],
            ];

            Log::info('[UnifiedImport] Proceso finalizado.', $summary);

            if (!empty($allErrors)) {
                return redirect()->route('unified-import.form')
                    ->with('import_summary', $summary)
                    ->with('import_errors', $allErrors)
                    ->with('warning', 'El proceso finalizó con algunos errores. Revisá el detalle.');
            }

            return redirect()->route('unified-import.form')
                ->with('import_summary', $summary)
                ->with('success', 'Proceso completado exitosamente.');

        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            $errors = [];
            foreach ($e->failures() as $failure) {
                $errors[] = [
                    'hoja'    => 'Excel',
                    'fila'    => $failure->row(),
                    'errores' => $failure->errors(),
                    'valores' => array_slice($failure->values(), 0, 4),
                ];
            }
            return redirect()->route('unified-import.form')
                ->with('import_errors', $errors)
                ->with('error', 'Se encontraron errores de validación en el archivo.');

        } catch (\Exception $e) {
            Log::error('[UnifiedImport] Error crítico: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return redirect()->route('unified-import.form')
                ->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $filePath = storage_path('app/templates/plantilla_unificada.xlsx');

        if (!file_exists($filePath)) {
            $this->generateTemplate($filePath);
        }

        return response()->download($filePath, 'plantilla_unificada.xlsx');
    }

    private function generateTemplate(string $filePath): void
    {
        $spreadsheet = new Spreadsheet();

        // ── HOJA 1: PERSONAS ────────────────────────────────────────────────
        $sh1 = $spreadsheet->getActiveSheet();
        $sh1->setTitle('Personas');

        $headersP = ['dni','apellido','nombre','titulo','domicilio','telefono','email','areaasignada'];
        foreach ($headersP as $i => $h) {
            $sh1->setCellValueByColumnAndRow($i + 1, 1, $h);
        }

        $user  = Auth::user();
        $query = Area::query();
        if ($user->role?->name === 'Administrador' && $user->area_id) {
            $query->where('id', $user->area_id);
        }
        $areas       = $query->get();
        $exampleArea = $areas->first();
        $areasString = $areas->pluck('nombre')->implode(', ');

        $exampleP = ['28456123','García','Juan','Ing.','Av. San Martín 123','3884111222','juan@mail.com', $exampleArea?->nombre ?? 'Nombre del Área'];
        foreach ($exampleP as $i => $v) {
            $sh1->setCellValueByColumnAndRow($i + 1, 2, $v);
        }

        $this->applyHeaderStyle($sh1, 'A1:H1', 'FF4472C4');
        $sh1->setCellValue('A4', '(*) Obligatorios: dni, apellido, nombre, email, areaasignada');
        $sh1->mergeCells('A4:H4');
        $sh1->getStyle('A4')->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF595959');
        $sh1->setCellValue('A5', 'Áreas disponibles: ' . $areasString);
        $sh1->mergeCells('A5:H5');
        $sh1->getStyle('A5')->getFont()->setSize(9)->setBold(true)->getColor()->setARGB('FF006400');
        foreach (range('A', 'H') as $col) { $sh1->getColumnDimension($col)->setAutoSize(true); }

        // ── HOJA 2: CERTIFICADOS ─────────────────────────────────────────────
        $sh2 = $spreadsheet->createSheet();
        $sh2->setTitle('Certificados');

        $headersC = ['dni','curso','nota','unidad_academica','area','subarea','codigo_incremental','ano','tipo_certificado','iniciales','3_ultimos_del_dni','cuv'];
        foreach ($headersC as $i => $h) {
            $sh2->setCellValueByColumnAndRow($i + 1, 1, $h);
        }

        $exampleC = ['28456123','Nombre Exacto del Curso','8','SS','SAL','PAB','45',date('Y'),'APR','GJ','123','(vacío = generación automática)'];
        foreach ($exampleC as $i => $v) {
            $sh2->setCellValueByColumnAndRow($i + 1, 2, $v);
        }

        $this->applyHeaderStyle($sh2, 'A1:L1', 'FF70AD47');
        $sh2->setCellValue('A4', '(*) Obligatorios: dni, curso, unidad_academica, area, subarea, codigo_incremental, ano, tipo_certificado, iniciales, 3_ultimos_del_dni');
        $sh2->mergeCells('A4:L4');
        $sh2->getStyle('A4')->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF595959');
        $sh2->setCellValue('A5', 'tipo_certificado: APR (Aprobado) | ASI (Asistente) | CAP (Capacitador)');
        $sh2->mergeCells('A5:L5');
        $sh2->getStyle('A5')->getFont()->setSize(9)->setBold(true)->getColor()->setARGB('FF006400');
        $sh2->setCellValue('A6', 'cuv: dejar vacío para generación automática. Se construye concatenando los campos anteriores.');
        $sh2->mergeCells('A6:L6');
        $sh2->getStyle('A6')->getFont()->setSize(9)->setItalic(true)->getColor()->setARGB('FF0070C0');
        $sh2->setCellValue('A7', 'IMPORTANTE: El DNI debe coincidir con un DNI de la Hoja "Personas" o con una persona ya existente en el sistema.');
        $sh2->mergeCells('A7:L7');
        $sh2->getStyle('A7')->getFont()->setSize(9)->setBold(true)->getColor()->setARGB('FFCC0000');
        foreach (range('A', 'L') as $col) { $sh2->getColumnDimension($col)->setAutoSize(true); }

        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        (new Xlsx($spreadsheet))->save($filePath);
        Log::info('[UnifiedImport] Plantilla generada: ' . $filePath);
    }

    private function applyHeaderStyle($sheet, string $range, string $color): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $color]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCCCCCC']]],
        ]);
    }
}