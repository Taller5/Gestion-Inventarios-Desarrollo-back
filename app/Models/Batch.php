<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends Model
{
    protected $table = 'batch';
     protected $primaryKey = 'lote_id';
    protected $fillable = [
        'codigo', 'numero_lote', 'cantidad', 'proveedor', 'fecha_entrada',
        'fecha_salida', 'fecha_salida_lote', 'descripcion', 'nombre'
    ];

    public function producto(): BelongsTo
    {
    return $this->belongsTo(Product::class, 'codigo', 'codigo');
    }

    
}
