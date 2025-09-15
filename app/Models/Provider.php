<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Provider extends Model
{
    protected $fillable = [
        'name',
        'contact',
        'email',
        'phone',
        'state',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_provider');
    }
}