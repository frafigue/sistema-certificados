<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CertificateTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use iio\libmergepdf\Merger;
use Throwable;

class TemplateController extends Controller
{
    const PDF_W = 1122;
    const PDF_H = 793;

    // =========================================================================
    // CRUD BÁSICO
    // =========================================================================

    public function index()
    {
        $user = Auth::user();

        if ($user->can('is-root')) {
            $templates = CertificateTemplate::latest()->get();
        } else {
            $templates = CertificateTemplate::where('created_by', $user->id)->latest()->get();
        }

        return view('templates.index', compact('templates'));
    }

    public function create()
    {
        return view('templates.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'paper_size' => 'required|in:A4,A5,Oficio,Carta,custom',
            'orientation' => 'required|in:portrait,landscape',
            'width_cm' => 'nullable|numeric|min:1|max:100',
            'height_cm' => 'nullable|numeric|min:1|max:100',
        ]);

        $template = CertificateTemplate::create([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'paper_size' => $request->paper_size,
            'orientation' => $request->orientation,
            'width_cm' => $request->paper_size === 'custom' ? $request->width_cm : null,
            'height_cm' => $request->paper_size === 'custom' ? $request->height_cm : null,
            'background_image' => null,
            'components' => null,
            'is_public' => false,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('templates.design', $template->id)
            ->with('success', 'Plantilla creada. Ahora diseñala.');
    }

    public function show(string $id)
    {
        $template = CertificateTemplate::findOrFail($id);
        $this->authorizeAccess($template);

        return view('templates.show', compact('template'));
    }

    public function edit(string $id)
    {
        $template = CertificateTemplate::findOrFail($id);
        $this->authorizeAccess($template);

        return view('templates.edit', compact('template'));
    }

    public function update(Request $request, string $id)
    {
        $template = CertificateTemplate::findOrFail($id);
        $this->authorizeAccess($template);

        $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'paper_size' => 'required|in:A4,A5,Oficio,Carta,custom',
            'orientation' => 'required|in:portrait,landscape',
            'width_cm' => 'nullable|numeric|min:1|max:100',
            'height_cm' => 'nullable|numeric|min:1|max:100',
        ]);

        $template->update([
            'nombre' => $request->nombre,
            'descripcion' => $request->descripcion,
            'paper_size' => $request->paper_size,
            'orientation' => $request->orientation,
            'width_cm' => $request->paper_size === 'custom' ? $request->width_cm : null,
            'height_cm' => $request->paper_size === 'custom' ? $request->height_cm : null,
        ]);

        return redirect()->route('templates.index')
            ->with('success', 'Plantilla actualizada.');
    }

    public function destroy(string $id)
    {
        $template = CertificateTemplate::findOrFail($id);
        $this->authorizeAccess($template);

        $template->delete();

        return redirect()->route('templates.index')
            ->with('success', 'Plantilla eliminada.');
    }

    // =========================================================================
    // DISEÑO DE PLANTILLAS
    // =========================================================================

    public function designTemplate(CertificateTemplate $template)
    {
        $this->authorizeAccess($template);

        $designFront = null;
        $designBack  = null;

        if ($template->components) {
            $decoded = json_decode($template->components, true);
            if ($decoded) $designFront = $decoded;
        }

        return view('templates.design-visual', compact('template', 'designFront', 'designBack'));
    }

    public function uploadBackground(Request $request, CertificateTemplate $template)
    {
        $this->authorizeAccess($template);

        $request->validate([
            'image' => 'required|image|max:5120',
            'side'  => 'required|in:front,back',
        ]);

        $path = $request->file('image')->store(
            'template-backgrounds/' . $template->id,
            'public'
        );

        return response()->json([
            'url'  => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    public function saveTemplate(Request $request, CertificateTemplate $template)
    {
        $this->authorizeAccess($template);

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

        $designFront = $this->sanitizeDesignBase64($designFront, $template, 'front');
        $designBack  = $this->sanitizeDesignBase64($designBack,  $template, 'back');

        $template->update([
            'components' => json_encode($designFront),
        ]);

        return redirect()->route('templates.design', $template->id)
            ->with('success', 'Diseño guardado exitosamente.');
    }

    public function previewTemplate(CertificateTemplate $template)
    {
        set_time_limit(120);
        ini_set('memory_limit', '512M');

        $this->authorizeAccess($template);

        if (empty($template->components)) {
            return 'Esta plantilla no tiene diseño guardado.';
        }

        $designFront = json_decode($template->components, true);

        $service = app(\App\Services\CertificateService::class);
        $templateHtml = $service->buildHtmlFromDesign($designFront);

        $data = $this->buildPreviewData($template);

        try {
            $htmlFront = Blade::render($templateHtml, $data);

            $pdf = Pdf::loadHTML($htmlFront)
                ->setPaper('a4', 'landscape')
                ->setOptions([
                    'dpi'                     => 96,
                    'defaultFont'             => 'sans-serif',
                    'isRemoteEnabled'         => false,
                    'isHtml5ParserEnabled'    => true,
                    'isFontSubsettingEnabled' => true,
                ]);

            return $pdf->stream('preview.pdf');

        } catch (Throwable $e) {
            return 'Error al renderizar la plantilla: ' . $e->getMessage();
        }
    }

    public function clearTemplate(CertificateTemplate $template)
    {
        $this->authorizeAccess($template);

        $template->update([
            'components' => null,
        ]);

        return redirect()->route('templates.design', $template->id)
            ->with('success', 'Diseño eliminado. Podés empezar desde cero.');
    }

    // =========================================================================
    // HELPERS PRIVADOS
    // =========================================================================

    private function authorizeAccess(CertificateTemplate $template): void
    {
        $user = Auth::user();

        if ($user->can('is-root')) {
            return;
        }

        if ($template->created_by != $user->id) {
            abort(403, 'No tienes permisos para acceder a esta plantilla.');
        }
    }

    private function buildPreviewData(CertificateTemplate $template): array
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

        return [
            'person'          => $person,
            'course'          => $course,
            'certificateData' => $certificateData,
            'qr_path'         => public_path('images/logo.png'),
        ];
    }

    private function sanitizeDesignBase64(array $design, CertificateTemplate $template, string $side): array
    {
        $bg = $design['background'] ?? '';

        if ($bg && str_starts_with($bg, 'data:image/')) {
            try {
                preg_match('/^data:(image\/\w+);base64,/', $bg, $matches);
                $mimeType  = $matches[1] ?? 'image/png';
                $extension = str_replace('image/', '', $mimeType);
                $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $bg));

                $filename = 'template-backgrounds/' . $template->id . '/' . $side . '_' . time() . '.' . $extension;
                Storage::disk('public')->put($filename, $imageData);

                $design['background'] = '/storage/' . $filename;
                unset($imageData);
            } catch (Throwable $e) {
                $design['background'] = '';
            }
        }

        return $design;
    }
}