<?php

use App\Models\Area;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Person;
use App\Models\Resolution;
use App\Models\Role;
use App\Models\Subarea;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Descarga de certificados: cada rol solo accede a lo que le corresponde
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');

    $this->areaA = Area::create(['nombre' => 'Area A']);
    $this->areaB = Area::create(['nombre' => 'Area B']);

    $this->subareaA = Subarea::create(['area_id' => $this->areaA->id, 'nombre' => 'Subarea A']);
    $this->subareaB = Subarea::create(['area_id' => $this->areaB->id, 'nombre' => 'Subarea B']);

    $roleId = fn (string $name) => Role::where('name', $name)->value('id');

    $crearUsuario = fn (string $rol, ?int $areaId = null, ?int $subareaId = null) => User::create([
        'name' => 'Usuario '.$rol,
        'email' => strtolower($rol).'@example.test',
        'password' => 'password',
        'role_id' => $roleId($rol),
        'area_id' => $areaId,
        'subarea_id' => $subareaId,
        'activo' => true,
    ]);

    $this->root = $crearUsuario('Root');
    $this->adminA = $crearUsuario('Administrador', $this->areaA->id);
    $this->gestorA = $crearUsuario('Gestor', $this->areaA->id, $this->subareaA->id);
    $this->personaA = User::create([
        'name' => 'Persona A',
        'email' => 'persona.a@example.test',
        'password' => 'password',
        'role_id' => $roleId('Persona'),
        'area_id' => $this->areaA->id,
        'subarea_id' => $this->subareaA->id,
        'activo' => true,
    ]);
    $this->personaB = User::create([
        'name' => 'Persona B',
        'email' => 'persona.b@example.test',
        'password' => 'password',
        'role_id' => $roleId('Persona'),
        'area_id' => $this->areaB->id,
        'subarea_id' => $this->subareaB->id,
        'activo' => true,
    ]);

    $crearResolucion = fn (Area $area) => Resolution::create([
        'numero' => 'RES-'.$area->id,
        'anio' => 2026,
        'area' => $area->nombre,
        'area_id' => $area->id,
        'pdf_path' => 'resolutions/test.pdf',
    ]);

    $crearCurso = fn (Area $area, Subarea $subarea, Resolution $resolution) => Course::create([
        'area_id' => $area->id,
        'subarea_id' => $subarea->id,
        'resolution_id' => $resolution->id,
        'nro_curso' => 'CUR-'.$subarea->id,
        'nombre' => 'Curso '.$subarea->nombre,
        'periodo' => '2026',
        'horas' => 40,
        'tipo_horas' => 'Reloj',
        'maxima_nota' => 10,
    ]);

    $this->cursoA = $crearCurso($this->areaA, $this->subareaA, $crearResolucion($this->areaA));
    $this->cursoB = $crearCurso($this->areaB, $this->subareaB, $crearResolucion($this->areaB));

    $personaA = Person::create([
        'dni' => '10000001',
        'apellido' => 'Apellido A',
        'nombre' => 'Nombre A',
        'email' => 'persona.a@example.test',
        'area_id' => $this->areaA->id,
        'user_id' => $this->personaA->id,
    ]);

    $personaB = Person::create([
        'dni' => '10000002',
        'apellido' => 'Apellido B',
        'nombre' => 'Nombre B',
        'email' => 'persona.b@example.test',
        'area_id' => $this->areaB->id,
        'user_id' => $this->personaB->id,
    ]);

    Storage::disk('public')->put('certificates/a.pdf', '%PDF-1.4 certificado A');
    Storage::disk('public')->put('certificates/b.pdf', '%PDF-1.4 certificado B');

    $this->certificadoA = Certificate::create([
        'course_id' => $this->cursoA->id,
        'person_id' => $personaA->id,
        'condition' => 'Aprobado',
        'unique_code' => 'CERT-A',
        'pdf_path' => 'certificates/a.pdf',
    ]);

    $this->certificadoB = Certificate::create([
        'course_id' => $this->cursoB->id,
        'person_id' => $personaB->id,
        'condition' => 'Aprobado',
        'unique_code' => 'CERT-B',
        'pdf_path' => 'certificates/b.pdf',
    ]);
});

test('persona solo descarga sus propios certificados', function () {
    $this->actingAs($this->personaA)
        ->get(route('certificates.download', $this->certificadoA->id))
        ->assertOk();

    $this->actingAs($this->personaA)
        ->get(route('certificates.download', $this->certificadoB->id))
        ->assertForbidden();
});

test('administrador descarga solo certificados de su area', function () {
    $this->actingAs($this->adminA)
        ->get(route('certificates.download', $this->certificadoA->id))
        ->assertOk();

    $this->actingAs($this->adminA)
        ->get(route('certificates.download', $this->certificadoB->id))
        ->assertForbidden();
});

test('gestor descarga solo certificados de su subarea', function () {
    $this->actingAs($this->gestorA)
        ->get(route('certificates.download', $this->certificadoA->id))
        ->assertOk();

    $this->actingAs($this->gestorA)
        ->get(route('certificates.download', $this->certificadoB->id))
        ->assertForbidden();
});

test('root descarga cualquier certificado', function () {
    $this->actingAs($this->root)
        ->get(route('certificates.download', $this->certificadoA->id))
        ->assertOk();

    $this->actingAs($this->root)
        ->get(route('certificates.download', $this->certificadoB->id))
        ->assertOk();
});
