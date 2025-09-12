<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $table = 'businesses';
    protected $primaryKey = 'negocio_id';
    protected $fillable = [
        'nombre_legal',
        'nombre_comercial',
        'tipo_identificacion',
        'numero_identificacion',
        'margen_ganancia',
        'descripcion',
        'telefono',
        'email',
    ];

    /**
     * Get the branches for the business.
     */
    public function branches()
    {
        return $this->hasMany(Branch::class, 'negocio_id', 'negocio_id');
    }
}
