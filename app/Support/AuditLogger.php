<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    public static function log(string $action, string $description, ?Model $subject = null, array $properties = []): void
    {
        $request = request();
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? Str::snake(class_basename($subject)) : null,
            'subject_id' => $subject?->getKey(),
            'description' => Str::limit($description, 490),
            'properties' => $properties ?: null,
            'ip' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 250, ''),
        ]);
    }

    /** Pending changes (old → new) of a model. Call BEFORE save(). */
    public static function diff(Model $model, array $ignore = ['updated_at', 'created_at', 'password', 'remember_token']): array
    {
        $changes = [];
        foreach ($model->getDirty() as $key => $new) {
            if (in_array($key, $ignore, true)) {
                continue;
            }
            $old = $model->getOriginal($key);
            $changes[$key] = ['from' => is_scalar($old) || $old === null ? $old : '[complex]', 'to' => is_scalar($new) || $new === null ? Str::limit((string) $new, 200) : '[complex]'];
        }

        return $changes;
    }
}
