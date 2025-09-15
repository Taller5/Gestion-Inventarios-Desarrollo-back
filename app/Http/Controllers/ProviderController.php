<?php
namespace App\Http\Controllers;

use App\Models\Provider;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function index()
    {
        return Provider::with('products')->get();
    }

    public function store(Request $request)
    {
        $provider = Provider::create($request->only(['name', 'contact', 'email', 'phone', 'state']));
        if ($request->has('products')) {
            $provider->products()->sync($request->products);
        }
        return $provider->load('products');
    }

    public function show($id)
    {
        return Provider::with('products')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $provider = Provider::findOrFail($id);
        $provider->update($request->only(['name', 'contact', 'email', 'phone', 'state']));
        if ($request->has('products')) {
            $provider->products()->sync($request->products);
        }
        return $provider->load('products');
    }

    public function destroy($id)
    {
        $provider = Provider::findOrFail($id);
        $provider->products()->detach();
        $provider->delete();
        return response()->json(['message' => 'Provider deleted']);
    }
}