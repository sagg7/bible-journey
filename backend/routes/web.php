<?php

use App\Http\Controllers\InstitutionSignupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::post('/instituciones', [InstitutionSignupController::class, 'store'])
    ->middleware('throttle:institution-signup')
    ->name('instituciones.store');

Route::get('/instituciones/gracias', function () {
    return view('instituciones-gracias');
})->name('instituciones.gracias');

Route::get('/privacy', function () {
    return view('privacy');
});

Route::get('/terms', function () {
    return view('terms');
});

// Página de solicitud de eliminación de cuenta (requisito de Google Play:
// recurso web además del flujo in-app). Declarar esta URL en Data Safety.
Route::get('/eliminar-cuenta', function () {
    return view('account-deletion');
});
Route::redirect('/delete-account', '/eliminar-cuenta');
