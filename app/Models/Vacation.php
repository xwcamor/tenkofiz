<?php

namespace App\Models;

use App\Models\Concerns\BelongsToScopedSite;
use Illuminate\Database\Eloquent\Model;

class Vacation extends Model
{
    use BelongsToScopedSite;

    protected $fillable = [
        'employee_id', 'start_date', 'end_date', 'days',
        'status', 'reason', 'approved_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }
}
