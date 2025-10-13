<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        // Customer info
        'customer_name',
        'customer_identity_number',
        'customer_id_type',

        // Branch / Business info
        'branch_name',
        'business_name',
        'business_legal_name',
        'branches_phone',
        'business_phone',
        'business_email',
        'province',
        'canton',
        'business_id_type',
        'business_id_number',

        // Cashier and date
        'cashier_name',
        'date',
        'document_type',

        // Products and totals
        'products', // JSON
        'subtotal',
        'total_discount',
        'taxes',
        'total',
        'amount_paid',
        'change',
        'payment_method',
        'receipt',
    ];

    // Cast 'products' to array automatically
    protected $casts = [
        'products' => 'array',
        'date' => 'datetime',
    ];

     public function xmls()
    {
        return $this->hasMany(InvoiceXml::class);
    }

    public function responses()
    {
        return $this->hasMany(HaciendaResponse::class);
    }

    // Relación con items (detalle de líneas snapshot)
    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
