<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\ResolutionController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PersonaDashboardController;
use App\Http\Controllers\PersonProfileController;
use App\Http\Controllers\ConsultaPublicaController;
use App\Http\Controllers\UnifiedImportController;
use App\Http\Controllers\CertificateExcelGeneratorController;

/*
|--------------------------------------------------------------------------
| RUTAS PÚBLICAS
|--------------------------------------------------------------------------
*/

Route::get('/', [ConsultaPublicaController::class, 'mostrarFormulario'])->name('consulta.home');
Route::post('/', [ConsultaPublicaController::class, 'buscarCertificados'])->name('certificados.buscar');
Route::get('/certificados/resultado', [ConsultaPublicaController::class, 'mostrarResultados'])->name('certificados.resultado');
Route::get('/certificados/publico/descargar/{id}', [ConsultaPublicaController::class, 'descargarCertificado'])->name('certificados.descargar.publico');
Route::get('/certificates/verify/{unique_code}', [CertificateController::class, 'verify'])->name('certificates.verify');
Route::get('/api/certificados/buscar', [ConsultaPublicaController::class, 'buscarAjax'])->name('certificados.buscar.ajax');
Route::get('/search-persons-by-course/{course}', [CertificateController::class, 'searchPersonsByCourse'])->name('courses.search_persons');
Route::get('/welcome', function () {
    return redirect()->route('consulta.home');
});

/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/dashboard', function () {
        return redirect()->route('home');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/mi-dashboard', [PersonaDashboardController::class, 'index'])->name('persona.dashboard');

    /*
    |--------------------------------------------------------------------------
    | PERSONAS
    |--------------------------------------------------------------------------
    */

    Route::get('persons/import', [PersonController::class, 'showImportForm'])->name('persons.import.form');
    Route::post('persons/import', [PersonController::class, 'import'])->name('persons.import');
    Route::get('persons/import/preview', [PersonController::class, 'importPreview'])->name('persons.import.preview');
    Route::post('persons/import/confirm', [PersonController::class, 'confirmImport'])->name('persons.import.confirm');
    Route::get('persons/download-template', [PersonController::class, 'downloadTemplate'])->name('persons.download.template');
    Route::delete('persons/bulk-delete', [PersonController::class, 'bulkDestroy'])->name('persons.bulkDelete');
    Route::resource('persons', PersonController::class);

    Route::get('/mi-perfil', [PersonProfileController::class, 'edit'])->name('persona.profile.edit');
    Route::put('/mi-perfil', [PersonProfileController::class, 'update'])->name('persona.profile.update');

    /*
    |--------------------------------------------------------------------------
    | CARGA MASIVA UNIFICADA
    |--------------------------------------------------------------------------
    */

    Route::prefix('carga-masiva')->name('unified-import.')->group(function () {
        Route::get('/', [UnifiedImportController::class, 'showForm'])->name('form');
        Route::post('/procesar', [UnifiedImportController::class, 'import'])->name('import');
        Route::get('/plantilla', [UnifiedImportController::class, 'downloadTemplate'])->name('template');
    });

    /*
    |--------------------------------------------------------------------------
    | GENERACIÓN MASIVA DE CERTIFICADOS
    |--------------------------------------------------------------------------
    */

    Route::prefix('certificate-generator')->name('certificate-generator.')->group(function () {
        Route::get('/', [CertificateExcelGeneratorController::class, 'showForm'])->name('form');
        Route::post('/generate', [CertificateExcelGeneratorController::class, 'generate'])->name('generate');
        Route::get('/course-info/{course}', [CertificateExcelGeneratorController::class, 'getCourseInfo'])->name('course-info');
        Route::post('/import', [CertificateExcelGeneratorController::class, 'importCertificates'])->name('import');
    });

    /*
    |--------------------------------------------------------------------------
    | 🔥 NUEVO — BATCH STATUS (PROGRESO EN VIVO)
    |--------------------------------------------------------------------------
    */

    Route::get('/batch-status/{id}', [CertificateExcelGeneratorController::class, 'batchStatus'])
        ->name('batch.status');

    /*
    |--------------------------------------------------------------------------
    | ÁREAS, RESOLUCIONES, CURSOS
    |--------------------------------------------------------------------------
    */

    Route::get('/areas/{area}/design', [AreaController::class, 'designTemplate'])->name('areas.design');
    Route::post('/areas/{area}/save-template', [AreaController::class, 'saveTemplate'])->name('areas.design.save');
    Route::post('/areas/{area}/upload-background', [AreaController::class, 'uploadBackground'])->name('areas.upload.background');
    Route::get('/areas/{area}/preview', [AreaController::class, 'previewTemplate'])->name('areas.preview');
    Route::post('/areas/{area}/preview-live', [AreaController::class, 'previewFromDesign'])->name('areas.preview.live');
    Route::delete('/areas/{area}/clear-template', [AreaController::class, 'clearTemplate'])->name('areas.clear.template');
    Route::resource('resolutions', ResolutionController::class);
    Route::resource('courses', CourseController::class);
    Route::resource('areas', AreaController::class);

    /*
    |--------------------------------------------------------------------------
    | CERTIFICADOS
    |--------------------------------------------------------------------------
    */

    Route::get('my-certificates', [CertificateController::class, 'myCertificates'])->name('certificates.my');
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'downloadPdf'])->name('certificates.download');
    Route::get('certificates/import/template', [CertificateController::class, 'downloadTemplate'])->name('certificates.template.download');
    Route::get('certificates/import', [CertificateController::class, 'showImportForm'])->name('certificates.import.form');
    Route::post('certificates/import', [CertificateController::class, 'import'])->name('certificates.import');
    Route::get('certificates/import/preview', [CertificateController::class, 'showPreview'])->name('certificates.import.preview');
    Route::post('certificates/import/process', [CertificateController::class, 'processImport'])->name('certificates.import.process');
    Route::get('certificates/import/preview-pdf', [CertificateController::class, 'previewPdf'])->name('certificates.import.preview_pdf');
    Route::get('/get-area-by-course/{course}', [CertificateController::class, 'getAreaByCourse'])->name('courses.get_area');
    Route::get('/get-persons-by-course/{course}', [CertificateController::class, 'getPersonsByCourse'])->name('courses.get_persons');

    Route::post('/certificates/send-pending', [CertificateController::class, 'sendPending'])
        ->name('certificates.sendPending')
        ->middleware('can:is-admin-or-root');

    Route::resource('certificates', CertificateController::class)->except(['show']);

    /*
    |--------------------------------------------------------------------------
    | USUARIOS (solo Root)
    |--------------------------------------------------------------------------
    */

    Route::middleware('is.root')->group(function () {
        Route::resource('users', UserController::class);
        Route::patch('/users/{user}/reactivar', [UserController::class, 'reactivar'])->name('users.reactivar');
    });

});

require __DIR__ . '/auth.php';