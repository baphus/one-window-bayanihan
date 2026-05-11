<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    public function created($model): void
    {
        $this->log('CREATE', $model, null, $this->filterKeys($model->getAttributes(), $model));
    }

    public function updated($model): void
    {
        $old = array_intersect_key($model->getOriginal(), $model->getDirty());
        $new = $model->getDirty();

        $old = $this->filterKeys($old, $model);
        $new = $this->filterKeys($new, $model);

        unset($old['updated_at'], $new['updated_at']);

        if (empty($new)) {
            return;
        }

        $this->log('UPDATE', $model, $old, $new);
    }

    public function deleted($model): void
    {
        $this->log('DELETE', $model, $this->filterKeys($model->getAttributes(), $model), null);
    }

    public function restored($model): void
    {
        $this->log('UPDATE', $model, ['is_deleted' => true], ['is_deleted' => false]);
    }

    private function log(string $action, $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'action' => $action,
            'module' => $model->getTable(),
            'entity_id' => $model->getKey(),
            'old_value' => $old,
            'new_value' => $new,
            'user_id' => Auth::id(),
            'timestamp' => now(),
        ]);
    }

    private function filterKeys(array $attributes, $model): array
    {
        if (property_exists($model, 'auditExclude')) {
            return array_diff_key($attributes, array_flip($model::$auditExclude));
        }

        return $attributes;
    }
}
