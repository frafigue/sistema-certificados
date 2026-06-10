<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CertificateTemplateExport;
use App\Imports\CertificatesImport;
use Illuminate\Support\Facades\File;
use iio\libmergepdf\Merger;
use Throwable;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\CertificateSent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;

class CertificateController extends Controller
{
    const CANVAS_W = 842;
    const CANVAS_H = 595;
    const PDF_W    = 1122;
    const PDF_H    = 793;

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function pdfOptions(): array
    {
        return [
            'dpi'                     => 96,
            'defaultFont'             => 'sans-serif',
            'isRemoteEnabled'         => false,
            'isHtml5ParserEnabled'    => true,
            'isFontSubsettingEnabled' => true,
        ];
    }

    private function deriveConditionCode(string $condition): string
    {
        $lettersOnly = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ]/u', '', $condition);
        return strtoupper(mb_substr($lettersOnly, 0, 3, 'UTF-8'));
    }

    private function generarSigla($texto)
    {
        $texto    = strtoupper(trim($texto));
        $palabras = preg_split('/\s+/', $texto);

        if (count($palabras) === 1) {
            return substr($texto, 0, 3);
        }

        $ignorar = ['DE', 'LA', 'LAS', 'LOS', 'DEL', 'Y'];
        $sigla   = '';

        foreach ($palabras as $p) {
            if (!in_array($p, $ignorar) && strlen($p) > 0) {
                $sigla .= substr($p, 0, 1);
            }
        }

        return substr($sigla, 0, 3);
    }

    private function extractDesignFromTemplate(?string $html): ?array
    {
        if (!$html) return null;
        if (preg_match('/<!-- DESIGN_JSON:(.*?):END_DESIGN_JSON -->/s', $html, $matches)) {
            $json = html_entity_decode($matches[1], ENT_QUOTES);
            return json_decode($json, true);
        }
        return null;
    }

    private function urlToLocalPath(string $url): ?string
    {
        if (preg_match('#/storage/(.+)$#', $url, $m)) {
            $localPath = Storage::disk('public')->path($m[1]);
            $localPath = str_replace('/', DIRECTORY_SEPARATOR, $localPath);
            if (file_exists($localPath)) {
                return $localPath;
            }
        }
        return null;
    }

    private function imageToBase64(string $path): ?string
    {
        if (!file_exists($path)) return null;

        $mime = mime_content_type($path);

        // Redimensionar y comprimir si es PNG o JPEG
        if (in_array($mime, ['image/png', 'image/jpeg', 'image/jpg'])) {
            $img = imagecreatefromstring(file_get_contents($path));
            if ($img) {
                // Redimensionar a máximo 1122x793 manteniendo proporción
                $origW = imagesx($img);
                $origH = imagesy($img);
                $maxW  = 1122;
                $maxH  = 793;

                if ($origW > $maxW || $origH > $maxH) {
                    $ratio  = min($maxW / $origW, $maxH / $origH);
                    $newW   = (int)($origW * $ratio);
                    $newH   = (int)($origH * $ratio);
                    $resized = imagecreatetruecolor($newW, $newH);
                    imagecopyresampled($resized, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
                    imagedestroy($img);
                    $img = $resized;
                }

                ob_start();
                if ($mime === 'image/png') {
                    imagepng($img, null, 6); // compresión 6/9
                } else {
                    imagejpeg($img, null, 75); // calidad 75%
                }
                $imageData = ob_get_clean();
                imagedestroy($img);

                return "data:{$mime};base64," . base64_encode($imageData);
            }
        }

        // Fallback para otros formatos
        return "data:{$mime};base64," . base64_encode(file_get_contents($path));
    }

    private function generateCertificatePdf(
        string $htmlFront,
        ?string $htmlBack,
        string $tempPath,
        string $uniqueCode
    ): string {
        File::ensureDirectoryExists($tempPath);
        $frontFilePath = $tempPath . '/' . $uniqueCode . '_front.pdf';

        Pdf::loadHTML($htmlFront)
            ->setPaper([0, 0, 793, 1122])
            ->setOptions($this->pdfOptions())
            ->save($frontFilePath);

        if (empty($htmlBack)) {
            $content = File::get($frontFilePath);
            File::delete($frontFilePath);
            return $content;
        }

        $backFilePath = $tempPath . '/' . $uniqueCode . '_back.pdf';
        Pdf::loadHTML($htmlBack)
            ->setPaper([0, 0, 793, 1122])
            ->setOptions($this->pdfOptions())
            ->save($backFilePath);

        $merger = new Merger;
        $merger->addFile($frontFilePath);
        $merger->addFile($backFilePath);
        $finalPdfContent = $merger->merge();

        File::delete($frontFilePath, $backFilePath);
        return $finalPdfContent;
    }

    public function buildHtmlFromDesign(array $design): string
    {
        $bgImage  = $design['background'] ?? '';
        $elements = $design['elements']   ?? [];
        $pdfW     = self::PDF_W;
        $pdfH     = self::PDF_H;

        if ($bgImage && str_starts_with($bgImage, 'http')) {
            $localPath = $this->urlToLocalPath($bgImage);
            $bgImage   = $localPath ? ($this->imageToBase64($localPath) ?? '') : '';
        }

        $bgStyle  = $bgImage ? '' : 'background-color: #ffffff;';
        $bgImgTag = $bgImage
            ? "<img src=\"{$bgImage}\" style=\"position:absolute;top:0;left:0;width:{$pdfW}px;height:{$pdfH}px;z-index:0;\" />"
            : '';

        $elementsHtml = '';

        foreach ($elements as $el) {
            $type     = $el['type'] ?? '';
            $x        = round($el['x']      ?? 0);
            $y        = round($el['y']      ?? 0);
            $w        = round($el['width']  ?? 200);
            $h        = round($el['height'] ?? 40);
            $fontSize = round($el['fontSize'] ?? 14);
            $style    = "position:absolute; left:{$x}px; top:{$y}px; width:{$w}px; height:{$h}px; z-index:1;";

            switch ($type) {
                case 'nombre':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? true)  ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden;\">{{ \$person->nombre }} {{ \$person->apellido }}</div>";
                    break;

                case 'curso':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? false) ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden;\">{{ \$course->nombre }}</div>";
                    break;

                case 'fecha':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">{{ date('d/m/Y') }}</div>";
                    break;

                case 'anio':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">{{ \$certificateData['ano'] ?? date('Y') }}</div>";
                    break;

                case 'condicion':
                    $color = $el['color'] ?? '#000000';
                    $bold  = ($el['bold'] ?? false) ? 'font-weight:bold;' : '';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold} text-align:{$align}; overflow:hidden;\">{{ \$certificateData['condition'] ?? \$certificateData['tipo_de_certificado'] ?? \$certificateData['tipo_certificado'] ?? '' }}</div>";
                    break;

                case 'area':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">{{ \$course->area->nombre ?? '' }}</div>";
                    break;

                case 'horas':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">{{ \$course->horas ?? '' }} horas</div>";
                    break;

                case 'cuv':
                    $color = $el['color'] ?? '#666666';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">CUV: {{ \$certificateData['cuv'] ?? '' }}</div>";
                    break;

                case 'qr':
                    $elementsHtml .= "<img src=\"{{ \$qr_path }}\" style=\"{$style} object-fit:contain;\">";
                    break;

                case 'objetivo':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? false) ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'left';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden; word-wrap:break-word;\"><strong>Objetivo:</strong> {{ \$course->objetivo ?? '' }}</div>";
                    break;

                case 'contenido':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? false) ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'left';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden; word-wrap:break-word;\"><strong>Contenido:</strong> {{ \$course->contenido ?? '' }}</div>";
                    break;

                case 'firma_responsable':
                    $index          = (int)($el['firma_index'] ?? 0);
                    $color          = $el['color'] ?? '#000000';
                    $align          = $el['align'] ?? 'center';
                    $imgH           = (int)round($h * 0.55);
                    $fontSizeNombre = $fontSize;
                    $fontSizeCargo  = max(9, $fontSize - 2);

                    $elementsHtml .= "@php \$_resp = \$course->responsables->get({$index}); @endphp"
                        . "@if(\$_resp)"
                        . "<div style=\"{$style} text-align:{$align};\">"
                        . "@php"
                        . " \$_sigPath = \$_resp->signature_path"
                        . "     ? str_replace('/', DIRECTORY_SEPARATOR, Storage::disk('public')->path(\$_resp->signature_path))"
                        . "     : null;"
                        . "@endphp"
                        . "@if(\$_sigPath && file_exists(\$_sigPath))"
                        . "@php \$_sigData = 'data:' . mime_content_type(\$_sigPath) . ';base64,' . base64_encode(file_get_contents(\$_sigPath)); @endphp"
                        . "<img src=\"{{ \$_sigData }}\" style=\"width:100%; height:{$imgH}px; object-fit:contain; display:block;\">"
                        . "@else"
                        . "<div style=\"width:100%; height:{$imgH}px; border-bottom:1px solid {$color}; display:block;\"></div>"
                        . "@endif"
                        . "<div style=\"font-size:{$fontSizeNombre}px; color:{$color}; font-weight:bold; line-height:1.4; text-align:{$align};\">{{ \$_resp->nombre }}</div>"
                        . "<div style=\"font-size:{$fontSizeCargo}px; color:{$color}; line-height:1.3; text-align:{$align};\">{{ \$_resp->cargo ?? '' }}</div>"
                        . "</div>"
                        . "@endif";
                    break;

                case 'texto':
                    $color   = $el['color']   ?? '#000000';
                    $bold    = ($el['bold']    ?? false) ? 'font-weight:bold;'  : '';
                    $italic  = ($el['italic']  ?? false) ? 'font-style:italic;' : '';
                    $align   = $el['align']   ?? 'left';
                    $content = htmlspecialchars($el['content'] ?? 'Texto libre');
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden;\">{$content}</div>";
                    break;

                case 'imagen':
                    $src = $el['src'] ?? '';
                    if ($src) {
                        if (str_starts_with($src, 'http')) {
                            $localPath = $this->urlToLocalPath($src);
                            $src       = $localPath ? ($this->imageToBase64($localPath) ?? '') : '';
                        }
                        if ($src) {
                            $elementsHtml .= "<img src=\"{$src}\" style=\"{$style} object-fit:contain;\">";
                        }
                    }
                    break;
            }
        }

        $designJson = htmlspecialchars(json_encode($design), ENT_QUOTES);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        @page { margin: 0; size: A4 landscape; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            width: {$pdfW}px; height: {$pdfH}px;
            overflow: hidden;
            font-family: 'DejaVu Sans', 'Helvetica', Arial, sans-serif;
        }
        .cert-page {
            position: relative;
            width: {$pdfW}px; height: {$pdfH}px;
            overflow: hidden;
        }
    </style>
