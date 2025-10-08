<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cabys extends Model
{
    protected $table = 'cabys';
    protected $primaryKey = 'code';
    public $incrementing = false; // PK is string
    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'description',
        'tax_rate',
        'category_main',
        'category_main_name',
        'category_2',
        'category_3',
        'category_4',
        'category_5',
        'category_6',
        'category_7',
        'category_8',
        'note_include',
        'note_exclude',
    ];

    
}
