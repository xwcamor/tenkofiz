<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Justification extends Model
{
    protected $fillable = ['employee_id', 'date', 'reason', 'document', 'status', 'reviewed_by'];

    protected $casts = ['date' => 'date'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }
}
