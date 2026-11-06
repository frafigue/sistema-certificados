<?php

use App\Imports\Sheets\CertificatesSheetImport;
use App\Models\Area;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\ImportHistory;
use App\Models\Person;
use App\Models\Resolution;
use App\Models\Role;
use App\Models\Subarea;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
|--------------------------------------------------------------------------
| Alcance por registro
|--------------------------------------------------------------------------
|
| El acceso a las pantallas ya está cubierto en RouteAccessTest. Acá se
| verifica que, dentro de una pantalla permitida, cada rol solo pueda
| operar sobre los registros de su área o subárea.
|
*/

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->areaA = Area::create(['nombre' => 'Area A']);
    $this->areaB = Area::create(['nombre' => 'Area B']);

    $this->subareaA1 = Subarea::create(['area_id' => $this->areaA->id, 'nombre' => 'Subarea A1']);
    $this->subareaA2 = Subarea::create(['area_id' => $this->areaA->id, 'nombre' => 'Subarea A2']);
    $this->subareaB1 = Subarea::create(['area_id' => $this->areaB->id, 'nombre' => 'Subarea B1']);

    $roleId = fn (string $name) => Role::where('name', $name)->value('id');

    $crearUsuario = fn (string $rol, string $email, ?int $areaId = null, ?int $subareaId = null) => User::create([
        'name' => 'Usuario '.$rol,
        'email' => $email,
        'password' => 'password',
        'role_id' => $roleId($rol),
        'area_id' => $areaId,
        'subarea_id' => $subareaId,
        'activo' => true,
    ]);

    $this->root = $crearUsuario('Root', 'root@example.test');
    $this->adminA = $crearUsuario('Administrador', 'admin.a@example.test', $this->areaA->id);
    $this->adminB = $crearUsuario('Administrador', 'admin.b@example.test', $this->areaB->id);
    $this->gestorA = $crearUsuario('Gestor', 'gestor.a@example.test', $this->areaA->id, $this->subareaA1->id);
    $this->gestorB = $crearUsuario('Gestor', 'gestor.b@example.test', $this->areaB->id, $this->subareaB1->id);

    $this->subareaA1->update(['gestor_id' => $this->gestorA->id]);
    $this->subareaB1->update(['gestor_id' => $this->gestorB->id]);

    $crearPersona = function (string $dni, Area $area, Subarea $subarea): Person {
        $person = Person::create([
            'dni' => $dni,
            'apellido' => 'Apellido '.$dni,
            'nombre' => 'Nombre '.$dni,
            'email' => 'persona'.$dni.'@example.test',
            'area_id' => $area->id,
        ]);

        $person->subarea_id = $subarea->id;
        $person->save();
        $person->areas()->attach($area->id);

        return $person;
    };

    $this->personaA1 = $crearPersona('20000001', $this->areaA, $this->subareaA1);
    $this->personaA2 = $crearPersona('20000002', $this->areaA, $this->subareaA2);
    $this->personaB1 = $crearPersona('20000003', $this->areaB, $this->subareaB1);

    $crearCurso = function (string $nombre, Area $area, Subarea $subarea): Course {
        $resolution = Resolution::create([
            'numero' => 'RES-'.$subarea->id,
            'anio' => 2026,
            'area' => $area->nombre,
            'area_id' => $area->id,
            'pdf_path' => 'resolutions/test.pdf',
        ]);

        return Course::create([
            'area_id' => $area->id,
            'subarea_id' => $subarea->id,
            'resolution_id' => $resolution->id,
            'nro_curso' => 'CUR-'.$subarea->id,
            'nombre' => $nombre,
            'periodo' => '2026',
            'horas' => 40,
            'tipo_horas' => 'Reloj',
            'maxima_nota' => 10,
        ]);
    };

    $this->cursoA1 = $crearCurso('Curso A1', $this->areaA, $this->subareaA1);
    $this->cursoA2 = $crearCurso('Curso A2', $this->areaA, $this->subareaA2);
    $this->cursoB1 = $crearCurso('Curso B1', $this->areaB, $this->subareaB1);

    $crearHistorial = fn (User $user, string $batch) => ImportHistory::create([
        'user_id' => $user->id,
        'batch_id' => $batch,
        'file_name' => $batch.'.xlsx',
        'total_rows' => 1,
        'status' => 'completed',
        'started_at' => now(),
        'finished_at' => now(),
    ]);

    $this->historialAdminA = $crearHistorial($this->adminA, 'batch-admin-a');
    $this->historialGestorA = $crearHistorial($this->gestorA, 'batch-gestor-a');
    $this->historialAdminB = $crearHistorial($this->adminB, 'batch-admin-b');
});