</head>
<body>
<!-- DESIGN_JSON:{$designJson}:END_DESIGN_JSON -->
<div class="cert-page" style="{$bgStyle}">
{$bgImgTag}
{$elementsHtml}
</div>
</body>
</html>
HTML;
    }

    // =========================================================================
    // ACCIONES
    // =========================================================================

    public function index(Request $request)
    {
        $query = Certificate::query();
        $user  = Auth::user();

        if ($user->role && $user->role->name === 'Persona') {
            if ($user->person) {
                $query->where('person_id', $user->person->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            $query->whereHas('course', function ($q) use ($user) {
                $q->where('area_id', $user->area_id);
            });
        }

        if ($request->filled('search_dni')) {
            $query->whereHas('person', function ($q) use ($request) {
                $q->where('dni', 'like', '%' . $request->search_dni . '%');
            });
        }

        if ($request->filled('search_course')) {
            $query->whereHas('course', function ($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->search_course . '%');
            });
        }

        if ($request->filled('search_area')) {
            $query->where('area_excel', 'like', '%' . $request->search_area . '%');
        }

        if ($request->filled('search_tipo')) {
            $query->where('condition', $request->search_tipo);
        }

        $tiposQuery = Certificate::select('condition')->distinct()->orderBy('condition');

        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            $tiposQuery->whereHas('course', function ($q) use ($user) {
                $q->where('area_id', $user->area_id);
            });
        }

        $tiposDisponibles = $tiposQuery->pluck('condition');
        $certificates     = $query->with(['person', 'course.area'])->latest()->paginate(10);

        return view('certificates.index', compact('certificates', 'tiposDisponibles'));
    }

    public function create()
    {
        $user = Auth::user();

        if (!$user->role || !in_array($user->role->name, ['Root', 'Administrador'])) {
            abort(403, 'No tienes permisos para crear certificados.');
        }

        $people = collect();

        if ($user->role->name === 'Root') {
            $courses = Course::orderBy('nombre')->get();
        } elseif ($user->area_id) {
            $courses = Course::where('area_id', $user->area_id)->orderBy('nombre')->get();
        } else {
            $courses = Course::orderBy('nombre')->get();
        }

        return view('certificates.create', compact('courses', 'people'));
    }

    public function store(Request $request)
    {
        set_time_limit(300);
        ini_set('memory_limit', '512M');

        try {
            $user = Auth::user();
            if (!$user->role || !in_array($user->role->name, ['Root', 'Administrador'])) {
                abort(403, 'No tienes permisos para crear certificados.');
            }

            $request->validate([
                'course_id'        => 'required|exists:courses,id',
                'person_id'        => 'required|exists:persons,id',
                'condition'        => 'required|string|min:3|max:100|regex:/^[\pL\s\-]+$/u',
                'nota'             => 'nullable|numeric|min:0',
                'unidad_academica' => 'required|string|max:255',
                'subarea'          => 'required|string|max:255',
                'iniciales'        => 'required|string|max:255',
                'anio'             => 'required|digits:4|integer|min:2000|max:2099',
            ]);

            $course = Course::with(['resolution', 'area', 'responsables'])->findOrFail($request->course_id);
            $person = Person::findOrFail($request->person_id);

            $unidadAcademica = $this->generarSigla($request->unidad_academica);
            $subarea         = $this->generarSigla($request->subarea);
            $areaCode        = $this->generarSigla($course->area->nombre ?? '');
            $iniciales       = strtoupper($request->iniciales);
            $anio            = $request->anio;
            $anioCorto       = substr($anio, -2);
            $tresUltimosDni  = substr($person->dni, -3);
            $conditionCode   = $this->deriveConditionCode($request->condition);

            $uniqueCode = $unidadAcademica
                        . $areaCode
                        . $subarea
                        . $person->id
                        . $anioCorto
                        . $conditionCode
                        . $iniciales
                        . $tresUltimosDni;

            if (Certificate::where('unique_code', $uniqueCode)->exists()) {
                return back()->withErrors(['cuv' => 'El CUV ya existe.']);
            }

            $qrPath = 'qrcodes/' . $uniqueCode . '.svg';
            Storage::disk('public')->makeDirectory('qrcodes');
            QrCode::format('svg')->size(150)->generate(
                route('certificates.verify', $uniqueCode),
                storage_path('app/public/' . $qrPath)
            );

            $data = [
                'person'          => $person,
                'course'          => $course,
                'certificateData' => [
                    'cuv'                 => $uniqueCode,
                    'tipo_de_certificado' => $request->condition,
                    'horas'               => $course->horas,
                    'ano'                 => $anio,
                    'condition'           => $request->condition,
                ],
                'qr_path' => storage_path('app/public/' . $qrPath),
            ];

            $area = $course->area;

            if (empty($area->template_front)) {
                return back()->withErrors(['template' => 'Falta plantilla']);
            }

            $designFront = $this->extractDesignFromTemplate($area->template_front);
            $htmlFront   = Blade::render(
                $designFront ? $this->buildHtmlFromDesign($designFront) : $area->template_front,
                $data
            );

            $htmlBack = null;
            if (!empty($area->template_back)) {
                $designBack = $this->extractDesignFromTemplate($area->template_back);
                if ($designBack && (!empty($designBack['elements']) || !empty($designBack['background']))) {
                    $htmlBack = Blade::render($this->buildHtmlFromDesign($designBack), $data);
                }
            }

            if (strlen($htmlFront) > 5000000) {
                return back()->withErrors(['pdf' => 'El diseño del certificado es demasiado pesado.']);
            }

            $tempPath        = storage_path('app/temp_pdf');
            $finalPdfContent = $this->generateCertificatePdf($htmlFront, $htmlBack, $tempPath, $uniqueCode);

            $pdfPath = 'certificates/' . $uniqueCode . '.pdf';
            Storage::disk('public')->put($pdfPath, $finalPdfContent);

            unset($htmlFront, $htmlBack, $finalPdfContent);
            gc_collect_cycles();

            $certificate = Certificate::create([
                'course_id'                => $course->id,
                'person_id'                => $person->id,
                'condition'                => $request->condition,
                'nota'                     => $request->nota,
                'unidad_academica'         => $unidadAcademica,
                'area_excel'               => $course->area->nombre ?? null,
                'subarea'                  => $subarea,
                'codigo_incremental'       => $person->id,
                'anio'                     => $anio,
                'tipo_certificado'         => $conditionCode,
                'iniciales'                => $iniciales,
                'tres_ultimos_digitos_dni' => $tresUltimosDni,
                'unique_code'              => $uniqueCode,
                'qr_path'                  => $qrPath,
                'pdf_path'                 => $pdfPath,
                'email_status'             => 'pendiente',
                'email_error'              => null,
            ]);

            if (!empty($person->email)) {
                \App\Jobs\SendCertificateEmail::dispatch($certificate);
            }

            return redirect()->route('certificates.index')
                ->with('success', 'Certificado generado correctamente');

        } catch (\Throwable $e) {
            Log::error('ERROR GENERANDO CERTIFICADO: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Error al generar el certificado. Ver logs.']);
        }
    }

    public function show(Certificate $certificate)
    {
        $user = Auth::user();

        if ($user->role && $user->role->name === 'Administrador') {
            if ($user->role->name !== 'Root' && $user->area_id && $certificate->course->area_id != $user->area_id) {
                abort(403, 'No tienes permisos para ver este certificado.');
            }
        } elseif ($user->role && $user->role->name !== 'Root') {
            if (!$user->person || $certificate->person_id != $user->person->id) {
                abort(403, 'No tienes permisos para ver este certificado.');
            }
        }

        return view('certificates.show', compact('certificate'));
    }

    public function import(Request $request)
    {
        set_time_limit(0);
        $request->validate(['excel_file' => 'required|mimes:xlsx,xls']);

        $import = new CertificatesImport;

        try {
            Excel::import($import, $request->file('excel_file'));
        } catch (Throwable $e) {
            return redirect()->route('certificates.index')
                ->with('import_errors', ['Hubo un error crítico durante la importación: ' . $e->getMessage()]);
        }

        $importedCount = $import->getImportedCount();
        $errors        = $import->getErrors();
        $successMsg    = "Proceso finalizado. Se importaron {$importedCount} certificados exitosamente.";

        if (!empty($errors)) {
            return redirect()->route('certificates.index')
                ->with('success', $successMsg)
                ->with('import_errors', $errors);
        }

        return redirect()->route('certificates.index')->with('success', 'La importación se ha realizado exitosamente.');
    }

    public function edit(Certificate $certificate)
    {
        $user = Auth::user();

        if (!$user->role || !in_array($user->role->name, ['Root', 'Administrador'])) {
            abort(403, 'No tienes permisos para editar certificados.');
        }

        if ($user->role->name !== 'Root' && $user->area_id && $certificate->course->area_id != $user->area_id) {
            abort(403, 'No tienes permisos para editar este certificado.');
        }

        $people = Person::orderBy('apellido')->get();

        if ($user->role->name === 'Root') {
            $courses = Course::orderBy('nombre')->get();
        } elseif ($user->area_id) {
            $courses = Course::where('area_id', $user->area_id)->orderBy('nombre')->get();
        } else {
            $courses = Course::orderBy('nombre')->get();
        }

        return view('certificates.edit', compact('certificate', 'courses', 'people'));
    }

    public function update(Request $request, Certificate $certificate)
    {
        $user = Auth::user();

        if (!$user->role || !in_array($user->role->name, ['Root', 'Administrador'])) {
            abort(403, 'No tienes permisos para actualizar certificados.');
        }

        if ($user->role->name !== 'Root' && $user->area_id && $certificate->course->area_id != $user->area_id) {
            abort(403, 'No tienes permisos para actualizar este certificado.');
        }

        $request->validate([
            'course_id' => 'required|exists:courses,id',
            'person_id' => 'required|exists:persons,id',
            'condition' => 'required|string|min:3|max:100|regex:/^[\pL\s\-]+$/u',
            'nota'      => 'nullable|numeric|min:0',
        ]);

        $course = Course::with(['responsables'])->findOrFail($request->course_id);

        if ($user->role->name !== 'Root' && $user->area_id && $course->area_id != $user->area_id) {
            return redirect()->back()
                ->withErrors(['course_id' => 'No puedes asignar cursos fuera de tu área.'])
                ->withInput();
        }

        if ($certificate->pdf_path) Storage::disk('public')->delete($certificate->pdf_path);
        if ($certificate->qr_path)  Storage::disk('public')->delete($certificate->qr_path);

        $qrPath = 'qrcodes/' . $certificate->unique_code . '.svg';
        QrCode::format('svg')->size(150)->generate(
            route('certificates.verify', $certificate->unique_code),
            storage_path('app/public/' . $qrPath)
        );

        $person = Person::find($request->person_id);
        $data   = [
            'person'        => $person,
            'course'        => $course,
            'condition'     => $request->condition,
            'nota'          => $request->nota,
            'unique_code'   => $certificate->unique_code,
            'qr_path'       => storage_path('app/public/' . $qrPath),
            'emission_date' => $certificate->created_at->format('d/m/Y'),
        ];

        $pdf     = Pdf::loadView('certificates.pdf_template', $data)
            ->setPaper([0, 0, 793, 1122])
            ->setOptions($this->pdfOptions());
        $pdfPath = 'certificates/' . $certificate->unique_code . '.pdf';
        Storage::disk('public')->put($pdfPath, $pdf->output());

        $certificate->update([
            'course_id'        => $request->course_id,
            'person_id'        => $request->person_id,
            'condition'        => $request->condition,
            'tipo_certificado' => $this->deriveConditionCode($request->condition),
            'nota'             => $request->nota,
            'qr_path'          => $qrPath,
            'pdf_path'         => $pdfPath,
        ]);

        return redirect()->route('certificates.index')->with('success', 'Certificado actualizado exitosamente.');
    }

    public function destroy(Certificate $certificate)
    {
        $user = Auth::user();

        if (!$user->role || !in_array($user->role->name, ['Root', 'Administrador'])) {
            abort(403, 'No tienes permisos para eliminar certificados.');
        }

        if ($user->role->name !== 'Root' && $user->area_id && $certificate->course->area_id != $user->area_id) {
            abort(403, 'No tienes permisos para eliminar este certificado.');
        }

        if ($certificate->pdf_path) Storage::disk('public')->delete($certificate->pdf_path);
        if ($certificate->qr_path)  Storage::disk('public')->delete($certificate->qr_path);

        $certificate->delete();

        return redirect()->route('certificates.index')->with('success', 'Certificado eliminado exitosamente.');
    }

    public function verify($unique_code)
    {
        $certificate = Certificate::where('unique_code', $unique_code)->firstOrFail();
        return view('certificates.verify', compact('certificate'));
    }

    public function downloadTemplate()
    {
        return Excel::download(new CertificateTemplateExport, 'plantilla_certificados.xlsx');
    }

    public function showPreview()
    {
        $importData = session('import_data');
        if (empty($importData)) {
            return redirect()->route('certificates.import.form');
        }
        return view('certificates.preview', ['importData' => $importData]);
    }

    public function processImport()
    {
        ini_set('memory_limit', '512M');

        $importData = session('import_data');
        if (empty($importData)) {
            return redirect()->route('certificates.import.form')
                ->with('import_errors', ['No hay datos para importar o la sesión ha expirado.']);
        }

        $certificatesCreated = 0;

        foreach ($importData as $row) {
            $course = Course::with(['resolution', 'area', 'responsables'])->where('nombre', $row['curso'])->first();
            $person = Person::firstOrCreate(
                ['dni' => $row['dni']],
                [
                    'apellido'  => $row['apellido'],
                    'nombre'    => $row['nombre'],
                    'titulo'    => 'N/A',
                    'domicilio' => 'N/A',
                    'telefono'  => 'N/A',
                    'email'     => $row['dni'] . '@email-temporal.com',
                ]
            );

            if (!$course) continue;

            $uniqueCode = $row['cuv'];
            $qrPath     = 'qrcodes/' . $uniqueCode . '.svg';

            Storage::disk('public')->makeDirectory('qrcodes');
            QrCode::format('svg')->size(150)->generate(
                route('certificates.verify', $uniqueCode),
                storage_path('app/public/' . $qrPath)
            );

            $data = [
                'person'          => $person,
                'course'          => $course,
                'certificateData' => $row,
                'qr_path'         => storage_path('app/public/' . $qrPath),
            ];

            $area          = $course->area;
            $templateFront = $area->template_front ?? null;
            $templateBack  = $area->template_back  ?? null;

            if ($templateFront) {
                $designFront = $this->extractDesignFromTemplate($templateFront);
                if ($designFront) {
                    $templateFront = $this->buildHtmlFromDesign($designFront);
                }
            }

            if ($templateBack) {
                $designBack = $this->extractDesignFromTemplate($templateBack);
                if ($designBack && !(empty($designBack['elements']) && empty($designBack['background']))) {
                    $templateBack = $this->buildHtmlFromDesign($designBack);
                } else {
                    $templateBack = null;
                }
            }

            $tempPath  = storage_path('app/temp_pdf');
            $htmlFront = $templateFront ? Blade::render($templateFront, $data) : view('certificates.pdf_template_front', $data)->render();
            $htmlBack  = null;

            if ($templateBack) {
                $designBack  = $this->extractDesignFromTemplate($area->template_back ?? '');
                $backIsEmpty = $designBack && empty($designBack['elements']) && empty($designBack['background']);
                if (!$backIsEmpty) {
                    $htmlBack = Blade::render($templateBack, $data);
                }
            }

            $finalPdfContent = $this->generateCertificatePdf($htmlFront, $htmlBack, $tempPath, $uniqueCode);

            $pdfPath = 'certificates/' . $uniqueCode . '.pdf';
            Storage::disk('public')->put($pdfPath, $finalPdfContent);
            unset($htmlFront, $htmlBack, $finalPdfContent);

            $conditionRaw  = $row['tipo_certificado'] ?? 'Aprobado';
            $conditionCode = $this->deriveConditionCode($conditionRaw);

            $certificate = Certificate::create([
                'course_id'                => $course->id,
                'person_id'                => $person->id,
                'condition'                => $conditionRaw,
                'nota'                     => $row['nota']               ?? null,
                'unique_code'              => $uniqueCode,
                'qr_path'                  => $qrPath,
                'pdf_path'                 => $pdfPath,
                'unidad_academica'         => $row['unidad_academica']   ?? null,
                'area_excel'               => $row['area']               ?? null,
                'subarea'                  => $row['subarea']            ?? null,
                'codigo_incremental'       => $row['codigo_incremental'] ?? null,
                'anio'                     => $row['ano']                ?? null,
                'tipo_certificado'         => $conditionCode,
                'iniciales'                => $row['iniciales']          ?? null,
                'tres_ultimos_digitos_dni' => $row['3_ultimos_del_dni']  ?? null,
            ]);

            $certificatesCreated++;

            if (!empty($person->email)) {
                \App\Jobs\SendCertificateEmail::dispatch($certificate);
            }
        }

        session()->forget('import_data');

        return redirect()->route('certificates.index')
            ->with('success', "Se importaron y generaron {$certificatesCreated} certificados exitosamente.");
    }

    public function previewPdf()
    {
        $importData = session('import_data');
        if (empty($importData)) {
            return redirect()->route('certificates.import.form')
                ->with('import_errors', ['No hay datos para previsualizar.']);
        }

        $firstRow = $importData[0];
        $course   = Course::with(['resolution', 'area', 'responsables'])->where('nombre', $firstRow['curso'])->first();
        $person   = Person::firstOrCreate(
            ['dni' => $firstRow['dni']],
            [
                'apellido'  => $firstRow['apellido'],
                'nombre'    => $firstRow['nombre'],
                'titulo'    => 'N/A',
                'domicilio' => 'N/A',
                'telefono'  => 'N/A',
                'email'     => $firstRow['dni'] . '@email-temporal.com',
            ]
        );

        if (!$course || !$person) {
            return redirect()->route('certificates.import.preview')
                ->with('import_errors', ['No se pudieron encontrar los datos para generar la vista previa.']);
        }

        $data = [
            'person'          => $person,
            'course'          => $course,
            'certificateData' => $firstRow,
            'qr_path'         => public_path('images/logo.png'),
        ];

        $area          = $course->area;
        $templateFront = $area->template_front ?? null;

        if (empty($templateFront)) {
            return response("Error: El Área de este curso no tiene plantilla definida.", 500);
        }

        $designFront = $this->extractDesignFromTemplate($templateFront);
        if ($designFront) {
            $templateFront = $this->buildHtmlFromDesign($designFront);
        }

        $htmlFront = Blade::render($templateFront, $data);
        $pdf       = Pdf::loadHTML($htmlFront)
            ->setPaper([0, 0, 793, 1122])
            ->setOptions($this->pdfOptions());

        return $pdf->stream('previsualizacion_certificado.pdf');
    }

    public function getAreaByCourse(Course $course)
    {
        $course->load('area');
        return response()->json(['area_name' => $course->area->nombre ?? 'Sin Área Definida']);
    }

    public function showImportForm()
    {
        return view('certificates.import');
    }

    public function myCertificates()
    {
        $user = Auth::user();

        if (!$user->person) {
            return redirect()->back()->with('error', 'No tienes un perfil de persona asociado.');
        }

        $certificates = Certificate::with(['course.area'])
            ->where('person_id', $user->person->id)
            ->latest()
            ->paginate(10);

        return view('certificates.my-certificates', compact('certificates'));
    }

    public function sendPending()
    {
        $certificates = Certificate::where('email_status', 'pendiente')->get();

        if ($certificates->isEmpty()) {
            return redirect()->back()->with('info', 'No hay certificados pendientes.');
        }

        foreach ($certificates as $cert) {
            \App\Jobs\SendCertificateEmail::dispatch($cert);
        }

        return redirect()->back()->with(
            'success',
            'Se enviaron a cola ' . $certificates->count() . ' certificados.'
        );
    }

    public function downloadPdf(Certificate $certificate)
    {
        $path = Storage::disk('public')->path($certificate->pdf_path);

        if (!file_exists($path)) {
            abort(404, 'PDF no encontrado');
        }

        return response()->file($path);
    }

    public function getPersonsByCourse(Course $course)
    {
        $course->load('area');

        $personasConCertificado = Certificate::where('course_id', $course->id)
            ->pluck('person_id')->toArray();

        $persons = Person::whereHas('areas', function ($q) use ($course) {
                $q->where('areas.id', $course->area_id);
            })
            ->whereNotIn('id', $personasConCertificado)
            ->orderBy('apellido')
            ->get(['id', 'nombre', 'apellido', 'dni']);

        $totalArea = Person::whereHas('areas', function ($q) use ($course) {
            $q->where('areas.id', $course->area_id);
        })->count();

        return response()->json([
            'persons'    => $persons,
            'area_name'  => $course->area->nombre ?? 'Sin Área Definida',
            'total_area' => $totalArea,
            'ya_tienen'  => count($personasConCertificado),
        ]);
    }

    public function searchPersonsByCourse(Request $request, Course $course)
    {
        $course->load('area');
        $search = $request->get('q', '');

        $personasConCertificado = Certificate::where('course_id', $course->id)
            ->pluck('person_id')->toArray();

        $persons = Person::whereHas('areas', function ($q) use ($course) {
                $q->where('areas.id', $course->area_id);
            })
            ->whereNotIn('id', $personasConCertificado)
            ->where(function ($query) use ($search) {
                $query->where('nombre',    'like', '%' . $search . '%')
                      ->orWhere('apellido', 'like', '%' . $search . '%')
                      ->orWhere('dni',      'like', '%' . $search . '%');
            })
            ->orderBy('apellido')
            ->limit(10)
            ->get(['id', 'nombre', 'apellido', 'dni']);

        $results = $persons->map(function ($p) {
            return [
                'id'       => $p->id,
                'text'     => $p->apellido . ', ' . $p->nombre . ' — DNI: ' . $p->dni,
                'dni'      => $p->dni,
                'apellido' => $p->apellido,
                'nombre'   => $p->nombre,
            ];
        });

        return response()->json([
            'results'    => $results,
            'total_area' => Person::whereHas('areas', function ($q) use ($course) {
                $q->where('areas.id', $course->area_id);
            })->count(),
            'area_name'  => $course->area->nombre ?? '',
        ]);
    }
}