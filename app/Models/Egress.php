<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Egress extends Model
{
    protected $fillable = [
        'codigo_producto',
        'cantidad',
        'motivo',
        'descripcion',
        'bodega_origen_id',
        'bodega_destino_id',
        'fecha'
    ];

    public function producto()
    {
        return $this->belongsTo(Product::class, 'codigo_producto', 'codigo_producto');
    }

    public function bodegaOrigen()
    {
        return $this->belongsTo(Warehouse::class, 'bodega_origen_id', 'bodega_id');
    }

    public function bodegaDestino()
    {
        return $this->belongsTo(Warehouse::class, 'bodega_destino_id', 'bodega_id');
    }
}
