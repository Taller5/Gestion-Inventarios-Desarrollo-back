<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla asociada
     *
     * @var string
     */
    protected $table = 'customers';

    /**
     * Clave primaria personalizada
     *
     * @var string
     */
    protected $primaryKey = 'customer_id';

    /**
     * Campos asignables en masa
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'identity_number',
        'id_type',
        'phone',
        'email',
    ];

    /**
     * Atributos ocultos al serializar
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Atributos con casting automático
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
