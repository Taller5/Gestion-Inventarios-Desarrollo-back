<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceXml extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'clave',
        'document_type',
        'schema_version',
        'xml',
        'schema_valid',
        'signature_valid',
        'validation_errors',
        'status',
        'attempts',
        'submitted_at',
        'accepted_at',
        'rejected_at',
        'error_message',
    ];

    protected $casts = [
        'schema_valid' => 'boolean',
        'signature_valid' => 'boolean',
        'submitted_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function responses()
    {
        return $this->hasMany(HaciendaResponse::class, 'invoice_xml_id');
    }
}
