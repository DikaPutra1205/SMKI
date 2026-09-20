<?php

namespace App\Services\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

trait ResolvesUnitScope
{
    /**
     * Resolve scoped unit IDs for subtree visibility.
     *
     * @param  array{unit_id?: int|string|null}  $filters
     * @return array<int>|null
     *
     * @throws AuthorizationException
     */
    protected function resolveScopedUnitIds(User $user, array $filters = []): ?array
    {
        $accessible = $user->accessibleUnitIds();
        $requested = $filters['unit_id'] ?? null;

        if ($requested === null || $requested === '') {
            return $accessible;
        }

        $requested = (int) $requested;

        if (is_array($accessible) && ! in_array($requested, $accessible, true)) {
            throw new AuthorizationException('Unit di luar lingkup akses Anda.');
        }

        return [$requested];
    }
}
