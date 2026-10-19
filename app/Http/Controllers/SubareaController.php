<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subarea;
use App\Models\Area;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use iio\libmergepdf\Merger;
use Throwable;

class SubareaController extends Controller
{
    const PDF_W = 1122;
    const PDF_H = 793;

    public function index()
    {
        $user = Auth::user();

        if ($user->role_id == 1) {
            $subareas = Subarea::with(['area', 'gestor'])->get();
        } elseif ($user->role_id == 2) {
            if ($user->area_id) {
                $subareas = Subarea::where('area_id', $user->area_id)
                    ->with(['area', 'gestor'])
                    ->get();
            } else {
                $subareas = collect();
            }
        } else {
            $subareas = Subarea::where('id', $user->subarea_id)->with(['area', 'gestor'])->get();
        }

        return view('subareas.index', compact('subareas'));
    }

    public function create()
    {
        $user = Auth::user();

        if ($user->role_id == 1) {
            $areas = Area::all();
        } else {
            $areas = Area::where('id', $user->area_id)->get();
        }

        return view('subareas.create', compact('areas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'area_id' => 'required|exists:areas,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        Subarea::create([
            'area_id' => $request->area_id,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'gestor_id' => null,
            'estado' => 'sin_gestor',
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('subareas.index')
            ->with('success', 'Subárea creada. Contactá al Root para asignar un gestor.');
    }

    public function show(string $id)
    {
        $subarea = Subarea::with(['area', 'gestor'])->findOrFail($id);
        return view('subareas.show', compact('subarea'));
    }

    public function edit(string $id)
    {
        $subarea = Subarea::findOrFail($id);
        $areas = Area::all();

        return view('subareas.edit', compact('subarea', 'areas'));
    }

    public function update(Request $request, string $id)
    {
        $request->validate([
            'area_id' => 'required|exists:areas,id',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $subarea = Subarea::findOrFail($id);
        $subarea->update([
            'area_id' => $request->area_id,
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
        ]);

        return redirect()->route('subareas.index')
            ->with('success', 'Subárea actualizada.');
    }

    public function destroy(string $id)
    {
        $subarea = Subarea::findOrFail($id);
        $subarea->delete();

        return redirect()->route('subareas.index')
            ->with('success', 'Subárea eliminada.');
    }

    // =========================================================================
    // DISEÑO DE PLANTILLAS
    // =========================================================================

    public function designTemplate(Subarea $subarea)
    {
        $user = Auth::user();
        if (!$this->canAccessSubarea($user, $subarea)) {
            abort(403, 'No tienes permisos para diseñar las plantillas de esta subárea.');
        }

        $designFront = null;
        $designBack  = null;

        if ($subarea->template_front) {
            $decoded = $this->extractDesignFromTemplate($subarea->template_front);
            if ($decoded) $designFront = $decoded;
        }

        if ($subarea->template_back) {
            $decoded = $this->extractDesignFromTemplate($subarea->template_back);
            if ($decoded) $designBack = $decoded;
        }

        return view('subareas.design-visual', compact('subarea', 'designFront', 'designBack'));
    }

    public function uploadBackground(Request $request, Subarea $subarea)
    {
        $user = Auth::user();
        if (!$this->canAccessSubarea($user, $subarea)) {
            abort(403);
        }

        $request->validate([
            'image' => 'required|image|max:5120',
            'side'  => 'required|in:front,back',
        ]);

        $path = $request->file('image')->store(
            'subarea-backgrounds/' . $subarea->id,
            'public'
        );

        return response()->json([
            'url'  => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    public function saveTemplate(Request $request, Subarea $subarea)
    {
        $user = Auth::user();
        if (!$this->canAccessSubarea($user, $subarea)) {
            abort(403, 'No tienes permisos para guardar las plantillas de esta subárea.');
        }

        $request->validate([
            'design_front' => 'required|string',
            'design_back'  => 'nullable|string',
        ]);

        $designFront = json_decode($request->design_front, true);
        $designBack  = $request->filled('design_back')
            ? json_decode($request->design_back, true)
            : ['background' => '', 'elements' => []];

        if (!$designFront) {
            return back()->withErrors(['design' => 'El diseño del frente no es válido.']);
        }

        $designFront = $this->sanitizeDesignBase64($designFront, $subarea, 'front');
        $designBack  = $this->sanitizeDesignBase64($designBack,  $subarea, 'back');

        $service = app(\App\Services\CertificateService::class);

        $templateFront = $service->buildHtmlFromDesign($designFront);
        $templateBack  = $service->buildHtmlFromDesign($designBack);

        $subarea->update([
            'template_front' => $templateFront,
            'template_back'  => $templateBack,
        ]);

        return redirect()->route('subareas.design', $subarea)
            ->with('success', 'Plantillas guardadas exitosamente.');
    }

    public function previewFromDesign(Request $request, Subarea $subarea)
    {
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $user = Auth::user();
        if (!$this->canAccessSubarea($user, $subarea)) {
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

        $designFront = $this->sanitizeDesignBase64($designFront, $subarea, 'front');
        $designBack  = $this->sanitizeDesignBase64($designBack,  $subarea, 'back');

        [$data, $tempPath, $uniqueCode] = $this->buildPreviewData($subarea, 'livepreview_' . time());

        try {
            $service = app(\App\Services\CertificateService::class);

            $templateFront = $service->buildHtmlFromDesign($designFront);
            $htmlFront     = Blade::render($templateFront, $data);
            unset($templateFront);

            $backEmpty = empty($designBack['elements']) && empty($designBack['background']);
            $htmlBack  = null;
            if (!$backEmpty) {
                $templateBack = $service->buildHtmlFromDesign($designBack);
                $htmlBack     = Blade::render($templateBack, $data);
                unset($templateBack);
            }

            $finalPdfContent = $this->generateCertificatePdf($htmlFront, $htmlBack, $tempPath, $uniqueCode);

            return response($finalPdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="preview_' . $subarea->nombre . '.pdf"');

        } catch (Throwable $e) {
            return response('Error al generar la preview: ' . $e->getMessage(), 500);
        }
    }

    public function previewTemplate(Subarea $subarea)
    {
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $user = Auth::user();
        if (!$this->canAccessSubarea($user, $subarea)) {
            abort(403);
        }

        if (empty($subarea->template_front) || empty($subarea->template_back)) {
            return 'Esta subárea no tiene ambas plantillas definidas.';
        }

        $designFront = $this->extractDesignFromTemplate($subarea->template_front);
        $designBack  = $this->extractDesignFromTemplate($subarea->template_back);

        $service = app(\App\Services\CertificateService::class);

        if ($designFront && $designBack) {
            $templateFront = $service->buildHtmlFromDesign($designFront);
            $templateBack  = $service->buildHtmlFromDesign($designBack);
        } else {
            $templateFront = $subarea->template_front;
            $templateBack  = $subarea->template_back;
        }

        [$data, $tempPath, $uniqueCode] = $this->buildPreviewData($subarea, 'preview_' . time());

        try {
            $htmlFront = Blade::render($templateFront, $data);
            unset($templateFront);

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

    public function clearTemplate(Subarea $subarea)
    {
        $user = Auth::user();
        if (!$this->canAccessSubarea($user, $subarea)) {
            abort(403);
        }

        $subarea->update([
            'template_front' => null,
            'template_back'  => null,
        ]);

        return redirect()->route('subareas.design', $subarea)
            ->with('success', 'Diseño eliminado. Podés empezar desde cero.');
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function canAccessSubarea($user, $subarea): bool
    {
        if ($user->can('is-root')) return true;

        if ($user->role && $user->role->name === 'Administrador' && $user->area_id) {
            return $subarea->area_id == $user->area_id;
        }

        if ($user->role && $user->role->name === 'Gestor' && $user->subarea_id) {
            return $subarea->id == $user->subarea_id;
        }

        return false;
    }

    private function buildPreviewData(Subarea $subarea, string $uniqueCode): array
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
        $course->area = $subarea->area;

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

    private function sanitizeDesignBase64(array $design, Subarea $subarea, string $side): array
    {
        $bg = $design['background'] ?? '';

        if ($bg && str_starts_with($bg, 'data:image/')) {
            try {
                preg_match('/^data:(image\/\w+);base64,/', $bg, $matches);
                $mimeType  = $matches[1] ?? 'image/png';
                $extension = str_replace('image/', '', $mimeType);
                $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $bg));

                $filename = 'subarea-backgrounds/' . $subarea->id . '/' . $side . '_' . time() . '.' . $extension;
                Storage::disk('public')->put($filename, $imageData);

                $design['background'] = '/storage/' . $filename;
                unset($imageData);
            } catch (Throwable $e) {
                $design['background'] = '';
            }
        }

        return $design;
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