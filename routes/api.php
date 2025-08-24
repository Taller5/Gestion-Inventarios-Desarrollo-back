<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('employees', [UserController::class, 'index']);
    Route::put('employees/{id}/password', [UserController::class, 'updatePassword']);
    Route::post('employees/{id}/profile-photo', [UserController::class, 'updateProfilePhoto']);
    Route::get('employees/{id}', [UserController::class, 'show']);
    Route::post('employees', [UserController::class, 'store']);
    Route::put('employees/{id}', [UserController::class, 'update']);
    Route::delete('employees/{id}', [UserController::class, 'destroy']); 
    Route::post('employees/recover-password', [UserController::class, 'recoverPassword']);

});

Route::post('/login', [AuthController::class, 'login']);