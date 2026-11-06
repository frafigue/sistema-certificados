<?php

use App\Imports\PersonsImport;
use App\Imports\Sheets\PersonsSheetImport;
use App\Models\Area;
use App\Models\Person;
use App\Models\Role;
use App\Models\Subarea;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
|--------------------------------------------------------------------------
| Alcance en importaciones de personas
|--------------------------------------------------------------------------
|
| Las pantallas de importación están disponibles para Root, Administrador
| y Gestor. Acá se verifica que la planilla no permita saltear el alcance
| por área o subárea definido en el resto del sistema.
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

    $this->gestorA = User::create([
        'name' => 'Gestor A',
        'email' => 'gestor.a@example.test',
        'password' => 'password',
        'role_id' => $roleId('Gestor'),
        'area_id' => $this->areaA->id,
        'subarea_id' => $this->subareaA1->id,
        'activo' => true,
    ]);

    $this->gestorB = User::create([
        'name' => 'Gestor B',
        'email' => 'gestor.b@example.test',
        'password' => 'password',
        'role_id' => $roleId('Gestor'),
        'area_id' => $this->areaB->id,
        'subarea_id' => $this->subareaB1->id,
        'activo' => true,
    ]);

    $this->subareaA1->update(['gestor_id' => $this->gestorA->id]);
});

function filaPersona(string $dni, string $area, string $nombre = 'Nombre'): array
{
    return [
        'dni' => $dni,
        'apellido' => 'Apellido',
        'nombre' => $nombre,
        'email' => 'persona'.$dni.'@example.test',
        'area_asignada' => $area,
    ];
}

test('un gestor no puede importar personas a otra area', function () {
    $this->actingAs($this->gestorA);

    $import = new PersonsImport;
    $import->collection(collect([
        collect(filaPersona('30000001', 'Area B')),
    ]));

    expect($import->getErrors())->not->toBeEmpty();
    expect(Person::where('dni', '30000001')->exists())->toBeFalse();
});

test('un gestor importa personas nuevas en su area y subarea', function () {
    $this->actingAs($this->gestorA);

    $import = new PersonsImport;
    $import->collection(collect([
        collect(filaPersona('30000002', 'Area A')),
    ]));
    $import->forceFinalFlush();
    $import->onImportCompleted();

    $persona = Person::where('dni', '30000002')->first();

    expect($persona)->not->toBeNull();
    expect((int) $persona->area_id)->toBe($this->areaA->id);
    expect((int) $persona->subarea_id)->toBe($this->subareaA1->id);
    expect($persona->areas()->where('areas.id', $this->areaA->id)->exists())->toBeTrue();
});

test('un gestor no puede actualizar personas de otra subarea por planilla', function () {
    $otra = Person::create([
        'dni' => '30000003',
        'apellido' => 'Original',
        'nombre' => 'Persona',
        'email' => 'otra@example.test',
        'area_id' => $this->areaA->id,
        'subarea_id' => $this->subareaA2->id,
    ]);
    $otra->areas()->attach($this->areaA->id);

    $this->actingAs($this->gestorA);

    $import = new PersonsImport(analyzeOnly: false, dniesToUpdate: ['30000003']);
    $import->collection(collect([
        collect(filaPersona('30000003', 'Area A', 'Cambiado')),
    ]));

    expect($import->getErrors())->not->toBeEmpty();
    expect($otra->fresh()->nombre)->toBe('Persona');
});

test('la carga masiva unificada tambien respeta el area del gestor', function () {
    $this->actingAs($this->gestorA);

    $import = new PersonsSheetImport;
    $import->collection(collect([
        collect(filaPersona('30000004', 'Area B')),
    ]));

    expect($import->getErrors())->not->toBeEmpty();
    expect(Person::where('dni', '30000004')->exists())->toBeFalse();
});

test('la carga masiva unificada asigna area subarea y pivote al gestor', function () {
    $this->actingAs($this->gestorA);

    $import = new PersonsSheetImport;
    $import->collection(collect([
        collect(filaPersona('30000005', 'Area A')),
    ]));
    $import->forceFinalFlush();
    $import->onImportCompleted();

    $persona = Person::where('dni', '30000005')->first();

    expect($persona)->not->toBeNull();
    expect((int) $persona->area_id)->toBe($this->areaA->id);
    expect((int) $persona->subarea_id)->toBe($this->subareaA1->id);
    expect($persona->areas()->where('areas.id', $this->areaA->id)->exists())->toBeTrue();
});
