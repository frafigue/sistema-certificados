<?php

namespace App\Imports\Sheets;

use App\Models\Area;
use App\Models\Person;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PersonsSheetImport implements ToCollection, WithHeadingRow
{
    private array $areasCache      = [];
    private array $personsCache    = [];
    private array $emailsCache     = [];
    private array $processedDnis   = [];
    private array $processedEmails = [];
    private array $batchInsert     = [];
    private int   $batchSize       = 500;
    private array $createdDnis     = [];
    private int   $importedCount   = 0;
    private int   $skippedCount    = 0;
    private array $errors          = [];
    private int   $processedRows   = 0;
    private $currentUser;

    public function __construct()
    {
        $this->currentUser = Auth::user();

        $this->areasCache = Area::all()->keyBy(function ($area) {
            return $this->normalize($area->nombre);
        })->toArray();

        $this->personsCache = Person::all()->keyBy('dni')->toArray();

        $this->emailsCache = Person::all()->mapWithKeys(function ($person) {
            return [$this->normalizeEmail($person->email) => $person->dni];
        })->toArray();
    }

    public function collection(Collection $rows): void
    {
        Log::info('[UnifiedImport] === PROCESANDO HOJA: Personas ===');
        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;
            $this->processedRows++;
            $rowArray = $row->toArray();

            if ($this->isEmptyRow($rowArray)) continue;

            $this->processRow($rowArray, $rowNumber);
        }

        $this->flushBatch();
        Log::info('[UnifiedImport] Personas importadas: ' . $this->importedCount);
        Log::info('[UnifiedImport] Personas salteadas:  ' . $this->skippedCount);
    }

    private function processRow(array $row, int $rowNumber): void
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
                'area', 'departamento', 'sector', 'área',
            ]);

            if (empty($dni)) {
                $this->addError($rowNumber, 'El DNI es obligatorio', [$dniRaw]);
                return;
            }

            if (isset($this->processedDnis[$dni])) {
                $this->addError($rowNumber, "DNI duplicado en el archivo: {$dni}", [$dni]);
                return;
            }

            if (!empty($email) && isset($this->processedEmails[$email])) {
                $this->addError($rowNumber, "Email duplicado en el archivo: {$email}", [$dni, $email]);
                return;
            }

            $missing = [];
            if (empty($apellido))   $missing[] = 'Apellido';
            if (empty($nombre))     $missing[] = 'Nombre';
            if (empty($email))      $missing[] = 'Email';
            if (empty($areaNombre)) $missing[] = 'Área Asignada';

            if (!empty($missing)) {
                $this->addError($rowNumber, 'Campos obligatorios faltantes: ' . implode(', ', $missing), [$dni]);
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addError($rowNumber, "Email inválido: {$email}", [$dni, $email]);
                return;
            }

            if (isset($this->emailsCache[$email]) && $this->emailsCache[$email] !== $dni) {
                $this->addError($rowNumber, "El email '{$email}' ya pertenece al DNI {$this->emailsCache[$email]}", [$dni, $email]);
                return;
            }

            $areaKey = $this->normalize($areaNombre);
            $area    = $this->areasCache[$areaKey] ?? null;

            if (!$area) {
                $disponibles = collect($this->areasCache)->pluck('nombre')->implode(', ');
                $this->addError($rowNumber, "Área '{$areaNombre}' no existe. Disponibles: {$disponibles}", [$dni, $areaNombre]);
                return;
            }

            if (
                $this->currentUser &&
                $this->currentUser->role?->name === 'Administrador' &&
                $this->currentUser->area_id != $area['id']
            ) {
                $this->addError($rowNumber, "Sin permisos para el área '{$areaNombre}'", [$dni]);
                return;
            }

            $this->processedDnis[$dni]     = true;
            $this->processedEmails[$email] = true;

            $data = [
                'dni'       => $dni,
                'apellido'  => $apellido,
                'nombre'    => $nombre,
                'titulo'    => $titulo ?: null,
                'domicilio' => $domicilio ?: null,
                'telefono'  => $telefono ?: null,
                'email'     => $email,
                'area_id'   => $area['id'],
            ];

            if (isset($this->personsCache[$dni])) {
                $this->skippedCount++;
                Log::info("[UnifiedImport] Persona existente salteada (fila {$rowNumber}): DNI {$dni}");
            } else {
                $this->createNewPerson($data);
            }

        } catch (\Exception $e) {
            Log::error("[UnifiedImport] ERROR fila {$rowNumber}: " . $e->getMessage());
            $this->addError($rowNumber, 'Error inesperado: ' . $e->getMessage(), array_slice($row, 0, 4));
        }
    }

    private function createNewPerson(array $data): void
    {
        $this->batchInsert[] = array_merge($data, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $fakePerson = (object) $data;
        $this->personsCache[$data['dni']]                          = $fakePerson;
        $this->emailsCache[$this->normalizeEmail($data['email'])]  = $data['dni'];
        $this->createdDnis[]                                        = $data['dni'];
        $this->importedCount++;

        if (count($this->batchInsert) >= $this->batchSize) {
            $this->flushBatch();
        }
    }

    public function flushBatch(): void
    {
        if (!empty($this->batchInsert)) {
            \DB::table('persons')->insert($this->batchInsert);

            $dnis = collect($this->batchInsert)->pluck('dni')->toArray();
            $inserted = Person::whereIn('dni', $dnis)->get();
            foreach ($inserted as $person) {
                $this->personsCache[$person->dni]                              = $person;
                $this->emailsCache[$this->normalizeEmail($person->email)]      = $person->dni;
            }

            Log::info('[UnifiedImport] Batch INSERT personas: ' . count($this->batchInsert));
            $this->batchInsert = [];
        }
    }

    public function forceFinalFlush(): void { $this->flushBatch(); }

    public function onImportCompleted(): void
    {
        Log::info('[UnifiedImport] === CREANDO USUARIOS ===');

        if (empty($this->createdDnis)) return;

        $personaRole = Role::where('name', 'Persona')->first();
        if (!$personaRole) {
            Log::error('[UnifiedImport] Rol "Persona" no encontrado.');
            return;
        }

        $persons = Person::whereIn('dni', $this->createdDnis)->get();

        foreach ($persons as $person) {
            try {
                if ($person->user_id) continue;

                $existingUser = User::where('email', $person->email)->first();
                if ($existingUser) {
                    $this->errors[] = [
                        'hoja'    => 'Personas',
                        'fila'    => 'post-import',
                        'errores' => ["Email {$person->email} ya registrado como usuario. Persona creada sin usuario."],
                        'valores' => [$person->dni, $person->email],
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

                Log::info("[UnifiedImport] Usuario creado: {$person->email}");

            } catch (\Exception $e) {
                $this->errors[] = [
                    'hoja'    => 'Personas',
                    'fila'    => 'post-import',
                    'errores' => ['Error al crear usuario: ' . $e->getMessage()],
                    'valores' => [$person->dni, $person->email],
                ];
            }
        }
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (!empty(trim((string) ($value ?? '')))) return false;
        }
        return true;
    }

    private function extractField(array $row, array $possibleNames): string
    {
        foreach ($possibleNames as $name) {
            if (isset($row[$name]) && $row[$name] !== null && $row[$name] !== '') {
                return trim((string) $row[$name]);
            }
        }
        foreach ($row as $key => $value) {
            $normalizedKey = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $key));
            foreach ($possibleNames as $name) {
                $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $name));
                if ($normalizedKey === $normalizedName && !empty(trim((string) ($value ?? '')))) {
                    return trim((string) $value);
                }
            }
        }
        return '';
    }

    private function normalize(string $text): string
    {
        $text = trim($text);
        $text = strtolower($text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        $text = preg_replace('/[^a-z0-9]/', '', $text);
        return $text;
    }

    private function normalizeEmail(string $email): string { return strtolower(trim($email)); }
    private function normalizeDni(string $dni): string     { return preg_replace('/[^0-9]/', '', $dni); }

    private function addError(int $rowNumber, string $message, array $values = []): void
    {
        $this->errors[] = [
            'hoja'    => 'Personas',
            'fila'    => $rowNumber,
            'errores' => [$message],
            'valores' => $values,
        ];
    }

    public function getImportedCount(): int { return $this->importedCount; }
    public function getSkippedCount(): int  { return $this->skippedCount; }
    public function getErrors(): array      { return $this->errors; }
    public function getTotalRows(): int     { return $this->processedRows; }
    public function getCreatedDnis(): array { return $this->createdDnis; }
}