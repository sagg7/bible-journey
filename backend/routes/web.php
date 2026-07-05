<?php

use App\Http\Controllers\InstitutionSignupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::post('/instituciones', [InstitutionSignupController::class, 'store'])->name('instituciones.store');

Route::get('/instituciones/gracias', function () {
    return view('instituciones-gracias');
})->name('instituciones.gracias');

Route::get('/privacy', function () {
    return view('privacy');
});
