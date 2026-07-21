<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * One raw punch (ZKTeco-style log entry). Additive to Attendance: recorded on
 * every successful kiosk mark, keeping the full per-day sequence for later break
 * detection and the raw-punch detail view.
 */
class AttendanceMark extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'employee_id', 'attendance_id', 'marked_at', 'kind', 'method', 'photo', 'lat', 'lng', 'accuracy', 'ip', 'user_agent',
    ];

    protected $casts = ['marked_at' => 'datetime', 'lat' => 'float', 'lng' => 'float', 'accuracy' => 'integer'];

    /** True when this punch carries GPS coordinates */
    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /** True when this punch has its own evidence photo (document/DNI marks) */
    public function hasPhoto(): bool
    {
        return !empty($this->photo);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }
}
