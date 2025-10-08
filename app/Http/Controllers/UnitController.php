<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    /**
     * Lista todas las unidades de medida.
     */
    public function index(): JsonResponse
    {
        // Al no limitar las columnas, también incluirá el accessor 'label'
        $units = Unit::orderBy('unidMedida')->get();
        return response()->json($units);
    }
}
