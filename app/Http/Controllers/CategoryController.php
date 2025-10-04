<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(Category::with('products')->get());
    }

    public function show($nombre)
    {
        $category = Category::with('products')->findOrFail($nombre);
        return response()->json($category);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'nombre' => 'required|string|unique:category,nombre',
                'descripcion' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            // Retornar mensaje JSON claro
            return response()->json([
                'message' => 'Ya existe una categoría con ese nombre.',
                'errors' => $e->errors(),
            ], 422);
        }

        $category = Category::create($data);
        return response()->json($category, 201);
    }

    public function update(Request $request, $nombre)
    {
        $category = Category::findOrFail($nombre);

        try {
            $data = $request->validate([
                'nombre' => 'sometimes|required|string|unique:category,nombre,' . $category->nombre . ',nombre',
                'descripcion' => 'nullable|string',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Ya existe una categoría con ese nombre.',
                'errors' => $e->errors(),
            ], 422);
        }

        $category->update($data);
        return response()->json($category);
    }

    public function destroy($nombre)
    {
        $category = Category::findOrFail($nombre);
        $category->delete();
        return response()->json(null, 204);
    }
}
