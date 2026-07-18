<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'user_id', 'action', 'module', 'description', 'data', 'ip'];

    protected $casts = ['data' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /** Records an audit event. Usage: AuditLog::record('DELETE', 'Employees', 'Deleted...', $model->toArray()) */
    public static function record(string $action, string $module, string $description, ?array $data = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'data' => $data,
            'ip' => request()->ip(),
        ]);
    }
}
