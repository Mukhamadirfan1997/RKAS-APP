<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

trait LogsActivity
{
    protected static function bootLogsActivity()
    {
        static::created(function ($model) {
            static::logAudit('created', $model);
        });

        static::updated(function ($model) {
            static::logAudit('updated', $model);
        });

        static::deleted(function ($model) {
            static::logAudit('deleted', $model);
        });
    }

    protected static function logAudit(string $action, $model, $oldValues = null, $newValues = null)
    {
        $changes = $model->wasChanged() ? $model->getChanges() : null;
        $original = $model->wasChanged() ? $model->getOriginal() : null;

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'description' => static::class.' #'.$model->getKey().' '.$action,
            'old_values' => $oldValues ?? $original,
            'new_values' => $newValues ?? $changes,
        ]);
    }
}
