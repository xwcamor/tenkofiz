<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Site extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'name', 'address', 'timezone', 'is_active',
        'kiosk_token', 'kiosk_device_hash', 'kiosk_pair_code', 'kiosk_pair_expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'kiosk_pair_expires_at' => 'datetime',
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** Paired kiosk tablets bound to this site (multi-device) */
    public function kioskDevices()
    {
        return $this->hasMany(KioskDevice::class)->orderBy('name');
    }

    /** True when at least one tablet is paired (device binding is then enforced) */
    public function hasPairedDevices(): bool
    {
        return $this->kioskDevices()->exists();
    }

    /** Generates (or rotates) this site's kiosk access token */
    public function regenerateKioskToken(): void
    {
        $this->update(['kiosk_token' => Str::random(48)]);
    }

    /** The authorized kiosk URL for this site (carries the site id and token) */
    public function kioskLink(): string
    {
        return url('kiosk').'?'.http_build_query(array_filter([
            'site' => $this->id,
            'token' => $this->kiosk_token,
        ]));
    }
}
