<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Justification extends Model
{
    use SoftDeletes;

    protected $fillable = ['employee_id', 'date', 'reason', 'document', 'status', 'reviewed_by', 'delete_reason'];

    protected $casts = ['date' => 'date'];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }
}
