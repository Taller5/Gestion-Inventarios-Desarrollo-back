<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Unit extends Model
{
    use HasFactory;

    protected $table = 'units';

    protected $fillable = [
        'unidMedida', // código corto ej: g, kg, und
        'descripcion', // descripción larga ej: Gramos
    ];

    public $timestamps = true;

    // Hacemos que se agregue automáticamente un campo combinado al serializar
    protected $appends = ['label'];

    /**
     * Devuelve etiqueta combinada: "g - Gramos".
     */
    public function getLabelAttribute(): string
    {
        // Normalizamos capitalización de la descripción (primera letra mayúscula)
        $desc = ucfirst($this->descripcion ?? '');
        return trim($this->unidMedida.' - '.$desc);
    }

    // Relación inversa con productos (si luego quieres acceder)
    public function products()
    {
        return $this->hasMany(Product::class, 'unit_id');
    }
}
