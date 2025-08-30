<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $table = 'branches';
    protected $primaryKey = 'sucursal_id';
    protected $fillable = [
        'negocio_id',
        'nombre',
        'provincia',
        'canton',
        'telefono',
    ];

    /**
     * Get the business that owns the branch.
     */
    public function business()
    {
        return $this->belongsTo(Business::class, 'negocio_id', 'negocio_id');
    }

    /**
     * Get the warehouses for the branch.
     */
    public function warehouses()
    {
        return $this->hasMany(Warehouse::class, 'sucursal_id', 'sucursal_id');
    }
}
