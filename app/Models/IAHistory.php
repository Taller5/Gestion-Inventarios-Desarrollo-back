<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IAHistory extends Model
{
    protected $table = 'ia_history';

    protected $fillable = [
        'user_id',
        'type',
        'product_id',
        'future_price',
        'promotion_active',
        'history',
    ];

    protected $casts = [
        'history' => 'array',
        'promotion_active' => 'integer',
        'future_price' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
