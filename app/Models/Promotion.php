<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'tipo',
        'valor',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'business_id', // nuevo campo
        'branch_id',   // nuevo campo
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'activo' => 'boolean',
        'valor' => 'float',
    ];

    // relación many-to-many con pivot personalizado
    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_products')
                    ->withPivot(['cantidad','descuento'])
                    ->withTimestamps();
    }

    // relación simple con negocio
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id', 'negocio_id');
    }

    // relación simple con sucursal
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'sucursal_id');
    }
}
