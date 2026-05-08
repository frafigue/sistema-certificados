<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Person;
use App\Models\Certificate;
use Illuminate\Support\Facades\DB;

class ConsultaPublicaController extends Controller
{
    /**
     * Muestra la vista con el formulario de búsqueda de certificados (Ruta: /).
     */
    public function mostrarFormulario()
    {
        return view('consulta.formulario');
    }

    /**
     * Procesa la búsqueda por DNI o Nombre y redirige a la página de resultados.
     */
    public function buscarCertificados(Request $request)
    {
        // 1. Validar los campos
        $request->validate([
            'dni' => 'nullable|string|max:20',
            'nombre_apellido' => 'nullable|string|max:255',
        ]);

        $dni = trim($request->input('dni'));
        $nombreApellido = trim($request->input('nombre_apellido'));

        // 2. Validar que al menos uno esté lleno
        if (empty($dni) && empty($nombreApellido)) {
            return back()->withErrors([
                'error' => 'Debe ingresar al menos el DNI o el Nombre y Apellido para realizar la búsqueda.'
            ])->withInput();
        }

        $persona = null;
        
        // 3. Búsqueda por DNI (prioritaria)
        if (!empty($dni)) {
            $persona = Person::where('dni', $dni)->first();
        } 
        
        // 4. Búsqueda por Nombre y Apellido (si no se encontró por DNI)
        if (!$persona && !empty($nombreApellido)) {
            // Normalizar: convertir a minúsculas y remover espacios extras
            $busqueda = strtolower(trim(preg_replace('/\s+/', ' ', $nombreApellido)));
            
            // Estrategia 1: Búsqueda INDISTINTA del orden usando CONCAT en ambos sentidos
            $persona = Person::where(function($query) use ($busqueda) {
                // Busca: "nombre apellido" O "apellido nombre"
                $query->whereRaw("LOWER(CONCAT(nombre, ' ', apellido)) LIKE ?", ["%{$busqueda}%"])
                      ->orWhereRaw("LOWER(CONCAT(apellido, ' ', nombre)) LIKE ?", ["%{$busqueda}%"]);
            })->first();

            // Estrategia 2: Si tiene espacio, separar y buscar ambas combinaciones
            if (!$persona && strpos($busqueda, ' ') !== false) {
                $partes = explode(' ', $busqueda);
                $parte1 = $partes[0];
                $parte2 = implode(' ', array_slice($partes, 1)); // El resto como segunda parte

                $persona = Person::where(function($query) use ($parte1, $parte2) {
                    // Combinación 1: parte1 = nombre, parte2 = apellido
                    $query->where(function($q) use ($parte1, $parte2) {
                        $q->whereRaw("LOWER(nombre) LIKE ?", ["%{$parte1}%"])
                          ->whereRaw("LOWER(apellido) LIKE ?", ["%{$parte2}%"]);
                    })
                    // Combinación 2: parte1 = apellido, parte2 = nombre
                    ->orWhere(function($q) use ($parte1, $parte2) {
                        $q->whereRaw("LOWER(apellido) LIKE ?", ["%{$parte1}%"])
                          ->whereRaw("LOWER(nombre) LIKE ?", ["%{$parte2}%"]);
                    });
                })->first();
            }

            // Estrategia 3: Búsqueda individual en cualquier campo (más amplia)
            if (!$persona) {
                $persona = Person::where(function($query) use ($busqueda) {
                    $query->whereRaw("LOWER(nombre) LIKE ?", ["%{$busqueda}%"])
                          ->orWhereRaw("LOWER(apellido) LIKE ?", ["%{$busqueda}%"]);
                })->first();
            }
        }

        // 5. Obtener los certificados si la persona fue encontrada
        $certificados = collect();
        if ($persona) {
            $certificados = Certificate::where('person_id', $persona->id)
                                        ->with(['course.area', 'person']) 
                                        ->get();
        }
        
        // 6. Redirigir a la vista de resultados
        return redirect()->route('certificados.resultado')->with([
            'persona' => $persona,
            'certificados' => $certificados,
        ]);
    }

    /**
     * Muestra la vista de resultados, recuperando los datos de la sesión.
     */
    public function mostrarResultados(Request $request)
    {
        // Recuperar los datos de la sesión
        $persona = $request->session()->get('persona');
        $certificados = $request->session()->get('certificados');

        // Si no hay datos en la sesión, redirige al formulario
        if (!$request->session()->has('persona') && !$request->session()->has('certificados')) {
             return redirect()->route('consulta.home');
        }

        return view('consulta.resultados', [
            'persona' => $persona,
            'certificados' => $certificados,
        ]);
    }

    /**
     * Descarga el certificado de forma pública.
     */
    public function descargarCertificado($id)
    {
        $certificado = Certificate::findOrFail($id);
        
        // Verificar que tenga un PDF asociado
        if (!$certificado->pdf_path) {
            abort(404, 'Certificado PDF no encontrado');
        }
        
        // Construir la ruta completa del archivo PDF
        $pathPDF = storage_path('app/public/' . $certificado->pdf_path);
        
        // Verificar que el archivo existe físicamente
        if (!file_exists($pathPDF)) {
            abort(404, 'Archivo PDF no encontrado en el servidor');
        }
        
        // Crear un nombre descriptivo para el archivo
        $nombreArchivo = 'certificado_' . $certificado->unique_code . '.pdf';
        
        // Retornar el archivo para descarga
        return response()->file($pathPDF, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $nombreArchivo . '"'
        ]);
    }
}