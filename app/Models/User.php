<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'perfil_id', 'activo', 'debe_cambiar_password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'debe_cambiar_password' => 'boolean',
        ];
    }

    public function perfil()
    {
        return $this->belongsTo(Perfil::class);
    }

    public function empleado()
    {
        return $this->hasOne(Empleado::class);
    }

    public function tienePerfil(string ...$nombres): bool
    {
        return $this->perfil && in_array($this->perfil->nombre, $nombres, true);
    }
}
