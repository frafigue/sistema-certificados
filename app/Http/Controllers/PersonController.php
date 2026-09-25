<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Person;
use Illuminate\Validation\Rule;
use App\Imports\PersonsImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use App\Models\Area;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class PersonController extends Controller
{
    public function index(Request $request)
    {
        $user  = Auth::user();
        $query = Person::query();

        if ($user->role?->name === 'Administrador' && $user->area_id) {
            $query->whereHas('areas', function($q) use ($user) {
                $q->where('areas.id', $user->area_id);
            });
        }

        if ($request->filled('dni')) {
            $query->where('dni', 'like', '%' . $request->dni . '%');
        }
        if ($request->filled('nombre')) {
            $query->where(function($q) use ($request) {
                $q->where('nombre', 'like', '%' . $request->nombre . '%')
                  ->orWhere('apellido', 'like', '%' . $request->nombre . '%');
            });
        }

        $people = $query->latest()->paginate(10)->withQueryString();
        return view('persons.index', compact('people'));
    }

    public function create()
    {
        $user  = Auth::user();
        $areas = Area::all();

        if ($user->role?->name === 'Administrador') {
            $areas = Area::where('id', $user->area_id)->get();
        }

        return view('persons.create', compact('areas'));
    }

    public function store(Request $request)
    {
        $request->merge(['email' => strtolower(trim($request->email))]);

        $data = $request->validate([
            'dni'       => 'required|string',
            'apellido'  => 'required|string|max:255',
            'nombre'    => 'required|string|max:255',
            'titulo'    => 'nullable|string|max:255',
            'domicilio' => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:255',
            'email'     => 'required|email',
            'area_id'   => 'required|exists:areas,id',
        ]);

        $existingPerson = Person::where('dni', $data['dni'])->first();

        // =========================================================
        // CASO 1: DNI ya existe
        // Solo agregar al área. NO tocar el email.
        // =========================================================
        if ($existingPerson) {

            $yaEnEstaArea = $existingPerson->areas()
                ->where('areas.id', $data['area_id'])
                ->exists();

            if ($yaEnEstaArea) {
                return back()
                    ->withErrors(['dni' => 'Esta persona ya está registrada en esta área.'])
                    ->withInput();
            }

            // Solo agregar al área nueva
            $existingPerson->areas()->attach($data['area_id']);

            $areaNueva = Area::find($data['area_id']);
            return redirect()->route('persons.index')
                ->with('success', "La persona {$existingPerson->nombre} {$existingPerson->apellido} fue agregada al área \"{$areaNueva->nombre}\" exitosamente.");
        }

        // =========================================================
        // CASO 2: DNI nuevo — validar emails únicos
        // =========================================================

        if (Person::whereRaw('LOWER(email) = ?', [$data['email']])->exists()) {
            return back()->withErrors(['email' => 'El email ya está asignado a otra persona.'])->withInput();
        }

        if (User::whereRaw('LOWER(email) = ?', [$data['email']])->exists()) {
            return back()->withErrors(['email' => 'El email ya está en uso por otro usuario del sistema.'])->withInput();
        }

        // =========================================================
        // CASO 3: Persona nueva — crear normalmente
        // =========================================================
        $person = Person::create($data);
        $personaRole = Role::where('name', 'Persona')->first();

        if ($personaRole) {
            $user = User::create([
                'name'     => $person->nombre . ' ' . $person->apellido,
                'email'    => $person->email,
                'password' => Hash::make($person->dni),
                'role_id'  => $personaRole->id,
                'area_id'  => $person->area_id,
            ]);
            $person->user_id = $user->id;
            $person->save();
        }

        $person->areas()->attach($data['area_id']);

        return redirect()->route('persons.index')
            ->with('success', 'Persona y cuenta de usuario creadas exitosamente.');
    }

    public function showImportForm()
    {
        return view('persons.import');
    }

    public function analyzeImport(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file     = $request->file('excel_file');
            $tempPath = $file->store('temp_imports', 'local');
            $fullPath = Storage::disk('local')->path($tempPath);

            Log::info('Archivo temporal guardado en: ' . $fullPath);

            $import = new PersonsImport(analyzeOnly: true);
            Excel::import($import, $fullPath);

            $newCount       = $import->getImportedCount();
            $withChanges    = $import->getWithChanges();
            $withoutChanges = $import->getWarnings();
            $errors         = $import->getErrors();
            $totalRows      = $import->getTotalRows();

            session([
                'import_temp_path' => $tempPath,
                'import_analysis'  => [
                    'new_count'       => $newCount,
                    'with_changes'    => $withChanges,
                    'without_changes' => $withoutChanges,
                    'errors'          => $errors,
                    'total_rows'      => $totalRows,
                ],
            ]);

            return redirect()->route('persons.import.preview');

        } catch (\Exception $e) {
            Log::error('Error en análisis de importación: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return redirect()->route('persons.import.form')
                ->with('error', 'Error al analizar el archivo: ' . $e->getMessage());
        }
    }

    public function importPreview()
    {
        $analysis = session('import_analysis');
        $tempPath = session('import_temp_path');

        if (!$analysis || !$tempPath) {
            return redirect()->route('persons.import.form')
                ->with('error', 'No hay datos de análisis. Por favor subí el archivo nuevamente.');
        }

        return view('persons.import-preview', compact('analysis'));
    }

    public function confirmImport(Request $request)
    {
        $analysis = session('import_analysis');
        $tempPath = session('import_temp_path');

        if (!$analysis || !$tempPath) {
            return redirect()->route('persons.import.form')
                ->with('error', 'Sesión expirada. Por favor subí el archivo nuevamente.');
        }

        $dniesToUpdate = $request->input('update_dns', []);

        try {
            $fullPath = Storage::disk('local')->path($tempPath);

            Log::info('Confirmando importación desde: ' . $fullPath);

            $import = new PersonsImport(analyzeOnly: false, dniesToUpdate: $dniesToUpdate);
            Excel::import($import, $fullPath);
            $import->forceFinalFlush();
            $import->onImportCompleted();

            $importedCount = $import->getImportedCount();
            $errors        = $import->getErrors();
            $warnings      = $import->getWarnings();
            $withChanges   = $import->getWithChanges();

            Storage::disk('local')->delete($tempPath);
            session()->forget(['import_temp_path', 'import_analysis']);

            $updatedCount = count(array_filter($withChanges, fn($w) => in_array($w['dni'], $dniesToUpdate)));
            $message      = "Importación completada. {$importedCount} personas nuevas agregadas.";
            if ($updatedCount > 0) {
                $message .= " {$updatedCount} persona(s) actualizadas.";
            }

            if (!empty($errors)) {
                return redirect()->route('persons.import.form')
                    ->with('import_errors', $errors)
                    ->with('import_warnings', $warnings)
                    ->with('warning', $message . ' Revisá los avisos a continuación.');
            }

            return redirect()->route('persons.index')->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Error en confirmación de importación: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return redirect()->route('persons.import.form')
                ->with('error', 'Error al importar: ' . $e->getMessage());
        }
    }

    public function import(Request $request)
    {
        return $this->analyzeImport($request);
    }

    public function downloadTemplate()
    {
        $this->generateTemplate();
        $filePath = storage_path('app/templates/plantilla_importacion_personas.xlsx');
        return response()->download($filePath, 'plantilla_importacion_personas.xlsx');
    }

    private function generateTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

            while ($spreadsheet->getSheetCount() > 1) {
                $spreadsheet->removeSheetByIndex(1);
            }

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Datos de Personas');

            $headers = ['dni', 'apellido', 'nombre', 'titulo', 'domicilio', 'telefono', 'email', 'areaasignada'];
            foreach ($headers as $index => $header) {
                $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
            }

            $user       = Auth::user();
            $areasQuery = Area::query();
            if ($user->role?->name === 'Administrador' && $user->area_id) {
                $areasQuery->where('id', $user->area_id);
            }

            $areas       = $areasQuery->get();
            $exampleArea = $areas->where('id', $user->area_id)->first() ?? $areas->first();

            $exampleData = [
                '12345678', 'Pérez', 'Juan', 'Ingeniero',
                'Calle Falsa 123', '3884123555', 'juan@email.com',
                $exampleArea ? $exampleArea->nombre : 'Nombre del Área',
            ];

            foreach ($exampleData as $index => $value) {
                $sheet->setCellValueByColumnAndRow($index + 1, 2, $value);
            }

            $sheet->getStyle('A1:H1')->getFont()->setBold(true);
            $sheet->getStyle('A1:H1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');

            foreach (range('A', 'H') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $sheet->setCellValue('A4', 'NOTA: El nombre del área debe coincidir EXACTAMENTE con las áreas disponibles en el sistema.');
            $sheet->mergeCells('A4:H4');
            $sheet->getStyle('A4')->getFont()->setItalic(true)->setSize(9)->getColor()->setARGB('FF0000FF');

            $areasDisponibles = $areas->pluck('nombre')->implode(', ');
            $sheet->setCellValue('A5', 'Áreas disponibles: ' . $areasDisponibles);
            $sheet->mergeCells('A5:H5');
            $sheet->getStyle('A5')->getFont()->setSize(9)->setBold(true)->getColor()->setARGB('FF006400');

            $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filePath = storage_path('app/templates/plantilla_importacion_personas.xlsx');

            if (!file_exists(dirname($filePath))) {
                mkdir(dirname($filePath), 0755, true);
            }

            $writer->save($filePath);
            Log::info('✅ Plantilla generada exitosamente');

        } catch (\Exception $e) {
            Log::error('❌ Error al generar plantilla: ' . $e->getMessage());
            throw $e;
        }
    }

    public function edit(Person $person)
    {
        $areas = Area::all();
        return view('persons.edit', compact('person', 'areas'));
    }

    public function update(Request $request, Person $person)
    {
        $request->merge(['email' => strtolower(trim($request->email))]);

        $data = $request->validate([
            'dni'       => 'required|string|unique:persons,dni,' . $person->id,
            'apellido'  => 'required|string|max:255',
            'nombre'    => 'required|string|max:255',
            'titulo'    => 'nullable|string|max:255',
            'domicilio' => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:255',
            'email'     => 'required|email|unique:persons,email,' . $person->id,
            'area_id'   => 'required|exists:areas,id',
        ]);

        $person->update($data);
        return redirect()->route('persons.index')->with('success', 'Persona actualizada exitosamente.');
    }

    public function updateProfile(Request $request)
    {
        $person = Auth::user()->person;
        $data   = $request->validate([
            'dni'       => 'required|string|unique:persons,dni,' . $person->id,
            'apellido'  => 'required|string|max:255',
            'nombre'    => 'required|string|max:255',
            'titulo'    => 'nullable|string|max:255',
            'domicilio' => 'nullable|string|max:255',
            'telefono'  => 'nullable|string|max:255',
        ]);
        $person->update($data);
        return redirect()->route('profile.edit')->with('success', 'Datos personales actualizados.');
    }

    public function destroy(Person $person)
    {
        if ($person->certificates()->count() > 0) {
            return redirect()->route('persons.index')
                ->with('error', 'No se puede eliminar esta persona porque tiene certificados asociados.');
        }
        $person->delete();
        return redirect()->route('persons.index')->with('success', 'Persona eliminada exitosamente.');
    }

    public function bulkDestroy(Request $request)
    {
        $user = Auth::user();
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return redirect()->route('persons.index')
                ->with('error', 'No seleccionaste personas.');
        }

        $persons = Person::whereIn('id', $ids)->get();
        $error = false;

        foreach ($persons as $person) {

            if ($user->role?->name === 'Administrador') {
                $pertenece = $person->areas()
                    ->where('areas.id', $user->area_id)
                    ->exists();

                if (!$pertenece) {
                    continue;
                }
            }

            if ($person->certificates()->count() > 0) {
                $error = true;
                continue;
            }

            if ($user->role?->name === 'Administrador') {
                $person->areas()->detach($user->area_id);

                if ($person->areas()->count() === 0) {
                    $person->delete();
                }
            } else {
                $person->delete();
            }
        }

        if ($error) {
            return redirect()->route('persons.index')
                ->with('error', 'No se pudo eliminar una o más personas porque tienen certificados asociados.');
        }

        return redirect()->route('persons.index')
            ->with('success', 'Eliminación masiva completada.');
    }
}