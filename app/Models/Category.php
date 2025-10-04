<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory;

    protected $table = 'category'; // nombre real de la tabla
    protected $primaryKey = 'nombre'; // PK es 'nombre'
    public $incrementing = false; // no autoincrementable
    protected $keyType = 'string'; // tipo string para PK

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'categoria', 'nombre');
    }
}
