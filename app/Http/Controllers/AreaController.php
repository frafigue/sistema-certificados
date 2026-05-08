<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Area;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use iio\libmergepdf\Merger;
use Throwable;

class AreaController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Dimensiones reales del PDF A4 landscape en DomPDF (px a 96dpi)
    // El canvas del diseñador trabaja DIRECTAMENTE en estas coordenadas.
    // NO se aplica ningún factor de escala en buildHtmlFromDesign.
    // ─────────────────────────────────────────────────────────────────────────
    const PDF_W = 1122;
    const PDF_H = 793;

    public function index(Request $request)
    {
        $user = Auth::user();

        if ($user->can('is-root')) {
            $query = Area::latest();
        } elseif ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            $query = Area::where('id', $user->area_id)->latest();
        } else {
            $query = Area::whereRaw('1 = 0')->latest();
        }

        if ($request->filled('nombre')) {
            $query->where('nombre', 'like', '%' . $request->nombre . '%');
        }

        $areas = $query->paginate(10)->withQueryString();
        return view('areas.index', compact('areas'));
    }

    public function create()
    {
        $user = Auth::user();
        if (!$user->can('is-root')) {
            abort(403, 'Solo el administrador del sistema puede crear nuevas áreas.');
        }
        return view('areas.create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user->can('is-root')) {
            abort(403, 'Solo el administrador del sistema puede crear nuevas áreas.');
        }
        $data = $request->validate([
            'nombre'      => 'required|string|unique:areas,nombre|max:255',
            'descripcion' => 'nullable|string',
        ]);
        Area::create($data);
        return redirect()->route('areas.index')->with('success', 'Área creada exitosamente.');
    }

    public function show(Area $area)
    {
        $user = Auth::user();
        if (!$this->canAccessArea($user, $area)) {
            abort(403, 'No tienes permisos para ver esta área.');
        }
        return view('areas.show', compact('area'));
    }

    public function edit(Area $area)
    {
        $user = Auth::user();
        if (!$this->canAccessArea($user, $area)) {
            abort(403, 'No tienes permisos para editar esta área.');
        }
        return view('areas.edit', compact('area'));
    }

    public function update(Request $request, Area $area)
    {
        $user = Auth::user();
        if (!$this->canAccessArea($user, $area)) {
            abort(403, 'No tienes permisos para actualizar esta área.');
        }

        $validationRules = [
            'descripcion' => 'nullable|string',
        ];

        if ($user->can('is-root')) {
            $validationRules['nombre'] = 'required|string|max:255|unique:areas,nombre,' . $area->id;
        }

        $data = $request->validate($validationRules);
        $area->update($data);

        return redirect()->route('areas.index')->with('success', 'Área actualizada exitosamente.');
    }

    public function destroy(Area $area)
    {
        $user = Auth::user();
        if (!$user->can('is-root')) {
            abort(403, 'Solo el administrador del sistema puede eliminar áreas.');
        }
        if ($area->courses()->count() > 0) {
            return redirect()->route('areas.index')
                ->with('error', 'No se puede eliminar esta área porque tiene cursos asociados.');
        }
        $area->delete();
        return redirect()->route('areas.index')->with('success', 'Área eliminada exitosamente.');
    }

    public function designTemplate(Area $area)
    {
        $user = Auth::user();
        if (!$this->canAccessArea($user, $area)) {
            abort(403, 'No tienes permisos para diseñar las plantillas de esta área.');
        }

        $designFront = null;
        $designBack  = null;

        if ($area->template_front) {
            $decoded = $this->extractDesignFromTemplate($area->template_front);
            if ($decoded) $designFront = $decoded;
        }

        if ($area->template_back) {
            $decoded = $this->extractDesignFromTemplate($area->template_back);
            if ($decoded) $designBack = $decoded;
        }

        return view('areas.design-visual', compact('area', 'designFront', 'designBack'));
    }

    public function uploadBackground(Request $request, Area $area)
    {
        $user = Auth::user();
        if (!$this->canAccessArea($user, $area)) {
            abort(403);
        }

        $request->validate([
            'image' => 'required|image|max:5120',
            'side'  => 'required|in:front,back',
        ]);

        $path = $request->file('image')->store(
            'area-backgrounds/' . $area->id,
            'public'
        );

        return response()->json([
            'url'  => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    public function saveTemplate(Request $request, Area $area)
    {
        $user = Auth::user();
        if (!$this->canAccessArea($user, $area)) {
            abort(403, 'No tienes permisos para guardar las plantillas de esta área.');
        }

        $request->validate([
            'design_front' => 'required|string',
            'design_back'  => 'nullable|string', // ← dorso opcional
        ]);

        $designFront = json_decode($request->design_front, true);
        // Si no viene dorso, usar diseño vacío
        $designBack  = $request->filled('design_back')
            ? json_decode($request->design_back, true)
            : ['background' => '', 'elements' => []];

        if (!$designFront) {
            return back()->withErrors(['design' => 'El diseño del frente no es válido.']);
        }

        $designFront = $this->sanitizeDesignBase64($designFront, $area, 'front');
        $designBack  = $this->sanitizeDesignBase64($designBack,  $area, 'back');

        $templateFront = $this->buildHtmlFromDesign($designFront);
        $templateBack  = $this->buildHtmlFromDesign($designBack);

        $area->update([
            'template_front' => $templateFront,
            'template_back'  => $templateBack,
        ]);

        return redirect()->route('areas.design', $area)
            ->with('success', 'Plantillas guardadas exitosamente.');
    }

    public function previewFromDesign(Request $request, Area $area)
    {
        ini_set('memory_limit', '512M');

        $user = Auth::user();
        if (!$this->canAccessArea($user, $area)) {
            abort(403);
        }

        $request->validate([
            'design_front' => 'required|string',
            'design_back'  => 'required|string',
        ]);

        $designFront = json_decode($request->design_front, true);
        $designBack  = json_decode($request->design_back,  true);

        if (!$designFront || !$designBack) {
            return response()->json(['error' => 'Diseño inválido.'], 422);
        }

        $designFront = $this->sanitizeDesignBase64($designFront, $area, 'front');
        $designBack  = $this->sanitizeDesignBase64($designBack,  $area, 'back');

        [$data, $tempPath, $uniqueCode] = $this->buildPreviewData($area, 'livepreview_' . time());

        try {
            $templateFront = $this->buildHtmlFromDesign($designFront);
            $htmlFront     = Blade::render($templateFront, $data);
            unset($templateFront);

            // Dorso: solo si tiene contenido
            $backEmpty = empty($designBack['elements']) && empty($designBack['background']);
            $htmlBack  = null;
            if (!$backEmpty) {
                $templateBack = $this->buildHtmlFromDesign($designBack);
                $htmlBack     = Blade::render($templateBack, $data);
                unset($templateBack);
            }

            $finalPdfContent = $this->generateCertificatePdf($htmlFront, $htmlBack, $tempPath, $uniqueCode);

            return response($finalPdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="preview_' . $area->nombre . '.pdf"');

        } catch (Throwable $e) {
            return response('Error al generar la preview: ' . $e->getMessage(), 500);
        }
    }

    public function previewTemplate(Area $area)
    {
        ini_set('memory_limit', '512M');

        $user = Auth::user();
        if (!$this->canAccessArea($user, $area)) {
            abort(403);
        }

        if (empty($area->template_front) || empty($area->template_back)) {
            return 'Esta área no tiene ambas plantillas definidas.';
        }

        $designFront = $this->extractDesignFromTemplate($area->template_front);
        $designBack  = $this->extractDesignFromTemplate($area->template_back);

        if ($designFront && $designBack) {
            $templateFront = $this->buildHtmlFromDesign($designFront);
            $templateBack  = $this->buildHtmlFromDesign($designBack);
        } else {
            $templateFront = $area->template_front;
            $templateBack  = $area->template_back;
        }

        [$data, $tempPath, $uniqueCode] = $this->buildPreviewData($area, 'preview_' . time());

        try {
            $htmlFront = Blade::render($templateFront, $data);
            unset($templateFront);

            // Dorso: solo si tiene contenido
            $backEmpty = empty($designBack) ||
                         (empty($designBack['elements']) && empty($designBack['background']));
            $htmlBack  = null;
            if (!$backEmpty && !empty($templateBack)) {
                $htmlBack = Blade::render($templateBack, $data);
                unset($templateBack);
            }

            $finalPdfContent = $this->generateCertificatePdf($htmlFront, $htmlBack, $tempPath, $uniqueCode);

            return response($finalPdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="preview.pdf"');

        } catch (Throwable $e) {
            return 'Error al renderizar la plantilla: ' . $e->getMessage();
        }
    }

    public function clearTemplate(Area $area)
    {
        $user = Auth::user();
        if (!$this->canAccessArea($user, $area)) {
            abort(403);
        }

        $area->update([
            'template_front' => null,
            'template_back'  => null,
        ]);

        return redirect()->route('areas.design', $area)
            ->with('success', 'Diseño eliminado. Podés empezar desde cero.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ─────────────────────────────────────────────────────────────────────────

    private function canAccessArea($user, $area): bool
    {
        if ($user->can('is-root')) return true;

        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            return $area->id == $user->area_id;
        }

        return false;
    }

    private function buildPreviewData(Area $area, string $uniqueCode): array
    {
        $person = new \App\Models\Person([
            'nombre'   => 'Juan',
            'apellido' => 'Pérez',
            'dni'      => '12345678',
        ]);

        $course = new \App\Models\Course([
            'nombre'    => 'Curso de Ejemplo',
            'horas'     => 40,
            'objetivo'  => 'Capacitar al personal en el uso de herramientas tecnológicas.',
            'contenido' => 'Módulo 1: Introducción. Módulo 2: Práctica. Módulo 3: Evaluación.',
        ]);
        $course->area = $area;

        $course->setRelation('responsables', collect([
            new \App\Models\CourseResponsable(['nombre' => 'María García',    'cargo' => 'Capacitadora',  'signature_path' => null]),
            new \App\Models\CourseResponsable(['nombre' => 'Carlos López',    'cargo' => 'Coordinador',   'signature_path' => null]),
            new \App\Models\CourseResponsable(['nombre' => 'Ana Martínez',    'cargo' => 'Directora',     'signature_path' => null]),
            new \App\Models\CourseResponsable(['nombre' => 'Pedro Rodríguez', 'cargo' => 'Supervisor',    'signature_path' => null]),
            new \App\Models\CourseResponsable(['nombre' => 'Laura Sánchez',   'cargo' => 'Coordinadora',  'signature_path' => null]),
        ]));

        $certificateData = [
            'cuv'                 => 'UAAREASUB1' . date('Y') . 'APRJP678',
            'tipo_de_certificado' => 'Aprobado',
            'tipo_certificado'    => 'APR',
            'ano'                 => date('Y'),
            'horas'               => 40,
            'nota'                => 8,
            'unidad_academica'    => 'UA',
            'area'                => 'AREA',
            'subarea'             => 'SUB',
        ];

        $data = [
            'person'          => $person,
            'course'          => $course,
            'certificateData' => $certificateData,
            'qr_path'         => public_path('images/logo.png'),
        ];

        $tempPath = storage_path('app/temp_pdf');

        return [$data, $tempPath, $uniqueCode];
    }

    private function sanitizeDesignBase64(array $design, Area $area, string $side): array
    {
        $bg = $design['background'] ?? '';

        if ($bg && str_starts_with($bg, 'data:image/')) {
            try {
                preg_match('/^data:(image\/\w+);base64,/', $bg, $matches);
                $mimeType  = $matches[1] ?? 'image/png';
                $extension = str_replace('image/', '', $mimeType);
                $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $bg));

                $filename = 'area-backgrounds/' . $area->id . '/' . $side . '_' . time() . '.' . $extension;
                Storage::disk('public')->put($filename, $imageData);

                $design['background'] = Storage::disk('public')->url($filename);

                unset($imageData);
            } catch (Throwable $e) {
                $design['background'] = '';
            }
        }

        return $design;
    }

    /**
     * Construye el HTML para DomPDF en coordenadas REALES del PDF (1122×793px).
     *
     * ── WYSIWYG garantizado ──────────────────────────────────────────────────
     * El canvas del diseñador (design-visual.blade.php) trabaja internamente con
     * width:1122px / height:793px y aplica CSS transform:scale(0.75) solo para
     * visualización en pantalla.  Las coordenadas x, y, width, height y fontSize
     * que se guardan en el JSON ya están en el espacio de 1122×793px, que es
     * exactamente el espacio que DomPDF usa para A4 landscape a 96dpi.
     *
     * Por lo tanto NO se aplica ningún factor de escala aquí.
     * ────────────────────────────────────────────────────────────────────────
     */
    private function buildHtmlFromDesign(array $design): string
    {
        $bgImage  = $design['background'] ?? '';
        $elements = $design['elements']   ?? [];

        $pdfW = self::PDF_W; // 1122
        $pdfH = self::PDF_H; // 793

        // ── Resolver ruta local del fondo ────────────────────────────────────
        if ($bgImage && str_starts_with($bgImage, 'http')) {
            $storagePublicUrl = Storage::disk('public')->url('');
            $relativePath     = ltrim(str_replace($storagePublicUrl, '', $bgImage), '/');
            $localPath        = Storage::disk('public')->path($relativePath);

            if (file_exists($localPath)) {
                $bgImage = str_replace('\\', '/', $localPath);
            }
        }

        $bgStyle = $bgImage
            ? "background-image: url('{$bgImage}'); background-size: cover; background-position: center; background-repeat: no-repeat;"
            : "background-color: #ffffff;";

        $elementsHtml = '';

        foreach ($elements as $el) {
            $type = $el['type'] ?? '';

            // ── Coordenadas directas, sin escala ────────────────────────────
            $x = (int) round($el['x']      ?? 0);
            $y = (int) round($el['y']      ?? 0);
            $w = (int) round($el['width']  ?? 200);
            $h = (int) round($el['height'] ?? 40);

            // ── Fuente directa, sin escala ───────────────────────────────────
            $fontSize = (int) round($el['fontSize'] ?? 14);

            $style = "position:absolute; left:{$x}px; top:{$y}px; width:{$w}px; height:{$h}px; z-index:1;";

            switch ($type) {

                case 'nombre':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? true)  ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden;\">"
                        . "{{ \$person->nombre }} {{ \$person->apellido }}"
                        . "</div>";
                    break;

                case 'curso':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? false) ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden;\">"
                        . "{{ \$course->nombre }}"
                        . "</div>";
                    break;

                case 'fecha':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">"
                        . "{{ date('d/m/Y') }}"
                        . "</div>";
                    break;

                case 'anio':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">"
                        . "{{ \$certificateData['ano'] ?? date('Y') }}"
                        . "</div>";
                    break;

                case 'condicion':
                    $color = $el['color'] ?? '#000000';
                    $bold  = ($el['bold'] ?? false) ? 'font-weight:bold;' : '';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold} text-align:{$align}; overflow:hidden;\">"
                        . "{{ \$certificateData['tipo_de_certificado'] ?? '' }}"
                        . "</div>";
                    break;

                case 'area':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">"
                        . "{{ \$course->area->nombre ?? '' }}"
                        . "</div>";
                    break;

                case 'horas':
                    $color = $el['color'] ?? '#000000';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">"
                        . "{{ \$course->horas ?? '' }} horas"
                        . "</div>";
                    break;

                case 'cuv':
                    $color = $el['color'] ?? '#666666';
                    $align = $el['align'] ?? 'center';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; text-align:{$align}; overflow:hidden;\">"
                        . "CUV: {{ \$certificateData['cuv'] ?? '' }}"
                        . "</div>";
                    break;

                case 'qr':
                    $elementsHtml .= "<img src=\"{{ \$qr_path }}\" style=\"{$style} object-fit:contain;\">";
                    break;

                case 'objetivo':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? false) ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'left';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden; word-wrap:break-word;\">"
                        . "<strong>Objetivo:</strong> {{ \$course->objetivo ?? '' }}"
                        . "</div>";
                    break;

                case 'contenido':
                    $color  = $el['color']  ?? '#000000';
                    $bold   = ($el['bold']   ?? false) ? 'font-weight:bold;'  : '';
                    $italic = ($el['italic'] ?? false) ? 'font-style:italic;' : '';
                    $align  = $el['align']  ?? 'left';
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden; word-wrap:break-word;\">"
                        . "<strong>Contenido:</strong> {{ \$course->contenido ?? '' }}"
                        . "</div>";
                    break;

                case 'firma_responsable':
                    $index  = (int)($el['firma_index'] ?? 0);
                    $color  = $el['color'] ?? '#000000';
                    $align  = $el['align'] ?? 'center';
                    // Altura de imagen de firma: 55% del alto del bloque
                    $imgH   = (int) round($h * 0.55);
                    $fontSizeNombre = $fontSize;
                    $fontSizeCargo  = max(9, $fontSize - 2);

                    $elementsHtml .= "@php \$_resp = \$course->responsables->get({$index}); @endphp"
                        . "@if(\$_resp)"
                        . "<div style=\"{$style} text-align:{$align};\">"
                        . "@if(\$_resp->signature_path)"
                        . "<img src=\"{{ storage_path('app/public/' . \$_resp->signature_path) }}\" "
                        . "style=\"width:100%; height:{$imgH}px; object-fit:contain; display:block;\">"
                        . "@else"
                        . "<div style=\"width:100%; height:{$imgH}px; border-bottom:1px solid {$color}; display:block;\"></div>"
                        . "@endif"
                        . "<div style=\"font-size:{$fontSizeNombre}px; color:{$color}; font-weight:bold; line-height:1.4; text-align:{$align};\">"
                        . "{{ \$_resp->nombre }}"
                        . "</div>"
                        . "<div style=\"font-size:{$fontSizeCargo}px; color:{$color}; line-height:1.3; text-align:{$align};\">"
                        . "{{ \$_resp->cargo ?? '' }}"
                        . "</div>"
                        . "</div>"
                        . "@endif";
                    break;

                case 'texto':
                    $color   = $el['color']   ?? '#000000';
                    $bold    = ($el['bold']    ?? false) ? 'font-weight:bold;'  : '';
                    $italic  = ($el['italic']  ?? false) ? 'font-style:italic;' : '';
                    $align   = $el['align']   ?? 'left';
                    $content = htmlspecialchars($el['content'] ?? 'Texto libre');
                    $elementsHtml .= "<div style=\"{$style} font-size:{$fontSize}px; color:{$color}; {$bold}{$italic} text-align:{$align}; overflow:hidden;\">"
                        . $content
                        . "</div>";
                    break;

                case 'imagen':
                    $src = $el['src'] ?? '';
                    if ($src) {
                        if (str_starts_with($src, 'http')) {
                            $storagePublicUrl = Storage::disk('public')->url('');
                            $relativePath     = ltrim(str_replace($storagePublicUrl, '', $src), '/');
                            $localPath        = Storage::disk('public')->path($relativePath);
                            $src = file_exists($localPath)
                                ? str_replace('\\', '/', $localPath)
                                : $src;
                        }
                        $elementsHtml .= "<img src=\"{$src}\" style=\"{$style} object-fit:contain;\">";
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
        @page { margin: 0; size: {$pdfW}px {$pdfH}px; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            width: {$pdfW}px;
            height: {$pdfH}px;
            overflow: hidden;
            font-family: 'DejaVu Sans', 'Helvetica', Arial, sans-serif;
        }
        .cert-page {
            position: relative;
            width: {$pdfW}px;
            height: {$pdfH}px;
            overflow: hidden;
        }
    </style>
</head>
<body>
<!-- DESIGN_JSON:{$designJson}:END_DESIGN_JSON -->
<div class="cert-page" style="{$bgStyle}">
{$elementsHtml}
</div>
</body>
</html>
HTML;
    }

    private function extractDesignFromTemplate(string $html): ?array
    {
        if (preg_match('/<!-- DESIGN_JSON:(.*?):END_DESIGN_JSON -->/s', $html, $matches)) {
            $json = html_entity_decode($matches[1], ENT_QUOTES);
            return json_decode($json, true);
        }
        return null;
    }

    private function generateCertificatePdf(string $htmlFront, ?string $htmlBack, string $tempPath, string $uniqueCode): string
    {
        File::ensureDirectoryExists($tempPath);
        $frontFilePath = $tempPath . '/' . $uniqueCode . '_front.pdf';

        Pdf::loadHTML($htmlFront)->setPaper('a4', 'landscape')->save($frontFilePath);
        unset($htmlFront);

        // Si no hay dorso, devolver solo el frente
        if (empty($htmlBack)) {
            $content = File::get($frontFilePath);
            File::delete($frontFilePath);
            return $content;
        }

        $backFilePath = $tempPath . '/' . $uniqueCode . '_back.pdf';
        Pdf::loadHTML($htmlBack)->setPaper('a4', 'landscape')->save($backFilePath);
        unset($htmlBack);

        $merger = new Merger;
        $merger->addFile($frontFilePath);
        $merger->addFile($backFilePath);
        $finalPdfContent = $merger->merge();

        File::delete($frontFilePath, $backFilePath);
        return $finalPdfContent;
    }
}