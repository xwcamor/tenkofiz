<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Site extends Model
{
    protected $fillable = ['name', 'address', 'timezone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
