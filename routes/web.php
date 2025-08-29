<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerController; // 👈 falta este use

Route::get('/', function () {
    return view('welcome');
});

Route::resource('users', UserController::class);
Route::resource('customers', CustomerController::class);
