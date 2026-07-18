<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A workspace/tenant. Every business row belongs to a company. A super-admin owns
 * all companies; normal users belong to exactly one.
 */
class Company extends Model
{
    protected $fillable = ['name', 'tax_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    /** This company's settings row (created on demand) */
    public function settings()
    {
        return $this->hasOne(Setting::class);
    }
}
