<?php

use App\Models\Area;
use App\Models\Person;
use App\Models\Role;
use App\Models\Subarea;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/*
|--------------------------------------------------------------------------
| Matriz de autorización por rol
|--------------------------------------------------------------------------
|
| Estas pruebas fijan el comportamiento esperado de acceso a las pantallas
| principales. Si un cambio futuro abre una ruta a un rol que no corresponde,
| la prueba falla antes de llegar a producción.
|
*/

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->areaPropia = Area::create(['nombre' => 'Area Propia']);
    $this->areaAjena = Area::create(['nombre' => 'Area Ajena']);

    $this->subareaPropia = Subarea::create([
        'area_id' => $this->areaPropia->id,
        'nombre' => 'Subarea Propia',
    ]);
    $this->subareaAjena = Subarea::create([
        'area_id' => $this->areaAjena->id,
        'nombre' => 'Subarea Ajena',
    ]);

    $roleId = fn (string $name) => Role::where('name', $name)->value('id');

    $crearUsuario = function (string $rol, ?int $areaId = null, ?int $subareaId = null) use ($roleId): User {
        return User::create([
            'name' => 'Usuario '.$rol,
            'email' => strtolower($rol).'@example.test',
            'password' => 'password',
            'role_id' => $roleId($rol),
            'area_id' => $areaId,
            'subarea_id' => $subareaId,
            'activo' => true,
        ]);
    };

    $this->root = $crearUsuario('Root');
    $this->admin = $crearUsuario('Administrador', $this->areaPropia->id);
    $this->gestor = $crearUsuario('Gestor', $this->areaPropia->id, $this->subareaPropia->id);
    $this->persona = $crearUsuario('Persona', $this->areaPropia->id, $this->subareaPropia->id);

    Person::create([
        'dni' => '99000001',
        'apellido' => 'Prueba',
        'nombre' => 'Persona',
        'email' => 'persona@example.test',
        'area_id' => $this->areaPropia->id,
        'user_id' => $this->persona->id,
    ]);

    $this->subareaPropia->update(['gestor_id' => $this->gestor->id]);
});

function estadoDeRutas($test, User $user, array $rutas): array
{
    $estados = [];

    foreach ($rutas as $ruta) {
        $estados[$ruta] = $test->actingAs($user)->get($ruta)->getStatusCode();
    }

    return $estados;
}

test('un invitado es redirigido al login', function () {
    $rutas = [
        '/home',
        '/mi-dashboard',
        '/persons',
        '/courses',
        '/certificates',
        '/resolutions',
        '/subareas',
        '/areas',
        '/users',
        '/import-history',
        '/carga-masiva',
        '/certificate-generator',
        '/templates',
    ];

    foreach ($rutas as $ruta) {
        $this->get($ruta)->assertRedirect('/login');
    }
});

test('persona solo accede a su panel, su perfil y sus certificados', function () {
    $esperado = [
        '/home' => 302,
        '/mi-dashboard' => 200,
        '/mi-perfil' => 200,
        '/my-certificates' => 200,
        '/profile' => 200,
        '/persons' => 403,
        '/courses' => 403,
        '/certificates' => 403,
        '/resolutions' => 403,
        '/subareas' => 403,
        '/areas' => 403,
        '/users' => 403,
        '/import-history' => 403,
        '/carga-masiva' => 403,
        '/certificate-generator' => 403,
        '/templates' => 403,
    ];

    expect(estadoDeRutas($this, $this->persona, array_keys($esperado)))->toBe($esperado);
});

test('gestor accede a la gestion de su subarea pero no a areas ni usuarios', function () {
    $esperado = [
        '/home' => 200,
        '/persons' => 200,
        '/courses' => 200,
        '/certificates' => 200,
        '/resolutions' => 200,
        '/subareas' => 200,
        '/subareas/create' => 403,
        '/import-history' => 200,
        '/carga-masiva' => 200,
        '/certificate-generator' => 200,
        '/templates' => 200,
        '/areas' => 403,
        '/users' => 403,
    ];

    expect(estadoDeRutas($this, $this->gestor, array_keys($esperado)))->toBe($esperado);
});

test('administrador accede a la gestion de su area pero no crea areas', function () {
    $esperado = [
        '/home' => 200,
        '/persons' => 200,
        '/courses' => 200,
        '/certificates' => 200,
        '/resolutions' => 200,
        '/subareas' => 200,
        '/subareas/create' => 200,
        '/import-history' => 200,
        '/carga-masiva' => 200,
        '/certificate-generator' => 200,
        '/templates' => 200,
        '/areas' => 200,
        '/areas/create' => 403,
        '/users' => 200,
    ];

    expect(estadoDeRutas($this, $this->admin, array_keys($esperado)))->toBe($esperado);
});

test('root accede a todo el sistema', function () {
    $esperado = [
        '/home' => 200,
        '/persons' => 200,
        '/courses' => 200,
        '/certificates' => 200,
        '/resolutions' => 200,
        '/subareas' => 200,
        '/subareas/create' => 200,
        '/import-history' => 200,
        '/carga-masiva' => 200,
        '/certificate-generator' => 200,
        '/templates' => 200,
        '/areas' => 200,
        '/areas/create' => 200,
        '/users' => 200,
    ];

    expect(estadoDeRutas($this, $this->root, array_keys($esperado)))->toBe($esperado);
});
