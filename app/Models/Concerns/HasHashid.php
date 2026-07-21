<?php

namespace App\Models\Concerns;

use App\Support\Hashid;

/**
 * Route-model binding via an obfuscated id (e.g. /employees/X7gk2 instead of
 * /employees/3), so URLs never expose sequential database ids. route($m) encodes
 * automatically via getRouteKey(); binding decodes and then resolves through the
 * model's tenant query so scoping/authorization is untouched (a foreign id still
 * 404s). Models that need a scoped binding define tenantBindingQuery().
 */
trait HasHashid
{
    public function getRouteKey(): string
    {
        return Hashid::encode($this->getKey());
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->resolveHashid($value, $field, false);
    }

    public function resolveSoftDeletableRouteBinding($value, $field = null)
    {
        return $this->resolveHashid($value, $field, true);
    }

    private function resolveHashid($value, $field, bool $withTrashed)
    {
        $query = method_exists($this, 'tenantBindingQuery') ? $this->tenantBindingQuery() : $this->newQuery();
        if ($withTrashed) {
            $query->withTrashed();
        }

        // A non-key field (rare) is matched as-is; the key is a hashid to decode.
        if ($field && $field !== $this->getKeyName()) {
            return $query->where($field, $value)->first();
        }

        $id = Hashid::decode(is_string($value) ? $value : (string) $value);

        return $id === null ? null : $query->where($this->getKeyName(), $id)->first();
    }
}
