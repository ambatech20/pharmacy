<?php

namespace App\Traits;

use App\Models\ActivityLog;

/**
 * Add `use LogsActivity;` inside any model to automatically record every
 * create, update, and delete on it — no controller changes needed.
 */
trait LogsActivity
{
    protected static function bootLogsActivity()
    {
        static::created(function ($model) {
            static::writeActivityLog('created', $model, static::cleanAttributes($model->getAttributes()));
        });

        static::updated(function ($model) {
            $changes = static::cleanAttributes($model->getChanges());
            if (empty($changes)) {
                return; // e.g. a save() that didn't actually change anything
            }
            static::writeActivityLog('updated', $model, $changes);
        });

        static::deleted(function ($model) {
            static::writeActivityLog('deleted', $model, []);
        });
    }

    protected static function cleanAttributes(array $attributes): array
    {
        // Timestamps alone aren't meaningful activity — strip them so
        // the log isn't full of noise, and never log a password value.
        unset($attributes['updated_at'], $attributes['created_at'], $attributes['password']);
        return $attributes;
    }

    protected static function writeActivityLog(string $action, $model, array $changes): void
    {
        ActivityLog::create([
            // auth()->id() is null for actions outside a web request
            // (e.g. a seeder) — nullable column handles that safely.
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => class_basename($model),
            'model_id' => $model->getKey(),
            'changes' => $changes,
        ]);
    }
}
