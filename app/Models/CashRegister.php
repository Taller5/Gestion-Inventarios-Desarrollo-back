<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashRegister extends Model
{
    use HasFactory;

    protected $table = 'cash_registers';

    protected $fillable = [
        'sucursal_id',
        'user_id',
        'opening_amount',
        'closing_amount',
        'opened_at',
        'closed_at',
    ];

    protected $dates = [
        'opened_at',
        'closed_at',
        'created_at',
        'updated_at',
    ];

    // Relación con Branch
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'sucursal_id', 'sucursal_id');
    }

    // Relación con User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