test('administrador solo edita personas de su area', function () {
    $this->actingAs($this->adminA)
        ->get(route('persons.edit', $this->personaA2->id))
        ->assertOk();

    $this->actingAs($this->adminA)
        ->get(route('persons.edit', $this->personaB1->id))
        ->assertForbidden();
});

test('gestor solo edita personas de su subarea', function () {
    $this->actingAs($this->gestorA)
        ->get(route('persons.edit', $this->personaA1->id))
        ->assertOk();

    $this->actingAs($this->gestorA)
        ->get(route('persons.edit', $this->personaA2->id))
        ->assertForbidden();

    $this->actingAs($this->gestorA)
        ->get(route('persons.edit', $this->personaB1->id))
        ->assertForbidden();
});

test('administrador tambien ve personas sin fila en area_person', function () {
    $sinPivote = Person::create([
        'dni' => '20000099',
        'apellido' => 'Sin Pivote',
        'nombre' => 'Persona',
        'email' => 'sinpivote@example.test',
        'area_id' => $this->areaA->id,
    ]);

    $response = $this->actingAs($this->adminA)->get(route('persons.index'));
    $response->assertOk();

    $ids = collect($response->viewData('people')->items())->pluck('id');

    expect($ids)->toContain($sinPivote->id);
    expect($ids)->not->toContain($this->personaB1->id);

    $this->actingAs($this->adminA)
        ->get(route('persons.edit', $sinPivote->id))
        ->assertOk();
});

test('administrador no actualiza ni elimina personas de otra area', function () {
    $this->actingAs($this->adminA)
        ->patch(route('persons.update', $this->personaB1->id), [
            'dni' => $this->personaB1->dni,
            'apellido' => 'Cambiado',
            'nombre' => 'Cambiado',
            'email' => $this->personaB1->email,
            'area_id' => $this->areaA->id,
        ])
        ->assertForbidden();

    $this->actingAs($this->adminA)
        ->delete(route('persons.destroy', $this->personaB1->id))
        ->assertForbidden();

    expect($this->personaB1->fresh())->not->toBeNull();
    expect($this->personaB1->fresh()->apellido)->toBe('Apellido 20000003');
});

test('gestor no elimina en masa personas de otra subarea', function () {
    $this->actingAs($this->gestorA)
        ->delete(route('persons.bulkDelete'), [
            'ids' => [
                $this->personaA1->id,
                $this->personaA2->id,
                $this->personaB1->id,
            ],
        ]);

    expect(Person::find($this->personaA1->id))->toBeNull();
    expect(Person::find($this->personaA2->id))->not->toBeNull();
    expect(Person::find($this->personaB1->id))->not->toBeNull();
});

test('administrador ve solo importaciones de usuarios de su area', function () {
    $response = $this->actingAs($this->adminA)->get(route('import-history.index'));
    $response->assertOk();

    $ids = collect($response->viewData('imports')->items())->pluck('id');

    expect($ids)->toContain($this->historialAdminA->id);
    expect($ids)->toContain($this->historialGestorA->id);
    expect($ids)->not->toContain($this->historialAdminB->id);
});

test('gestor ve solo sus propias importaciones', function () {
    $response = $this->actingAs($this->gestorA)->get(route('import-history.index'));
    $response->assertOk();

    $ids = collect($response->viewData('imports')->items())->pluck('id');

    expect($ids)->toContain($this->historialGestorA->id);
    expect($ids)->not->toContain($this->historialAdminA->id);
    expect($ids)->not->toContain($this->historialAdminB->id);
});

test('gestor no accede al detalle de una importacion ajena', function () {
    $this->actingAs($this->gestorA)
        ->get(route('import-history.show', $this->historialGestorA->id))
        ->assertOk();

    $this->actingAs($this->gestorA)
        ->get(route('import-history.show', $this->historialAdminB->id))
        ->assertForbidden();
});

