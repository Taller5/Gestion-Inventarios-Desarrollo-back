<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'id';
    protected $fillable = [
        'codigo_producto', 
        'nombre_producto', 
        'categoria', 
        'codigo_cabys', 
        'impuesto', 
        'unit_id', 
        'descripcion', 
        'stock', 
        'precio_compra', 
        'precio_venta', 
        'bodega_id'];

    public function lotes(): HasMany
    {
        return $this->hasMany(Batch::class, 'codigo_producto', 'codigo_producto');
    }

    public function bodega(): BelongsTo
    {
    return $this->belongsTo(Warehouse::class, 'bodega_id', 'bodega_id');
    }

     public function providers()
    {
        return $this->belongsToMany(Provider::class, 'product_provider');
    }
    
     public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
