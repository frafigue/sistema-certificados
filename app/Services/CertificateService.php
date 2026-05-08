<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Person;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use iio\libmergepdf\Merger;

class CertificateService
{
    public function generate(array $data): Certificate
    {
        $person = Person::findOrFail($data['person_id']);
        $course = Course::with(['area', 'responsables', 'resolution'])
            ->findOrFail($data['course_id']);

        // 🔒 Evitar duplicados
        if (Certificate::where('unique_code', $data['cuv'])->exists()) {
            return Certificate::where('unique_code', $data['cuv'])->first();
        }

        // ===============================
        // QR
        // ===============================
        $qrPath = 'qrcodes/' . $data['cuv'] . '.svg';

        Storage::disk('public')->makeDirectory('qrcodes');

        QrCode::format('svg')->size(150)->generate(
            route('certificates.verify', $data['cuv']),
            storage_path('app/public/' . $qrPath)
        );

        // ===============================
        // DATA PARA PDF
        // ===============================
        $bladeData = [
            'person' => $person,
            'course' => $course,
            'qr_path'=> storage_path('app/public/' . $qrPath),
            'certificateData' => [
                'cuv' => $data['cuv'],
                'tipo_certificado' => $data['tipo_certificado'],
                'condition' => $data['tipo_certificado'],
                'ano' => $data['ano'] ?? date('Y'),
            ],
        ];

        $templateFront = $course->area->template_front;
        $templateBack  = $course->area->template_back ?? null;

        // FRONT
        $htmlFront = Blade::render($templateFront, $bladeData);

        // BACK
        $htmlBack = $templateBack ? Blade::render($templateBack, $bladeData) : null;

        // ===============================
        // PDF
        // ===============================
        $tempPath = storage_path('app/temp_pdf');
        File::ensureDirectoryExists($tempPath);

        $frontFile = $tempPath . '/' . $data['cuv'] . '_front.pdf';
        Pdf::loadHTML($htmlFront)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'dpi' => 72,
                'defaultFont' => 'sans-serif',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => false,
            ])
            ->save($frontFile);

        if ($htmlBack) {
            $backFile = $tempPath . '/' . $data['cuv'] . '_back.pdf';
            Pdf::loadHTML($htmlBack)
                ->setPaper('a4', 'landscape')
                ->setOptions([
                    'dpi' => 72,
                    'defaultFont' => 'sans-serif',
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => false,
                ])
                ->save($backFile);

            $merger = new Merger;
            $merger->addFile($frontFile);
            $merger->addFile($backFile);

            $finalPdf = $merger->merge();

            File::delete($frontFile, $backFile);
        } else {
            $finalPdf = File::get($frontFile);
            File::delete($frontFile);
        }

        $pdfPath = 'certificates/' . $data['cuv'] . '.pdf';
        Storage::disk('public')->put($pdfPath, $finalPdf);
        unset($htmlFront);
        unset($htmlBack);
        unset($bladeData);
        unset($finalPdf);

        gc_collect_cycles();

        // ===============================
        // BD
        // ===============================
        return Certificate::create([
            'course_id'               => $course->id,
            'person_id'               => $person->id,
            'condition'               => $data['tipo_certificado'],
            'nota'                    => $data['nota'] ?? null,
            'unique_code'             => $data['cuv'],
            'qr_path'                 => $qrPath,
            'pdf_path'                => $pdfPath,
            'unidad_academica'        => $data['unidad_academica'] ?? null,
            'area_excel'              => $data['area'] ?? null,
            'subarea'                 => $data['subarea'] ?? null,
            'codigo_incremental'      => $data['codigo_incremental'] ?? null,
            'anio'                    => $data['ano'] ?? date('Y'),
            'tipo_certificado'        => $this->deriveConditionCode($data['tipo_certificado']),
            'iniciales'               => $data['iniciales'] ?? null,
            'tres_ultimos_digitos_dni'=> $data['3_ultimos_del_dni'] ?? null,
        ]);
    }

    private function deriveConditionCode(string $condition): string
    {
        $lettersOnly = preg_replace('/[^a-zA-Z]/', '', $condition);
        return strtoupper(substr($lettersOnly, 0, 3));
    }
}