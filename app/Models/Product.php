<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'id';
     protected $fillable = ['codigo', 'nombre', 'stock', 'precio', 'bodega_id'];

    public function lotes(): HasMany
    {
        return $this->hasMany(Batch::class, 'codigo', 'codigo');
    }

    public function bodega(): BelongsTo
    {
    return $this->belongsTo(Warehouse::class, 'bodega_id', 'bodega_id');
    }

     public function providers()
    {
        return $this->belongsToMany(Provider::class, 'product_provider');
    }
}
