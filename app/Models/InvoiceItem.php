<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_id',
        'codigo_producto',
        'descripcion',
        'codigo_cabys',
        'unidad_medida',
        'impuesto_porcentaje',
        'cantidad',
        'precio_unitario',
        'descuento_pct',
        'subtotal_linea',
        'impuesto_monto',
        'total_linea',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
