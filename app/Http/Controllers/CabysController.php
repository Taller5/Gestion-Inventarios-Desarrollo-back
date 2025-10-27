<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cabys;

class CabysController extends Controller
{
    /**
     * GET /api/v1/cabys
     * Params:
     *  - q: término de búsqueda
     *  - per_page: paginación
     *  - category_main: filtra por la categoría principal (primer dígito)
     *  - with=path : agrega un arreglo path (breadcrumb)
     *  - mode=lite : sólo devuelve code + description (más liviano)
     */
    public function index()
    {
        $request = request();
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 25);
        $categoryMain = $request->query('category_main');
        $with = $request->query('with');
        $mode = $request->query('mode');

        $query = Cabys::query();

        if ($categoryMain !== null && $categoryMain !== '') {
            $query->where('category_main', $categoryMain);
        }

        if ($q) {
            $like = "%$q%";
            $query->where(function ($builder) use ($like) {
                $builder->where('code', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('category_main_name', 'like', $like)
                    ->orWhere('category_2', 'like', $like)
                    ->orWhere('category_3', 'like', $like)
                    ->orWhere('category_4', 'like', $like)
                    ->orWhere('category_5', 'like', $like)
                    ->orWhere('category_6', 'like', $like)
                    ->orWhere('category_7', 'like', $like)
                    ->orWhere('category_8', 'like', $like);
            });
        }

        // Selección de columnas si modo lite
        if ($mode === 'lite') {
            $query->select(['code', 'description', 'tax_rate']);
        }

        $paginated = $query->paginate($perPage);

        if ($with === 'path') {
            $paginated->getCollection()->transform(function ($item) {
                $item->path = $this->buildPath($item);
                return $item;
            });
        }

        return response()->json($paginated);
    }

    /**
     * GET /api/v1/cabys/search
     * Params:
     *  - q: término de búsqueda (requerido para search)
     *  - per_page: paginación (default 10)
     *  - category_main: filtra por la categoría principal
     *  - with=path : agrega un arreglo path (breadcrumb)
     *  - mode=lite : sólo devuelve code + description (más liviano)
     */
    public function search()
    {
        $request = request();
        $q = $request->query('q');
        $perPage = (int) $request->query('per_page', 10);
        $categoryMain = $request->query('category_main');
        $with = $request->query('with');
        $mode = $request->query('mode', 'lite');

        // Si no hay término de búsqueda, devolvemos 400 con mensaje
        if (!$q || trim($q) === '') {
            return response()->json([
                'message' => 'Query parameter q is required for search'
            ], 400);
        }

        $query = Cabys::query();

        if ($categoryMain !== null && $categoryMain !== '') {
            $query->where('category_main', $categoryMain);
        }

        $like = "%$q%";
        $query->where(function ($builder) use ($like) {
            $builder->where('code', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('category_main_name', 'like', $like)
                ->orWhere('category_2', 'like', $like)
                ->orWhere('category_3', 'like', $like)
                ->orWhere('category_4', 'like', $like)
                ->orWhere('category_5', 'like', $like)
                ->orWhere('category_6', 'like', $like)
                ->orWhere('category_7', 'like', $like)
                ->orWhere('category_8', 'like', $like);
        });

        if ($mode === 'lite') {
            $query->select(['code', 'description', 'tax_rate']);
        }

        $paginated = $query->paginate($perPage);

        if ($with === 'path') {
            $paginated->getCollection()->transform(function ($item) {
                $item->path = $this->buildPath($item);
                return $item;
            });
        }

        return response()->json($paginated);
    }

    // GET /api/v1/cabys/{code}?with=path
    public function show(string $code)
    {
        $with = request()->query('with');
        $item = Cabys::findOrFail($code);
        if ($with === 'path') {
            $item->path = $this->buildPath($item);
        }
        return response()->json($item);
    }

    // GET /api/v1/cabys/categories  -> lista categorías principales
    public function categories()
    {
        $rows = Cabys::select('category_main', 'category_main_name')
            ->whereNotNull('category_main')
            ->groupBy('category_main', 'category_main_name')
            ->orderBy('category_main')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->category_main,
                    'label' => trim(($r->category_main ?? '') . ' - ' . ($r->category_main_name ?? '')),
                    'name' => $r->category_main_name,
                ];
            });
        return response()->json($rows);
    }

    private function buildPath($item): array
    {
        $parts = [];
        // category_main_name es el nombre del primer nivel
        if ($item->category_main_name) { $parts[] = $item->category_main_name; }
        foreach (['category_2','category_3','category_4','category_5','category_6','category_7','category_8'] as $field) {
            if (!empty($item->{$field})) { $parts[] = $item->{$field}; }
        }
        return $parts;
    }
}
