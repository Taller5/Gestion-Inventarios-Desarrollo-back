<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HaciendaResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'invoice_xml_id',
        'clave',
        'estado',
        'numero_consecutivo',
        'ind_ambiente',
        'fecha_recepcion',
        'fecha_resolucion',
        'respuesta_xml',
        'detalle',
        'error_message',
    ];

    protected $casts = [
        'fecha_recepcion' => 'datetime',
        'fecha_resolucion' => 'datetime',
        'detalle' => 'array',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceXml()
    {
        return $this->belongsTo(InvoiceXml::class, 'invoice_xml_id');
    }
}
