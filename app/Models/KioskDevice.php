<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;

/**
 * One paired kiosk tablet bound to a site. The device holds a long-lived cookie
 * whose sha256 matches device_hash; only devices with a matching row can open
 * that site's kiosk. Revoking a device just deletes its row (the others stay).
 */
class KioskDevice extends Model
{
    use BelongsToCompany, HasHashid;

    protected $fillable = ['company_id', 'site_id', 'name', 'device_hash', 'last_seen_at'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }
}
