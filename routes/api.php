<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {

     // ------------------ Employees (Users) ------------------
    Route::get('employees', [UserController::class, 'index']);
    Route::put('employees/{id}/password', [UserController::class, 'updatePassword']);
    Route::post('employees/{id}/profile-photo', [UserController::class, 'updateProfilePhoto']);
    Route::get('employees/{id}', [UserController::class, 'show']);
    Route::post('employees', [UserController::class, 'store']);
    Route::put('employees/{id}', [UserController::class, 'update']);
    Route::delete('employees/{id}', [UserController::class, 'destroy']); 
    Route::post('employees/recover-password', [UserController::class, 'recoverPassword']);

     // ------------------ Customers ------------------
    Route::get('customers', [CustomerController::class, 'index']);       // Listar todos
    Route::get('customers/{id}', [CustomerController::class, 'show']);  // Mostrar uno
    Route::post('customers', [CustomerController::class, 'store']);     // Crear
    Route::put('customers/{id}', [CustomerController::class, 'update']); // Actualizar
    Route::delete('customers/{id}', [CustomerController::class, 'destroy']); // Eliminar

});

Route::post('/login', [AuthController::class, 'login']);