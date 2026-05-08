<?php

namespace App\Imports;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Person;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;
use Illuminate\Support\Facades\Mail;
use App\Mail\CertificateSent;
use Illuminate\Support\Facades\Blade;

class CertificatesImport implements ToCollection, WithHeadingRow, WithCalculatedFormulas
{
    private $errors = [];
    private $importedCount = 0;

    public function collection(Collection $rows)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        foreach ($rows as $rowIndex => $row) {
            try {

                // 🔹 NORMALIZAR
                $cleanedRow = [];
                foreach ($row as $key => $value) {
                    if ($key) {
                        $cleanedRow[strtolower(str_replace([' ', '-'], '_', trim($key)))] = $value;
                    }
                }

                $cursoNombre = $cleanedRow['curso'] ?? null;
                $cuv = $cleanedRow['cuv'] ?? null;
                $dni = $cleanedRow['dni'] ?? null;

                if (!$cursoNombre || !$cuv || !$dni) continue;

                $course = Course::with(['area'])
                    ->where('nombre', trim($cursoNombre))
                    ->first();

                if (!$course) continue;

                // 🔹 CONDITION
                $map = [
                    'APR' => 'Aprobado',
                    'ASI' => 'Asistente',
                    'CAP' => 'Capacitador',
                ];

                $tipo = strtoupper(trim($cleanedRow['tipo_certificado'] ?? ''));
                $condition = $map[$tipo] ?? 'Asistente';

                // 🔹 CUV
                if (Certificate::where('unique_code', $cuv)->exists()) continue;

                // 🔹 PERSONA
                $person = Person::firstOrCreate(
                    ['dni' => $dni],
                    [
                        'apellido' => $cleanedRow['apellido'] ?? 'N/A',
                        'nombre' => $cleanedRow['nombre'] ?? 'N/A',
                        'email' => $dni . '@tmp.com'
                    ]
                );

                // 🔹 QR
                $qrPath = 'qrcodes/' . $cuv . '.svg';

                QrCode::format('svg')
                    ->size(100) // 🔥 más chico
                    ->generate(route('certificates.verify', $cuv),
                        storage_path('app/public/' . $qrPath)
                    );

                // 🔥 TEMPLATE ÚNICO (SIN BACK PDF SEPARADO)
                $data = [
                    'person' => $person,
                    'course' => $course,
                    'certificateData' => $cleanedRow,
                    'condition' => $condition,
                    'qr_path' => storage_path('app/public/' . $qrPath),
                ];

                $html = Blade::render($course->area->template_front, $data);

                // 🔥 DOMPDF ULTRA LIVIANO
                $pdf = Pdf::loadHTML($html)
                    ->setPaper('a4', 'landscape')
                    ->setOptions([
                        'dpi' => 60,
                        'defaultFont' => 'sans-serif',
                        'isRemoteEnabled' => false,
                        'isHtml5ParserEnabled' => false,
                    ]);

                $pdfPath = 'certificates/' . $cuv . '.pdf';

                Storage::disk('public')->put($pdfPath, $pdf->output());

                // 🔥 LIBERAR MEMORIA
                unset($pdf);
                gc_collect_cycles();

                // 🔹 DB
                $certificate = Certificate::create([
                    'course_id' => $course->id,
                    'person_id' => $person->id,
                    'condition' => $condition,
                    'tipo_certificado' => $tipo,
                    'unique_code' => $cuv,
                    'qr_path' => $qrPath,
                    'pdf_path' => $pdfPath,
                ]);

                // 🔹 EMAIL
                if ($person->email && !str_ends_with($person->email, '@tmp.com')) {
                    Mail::to($person->email)->queue(new CertificateSent($certificate));
                }

                $this->importedCount++;

            } catch (Throwable $e) {
                $this->errors[] = "Fila " . ($rowIndex + 2) . ": " . $e->getMessage();
            }
        }
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getImportedCount()
    {
        return $this->importedCount;
    }
}