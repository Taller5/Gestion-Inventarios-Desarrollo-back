<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $table = 'warehouses';
    protected $primaryKey = 'bodega_id';
    protected $fillable = [
        'sucursal_id',
        'codigo',
    ];

    /**
     * Get the branch that owns the warehouse.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'sucursal_id', 'sucursal_id');
    }
}