test('generador de certificados respeta el alcance del curso', function () {
    $this->actingAs($this->gestorA)
        ->get(route('certificate-generator.course-info', $this->cursoA1->id))
        ->assertOk();

    $this->actingAs($this->gestorA)
        ->get(route('certificate-generator.course-info', $this->cursoB1->id))
        ->assertForbidden();

    $this->actingAs($this->adminA)
        ->get(route('certificate-generator.course-info', $this->cursoB1->id))
        ->assertForbidden();
});

test('el formulario del generador lista solo los cursos permitidos', function () {
    $response = $this->actingAs($this->gestorA)->get(route('certificate-generator.form'));
    $response->assertOk();

    $cursos = collect($response->viewData('courses'))->pluck('id');

    expect($cursos)->toContain($this->cursoA1->id);
    expect($cursos)->not->toContain($this->cursoA2->id);
    expect($cursos)->not->toContain($this->cursoB1->id);
});

test('la importacion de certificados rechaza cursos fuera de alcance', function () {
    $this->actingAs($this->gestorA);

    $import = new CertificatesSheetImport;
    $import->collection(collect([
        [
            'dni' => '20000003',
            'curso' => $this->cursoB1->nombre,
            'cuv' => 'CUV-FUERA-DE-ALCANCE',
            'tipo_certificado' => 'APR',
        ],
    ]));

    expect($import->getErrors())->not->toBeEmpty();
    expect(Certificate::where('unique_code', 'CUV-FUERA-DE-ALCANCE')->exists())->toBeFalse();
});

test('gestor no genera certificados fuera de su alcance', function () {
    $payload = [
        'condition' => 'Aprobado',
        'unidad_academica' => 'Unidad',
        'subarea' => 'Subarea',
        'iniciales' => 'AB',
        'anio' => '2026',
    ];

    $this->actingAs($this->gestorA)
        ->post(route('certificates.store'), $payload + [
            'course_id' => $this->cursoB1->id,
            'person_id' => $this->personaA1->id,
        ])
        ->assertForbidden();

    $this->actingAs($this->gestorA)
        ->post(route('certificates.store'), $payload + [
            'course_id' => $this->cursoA1->id,
            'person_id' => $this->personaA2->id,
        ])
        ->assertForbidden();

    expect(Certificate::count())->toBe(0);
});

test('el listado de personas del curso respeta la subarea del gestor', function () {
    $response = $this->actingAs($this->gestorA)
        ->get(route('courses.get_persons', $this->cursoA1->id));

    $response->assertOk();

    $ids = collect($response->json('persons'))->pluck('id');

    expect($ids)->toContain($this->personaA1->id);
    expect($ids)->not->toContain($this->personaA2->id);
    expect($ids)->not->toContain($this->personaB1->id);
    expect($response->json('total_area'))->toBe(1);
});

test('un administrador no reactiva usuarios de otra area', function () {
    $this->gestorB->update(['activo' => false]);

    $this->actingAs($this->adminA)
        ->patch(route('users.reactivar', $this->gestorB->id))
        ->assertForbidden();

    expect($this->gestorB->fresh()->activo)->toBeFalse();
});

test('un administrador no asigna a su gestor una subarea de otra area', function () {
    $this->actingAs($this->adminA)
        ->from(route('users.edit', $this->gestorA->id))
        ->patch(route('users.update', $this->gestorA->id), [
            'name' => $this->gestorA->name,
            'email' => $this->gestorA->email,
            'role_id' => Role::where('name', 'Gestor')->value('id'),
            'area_id' => $this->areaA->id,
            'subarea_id' => $this->subareaB1->id,
        ])
        ->assertSessionHasErrors('subarea_id');

    expect((int) $this->gestorA->fresh()->subarea_id)->toBe($this->subareaA1->id);
});

test('la descarga de certificados usa la misma regla de alcance', function () {
    $certificadoB1 = Certificate::create([
        'course_id' => $this->cursoB1->id,
        'person_id' => $this->personaB1->id,
        'condition' => 'Aprobado',
        'unique_code' => 'CERT-B1',
    ]);

    $this->actingAs($this->gestorA)
        ->get(route('certificates.download', $certificadoB1->id))
        ->assertForbidden();
});
