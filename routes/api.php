<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\EnumController;
use App\Http\Controllers\InvoiceController;

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

    // ------------------ Businesses ------------------
    Route::get('businesses', [BusinessController::class, 'index']);
    Route::get('businesses/{id}', [BusinessController::class, 'show']);
    Route::post('businesses', [BusinessController::class, 'store']);
    Route::put('businesses/{id}', [BusinessController::class, 'update']);
    Route::delete('businesses/{id}', [BusinessController::class, 'destroy']);

    // ------------------ Branches ------------------
    Route::get('branches', [BranchController::class, 'index']);
    Route::get('branches/{id}', [BranchController::class, 'show']);
    Route::post('branches', [BranchController::class, 'store']);
    Route::put('branches/{id}', [BranchController::class, 'update']);
    Route::delete('branches/{id}', [BranchController::class, 'destroy']);

    // ------------------ Warehouses ------------------
    Route::get('warehouses', [WarehouseController::class, 'index']);
    Route::get('warehouses/{id}', [WarehouseController::class, 'show']);
    Route::post('warehouses', [WarehouseController::class, 'store']);
    Route::put('warehouses/{id}', [WarehouseController::class, 'update']);
    Route::delete('warehouses/{id}', [WarehouseController::class, 'destroy']);

        // ------------------ Products ------------------
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{id}', [ProductController::class, 'update']);
    Route::delete('products/{id}', [ProductController::class, 'destroy']);

    // ------------------ Batch ------------------
    Route::get('batch', [BatchController::class, 'index']);
    Route::get('batch/{id}', [BatchController::class, 'show']);
    Route::post('batch', [BatchController::class, 'store']);
    Route::put('batch/{id}', [BatchController::class, 'update']);
    Route::delete('batch/{id}', [BatchController::class, 'destroy']);
     // ------------------ Invoices ------------------
    Route::get('invoices', [InvoiceController::class, 'index']);      // List all invoices
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);  // Show single invoice
    Route::post('invoices', [InvoiceController::class, 'store']);     // Create invoice
    Route::put('invoices/{id}', [InvoiceController::class, 'update']); // Update invoice if needed
    Route::delete('invoices/{id}', [InvoiceController::class, 'destroy']); // Delete invoice

});

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');