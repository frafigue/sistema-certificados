<?php

namespace App\Imports;
use App\Models\Person;
use App\Models\User;
use App\Models\Role;
use App\Models\Area;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PersonsImport implements ToCollection, WithHeadingRow
{
    private $areasCache      = [];
    private $personsCache    = [];
    private $emailsCache     = [];
    private $createdDnis     = [];
    private $processedDnis   = [];
    private $processedEmails = [];
    private $batchInsert     = [];
    private $batchUpdate     = [];
    private $batchSize       = 500;
    private $currentUser;
    private $importedCount   = 0;
    private $errors          = [];
    private $warnings        = [];
    private $withChanges     = [];
    private $processedRows   = 0;

    // ✅ DNIs que se agregaron a una nueva área (para registro en pivot post-import)
    private $newAreaAttachments = []; // ['dni' => X, 'area_id' => Y]

    private bool  $analyzeOnly   = false;
    private array $dniesToUpdate = [];

    public function __construct(bool $analyzeOnly = false, array $dniesToUpdate = [])
    {
        $this->analyzeOnly   = $analyzeOnly;
        $this->dniesToUpdate = $dniesToUpdate;
        $this->currentUser   = Auth::user();

        $this->areasCache = Area::all()->keyBy(function ($area) {
            return $this->normalize($area->nombre);
        });
        $this->personsCache = Person::all()->keyBy('dni');
        $this->emailsCache  = Person::all()->mapWithKeys(function ($person) {
            return [$this->normalizeEmail($person->email) => $person->dni];
        })->toArray();
    }

    public function collection(Collection $rows)
    {
        Log::info('=== INICIANDO ' . ($this->analyzeOnly ? 'ANÁLISIS' : 'IMPORTACIÓN') . ' DE PERSONAS ===');
        Log::info('Total de filas en el archivo: ' . $rows->count());

        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;
            $this->processedRows++;

            $rowArray = $row->toArray();

            if ($this->isEmptyRow($rowArray)) {
                continue;
            }

            if ($this->isInfoRow($rowArray)) {
                continue;
            }

            $this->processRow($rowArray, $rowNumber);
        }

        if (!$this->analyzeOnly) {
            $this->flushBatch();
        }

        Log::info("=== RESUMEN ===");
        Log::info("Filas procesadas: {$this->processedRows}");
        Log::info("Nuevas: {$this->importedCount}");
        Log::info("Sin cambios: " . count($this->warnings));
        Log::info("Con cambios: " . count($this->withChanges));
        Log::info("Errores: " . count($this->errors));
    }

    private function isEmptyRow($row)
    {
        foreach ($row as $value) {
            if (!empty(trim($value ?? ''))) return false;
        }
        return true;
    }

    private function isInfoRow($row)
    {
        $values    = array_values($row);
        $firstCell = trim((string) ($values[0] ?? ''));

        $notaPrefixes = ['⚠', 'NOTA', 'Áreas disponibles', 'Curso:', 'Área:'];
        foreach ($notaPrefixes as $prefix) {
            if (str_starts_with($firstCell, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function processRow($row, $rowNumber)
    {
        try {
            $dniRaw     = $this->extractField($row, ['dni', 'documento', 'cedula', 'identificacion']);
            $dni        = $this->normalizeDni($dniRaw);
            $apellido   = $this->extractField($row, ['apellido', 'lastname', 'surname', 'apellidos']);
            $nombre     = $this->extractField($row, ['nombre', 'name', 'firstname', 'nombres']);
            $titulo     = $this->extractField($row, ['titulo', 'title', 'cargo', 'profesion']);
            $domicilio  = $this->extractField($row, ['domicilio', 'direccion', 'address']);
            $telefono   = $this->extractField($row, ['telefono', 'phone', 'celular', 'contacto', 'teléfono']);
            $emailRaw   = $this->extractField($row, ['email', 'correo', 'mail', 'e-mail']);
            $email      = $this->normalizeEmail($emailRaw);
            $areaNombre = $this->extractField($row, [
                'area_asignada', 'area asignada', 'areaasignada',
                'area', 'departamento', 'sector', 'área'
            ]);

            if (empty($dni)) {
                $this->errors[] = [
                    'fila'    => $rowNumber,
                    'errores' => ['El DNI es obligatorio. El valor ingresado "' . trim($dniRaw) . '" no es un DNI válido (solo se permiten números).'],
                    'valores' => [$dniRaw, $apellido, $nombre, $email],
                ];
                return;
            }

            if (isset($this->processedDnis[$dni])) {
                $this->errors[] = ['fila' => $rowNumber, 'errores' => ["DNI duplicado dentro del mismo archivo Excel"], 'valores' => [$dni, $email]];
                return;
            }

            if (isset($this->processedEmails[$email])) {
                $this->errors[] = ['fila' => $rowNumber, 'errores' => ["Email duplicado dentro del mismo archivo Excel"], 'valores' => [$dni, $email]];
                return;
            }

            if (isset($this->emailsCache[$email]) && $this->emailsCache[$email] !== $dni) {
                $this->errors[] = ['fila' => $rowNumber, 'errores' => ["El email '{$email}' ya pertenece a otro DNI ({$this->emailsCache[$email]}) en el sistema"], 'valores' => [$dni, $email, $apellido, $nombre]];
                return;
            }

            $personByEmail = Person::where('email', $email)->where('dni', '!=', $dni)->first();
            if ($personByEmail) {
                $this->errors[] = ['fila' => $rowNumber, 'errores' => ["El email '{$email}' ya está asignado a otra persona (DNI: {$personByEmail->dni})"], 'valores' => [$dni, $email, $apellido, $nombre]];
                $this->emailsCache[$email] = $personByEmail->dni;
                return;
            }

            $this->processedDnis[$dni]     = true;
            $this->processedEmails[$email] = true;

            $missingFields = [];
            if (empty($dni))        $missingFields[] = 'DNI';
            if (empty($apellido))   $missingFields[] = 'Apellido';
            if (empty($nombre))     $missingFields[] = 'Nombre';
            if (empty($email))      $missingFields[] = 'Email';
            if (empty($areaNombre)) $missingFields[] = 'Área Asignada';

            if (!empty($missingFields)) {
                $this->errors[] = ['fila' => $rowNumber, 'errores' => ["Campos obligatorios faltantes: " . implode(', ', $missingFields)], 'valores' => [$dni, $apellido, $nombre, $email]];
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->errors[] = [
                    'fila'    => $rowNumber,
                    'errores' => ["El email '{$email}' no tiene un formato válido (ejemplo correcto: nombre@dominio.com)"],
                    'valores' => [$dni, $apellido, $nombre, $email],
                ];
                return;
            }

            $areaKey = $this->normalize($areaNombre);
            $area    = $this->areasCache[$areaKey] ?? null;

            if (!$area) {
                $areasDisponibles = collect($this->areasCache)->pluck('nombre')->implode(', ');
                $this->errors[] = ['fila' => $rowNumber, 'errores' => ["El área '{$areaNombre}' no existe en el sistema. Áreas disponibles: " . $areasDisponibles], 'valores' => [$dni, $apellido, $nombre, $areaNombre]];
                return;
            }

            if ($this->currentUser->role->name === 'Administrador' && $this->currentUser->area_id != $area->id) {
                $this->errors[] = ['fila' => $rowNumber, 'errores' => ["No tenés permisos para asignar personas al área '{$areaNombre}'"], 'valores' => [$dni, $apellido, $nombre, $areaNombre]];
                return;
            }

            $existingPerson = $this->personsCache[$dni] ?? null;

            $data = [
                'dni'       => $dni,
                'apellido'  => $apellido,
                'nombre'    => $nombre,
                'titulo'    => $titulo ?: null,
                'domicilio' => $domicilio ?: null,
                'telefono'  => $telefono ?: null,
                'email'     => $email,
                'area_id'   => $area->id,
            ];

            if ($existingPerson) {

                // ✅ NUEVO: verificar si ya está en esta área via pivot
                $yaEnEstaArea = \DB::table('area_person')
                    ->where('person_id', $existingPerson->id)
                    ->where('area_id', $area->id)
                    ->exists();

                if ($yaEnEstaArea) {
                    // ✅ Ya está en esta área — detectar cambios normalmente
                    $cambios = $this->detectChanges($existingPerson, $data, $area->nombre ?? $areaNombre);

                    if (empty($cambios)) {
                        $this->warnings[] = [
                            'fila'     => $rowNumber,
                            'dni'      => $dni,
                            'nombre'   => $nombre,
                            'apellido' => $apellido,
                            'mensaje'  => "La persona con DNI {$dni} ({$nombre} {$apellido}) ya existe en esta área y no tiene cambios.",
                            'valores'  => [$dni, $nombre, $apellido, $email],
                        ];
                    } else {
                        $this->withChanges[] = [
                            'fila'         => $rowNumber,
                            'dni'          => $dni,
                            'nombre'       => $nombre,
                            'apellido'     => $apellido,
                            'cambios'      => $cambios,
                            'datos_nuevos' => $data,
                            'mensaje'      => "La persona con DNI {$dni} ({$nombre} {$apellido}) ya existe con datos diferentes.",
                            'valores'      => [$dni, $nombre, $apellido, $email],
                        ];

                        if (!$this->analyzeOnly && in_array($dni, $this->dniesToUpdate)) {
                            $this->updateExistingPerson($existingPerson, $data, $rowNumber);
                        }
                    }
                } else {
                    // ✅ NUEVO: persona existe pero NO está en esta área — agregar al área
                    if ($this->analyzeOnly) {
                        // En modo análisis: mostrar como "nueva en esta área"
                        $this->importedCount++;
                        Log::info("Análisis: persona DNI {$dni} se agregará al área '{$areaNombre}'");
                    } else {
                        // En modo importación: registrar en pivot
                        $this->newAreaAttachments[] = [
                            'person_id' => $existingPerson->id,
                            'area_id'   => $area->id,
                        ];
                        $this->importedCount++;
                        Log::info("✅ Persona DNI {$dni} agregada al área '{$areaNombre}' via pivot");
                    }
                }

            } else {
                // ✅ Persona nueva
                if ($this->analyzeOnly) {
                    $this->importedCount++;
                } else {
                    $this->createNewPerson($data, $rowNumber);
                }
            }

        } catch (\Exception $e) {
            Log::error("ERROR en fila {$rowNumber}: " . $e->getMessage());
            $this->errors[] = ['fila' => $rowNumber, 'errores' => ["Error inesperado: " . $e->getMessage()], 'valores' => array_slice($row, 0, 4)];
        }
    }

    private function detectChanges(Person $person, array $newData, string $areaNombre): array
    {
        $cambios = [];
        $campos  = [
            'apellido'  => 'Apellido',
            'nombre'    => 'Nombre',
            'titulo'    => 'Título',
            'domicilio' => 'Domicilio',
            'telefono'  => 'Teléfono',
            'email'     => 'Email',
        ];

        foreach ($campos as $field => $label) {
            $valorActual = trim((string) ($person->$field ?? ''));
            $valorNuevo  = trim((string) ($newData[$field] ?? ''));
            if ($valorActual !== $valorNuevo) {
                $cambios[] = [
                    'campo'  => $label,
                    'actual' => $valorActual ?: '(vacío)',
                    'nuevo'  => $valorNuevo ?: '(vacío)',
                ];
            }
        }

        // ✅ Ya no comparamos área porque la persona puede estar en múltiples áreas
        // El área no es un "cambio" sino una "adición"

        return $cambios;
    }

    private function extractField($row, $possibleNames)
    {
        foreach ($possibleNames as $name) {
            if (isset($row[$name]) && $row[$name] !== null && $row[$name] !== '') {
                return trim($row[$name]);
            }
        }
        foreach ($row as $key => $value) {
            $normalizedKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $key));
            foreach ($possibleNames as $name) {
                $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
                if ($normalizedKey === $normalizedName && !empty(trim($value ?? ''))) {
                    return trim($value);
                }
            }
        }
        return '';
    }

    private function normalize($text)
    {
        $text = trim($text);
        $text = strtolower($text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = preg_replace('/[^a-z0-9]/', '', $text);
        return $text;
    }

    private function normalizeEmail($email)  { return strtolower(trim($email)); }
    private function normalizeDni($dni)
    {
        $dni = trim($dni);
        $dni = strtoupper($dni); // opcional pero recomendado
        return $dni;
    }

    private function updateExistingPerson($person, $data, $rowNumber)
    {
        $oldEmailNormalized = $this->normalizeEmail($person->email);

        $this->batchUpdate[] = [
            'dni'        => $person->dni,
            'apellido'   => $data['apellido'],
            'nombre'     => $data['nombre'],
            'titulo'     => $data['titulo'] ?: null,
            'domicilio'  => $data['domicilio'] ?: null,
            'telefono'   => $data['telefono'] ?: null,
            'email'      => $data['email'],
            'area_id'    => $data['area_id'],
            'updated_at' => now(),
        ];

        $person->email = $data['email'];
        $this->personsCache[$person->dni] = $person;
        unset($this->emailsCache[$oldEmailNormalized]);
        $this->emailsCache[$this->normalizeEmail($data['email'])] = $person->dni;
        $this->importedCount++;
        $this->flushBatchIfNeeded();
    }

    private function createNewPerson($data, $rowNumber)
    {
        $this->batchInsert[] = [
            'dni'        => $data['dni'],
            'apellido'   => $data['apellido'],
            'nombre'     => $data['nombre'],
            'titulo'     => $data['titulo'] ?: null,
            'domicilio'  => $data['domicilio'] ?: null,
            'telefono'   => $data['telefono'] ?: null,
            'email'      => $data['email'],
            'area_id'    => $data['area_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $fakePerson = new Person($data);
        $this->personsCache[$data['dni']] = $fakePerson;
        $this->emailsCache[$this->normalizeEmail($data['email'])] = $data['dni'];
        $this->createdDnis[] = $data['dni'];
        $this->importedCount++;
        $this->flushBatchIfNeeded();
    }

    private function flushBatchIfNeeded()
    {
        if (count($this->batchInsert) + count($this->batchUpdate) >= $this->batchSize) {
            $this->flushBatch();
        }
    }

    private function flushBatch()
    {
        if (!empty($this->batchInsert)) {
            \DB::table('persons')->insert($this->batchInsert);
            $dnis            = collect($this->batchInsert)->pluck('dni')->toArray();
            $insertedPersons = Person::whereIn('dni', $dnis)->get();
            foreach ($insertedPersons as $person) {
                $this->personsCache[$person->dni] = $person;
                $this->emailsCache[$this->normalizeEmail($person->email)] = $person->dni;
            }
            Log::info("✅ Batch INSERT: " . count($this->batchInsert) . " personas nuevas");
            $this->batchInsert = [];
        }

        if (!empty($this->batchUpdate)) {
            \DB::table('persons')->upsert(
                $this->batchUpdate,
                ['dni'],
                ['apellido', 'nombre', 'titulo', 'domicilio', 'telefono', 'email', 'area_id', 'updated_at']
            );
            $dnis           = collect($this->batchUpdate)->pluck('dni')->toArray();
            $updatedPersons = Person::whereIn('dni', $dnis)->get();
            foreach ($updatedPersons as $person) {
                $this->personsCache[$person->dni] = $person;
                $this->emailsCache[$this->normalizeEmail($person->email)] = $person->dni;
            }
            Log::info("✅ Batch UPDATE: " . count($this->batchUpdate) . " personas actualizadas");
            $this->batchUpdate = [];
        }

        // ✅ NUEVO: registrar en pivot las personas que se agregaron a una nueva área
        if (!empty($this->newAreaAttachments)) {
            foreach ($this->newAreaAttachments as $attachment) {
                \DB::table('area_person')->insertOrIgnore([
                    'person_id'  => $attachment['person_id'],
                    'area_id'    => $attachment['area_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            Log::info("✅ Pivot area_person: " . count($this->newAreaAttachments) . " relaciones nuevas registradas");
            $this->newAreaAttachments = [];
        }
    }

    public function forceFinalFlush() { $this->flushBatch(); }

    public function onImportCompleted()
    {
        Log::info('=== CREACIÓN DE USUARIOS PARA PERSONAS NUEVAS ===');
        if (empty($this->createdDnis)) { Log::info('No hay personas nuevas.'); return; }

        $personaRole = Role::where('name', 'Persona')->first();
        if (!$personaRole) { Log::error('Rol Persona no encontrado'); return; }

        $persons = Person::whereIn('dni', $this->createdDnis)->get();

        foreach ($persons as $person) {
            try {
                if ($person->user_id) continue;

                $existingUser = User::where('email', $person->email)->first();
                if ($existingUser) {
                    $this->errors[] = [
                        'fila'    => 'post-import',
                        'errores' => [
                            "El email {$person->email} ya está registrado como usuario del sistema.",
                            "La persona fue creada pero NO se le asignó un usuario."
                        ],
                        'valores' => [$person->dni, $person->email, $person->nombre, $person->apellido],
                    ];
                    continue;
                }

                $user = User::create([
                    'name'     => $person->nombre . ' ' . $person->apellido,
                    'email'    => $person->email,
                    'password' => Hash::make($person->dni),
                    'role_id'  => $personaRole->id,
                    'area_id'  => $person->area_id,
                ]);
                $person->user_id = $user->id;
                $person->save();

                // ✅ NUEVO: registrar en pivot para personas nuevas también
                \DB::table('area_person')->insertOrIgnore([
                    'person_id'  => $person->id,
                    'area_id'    => $person->area_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info("✅ Usuario creado para {$person->email}");

            } catch (\Exception $e) {
                Log::error("❌ Error creando usuario para {$person->email}: " . $e->getMessage());
                $this->errors[] = [
                    'fila'    => 'post-import',
                    'errores' => [
                        "Error al crear usuario: " . $e->getMessage(),
                        "La persona fue creada pero NO se le asignó un usuario."
                    ],
                    'valores' => [$person->dni, $person->email, $person->nombre, $person->apellido],
                ];
            }
        }
    }

    public function getImportedCount() { return $this->importedCount; }
    public function getErrors()        { return $this->errors; }
    public function getWarnings()      { return $this->warnings; }
    public function getWithChanges()   { return $this->withChanges; }
    public function getTotalRows()     { return $this->processedRows; }
}