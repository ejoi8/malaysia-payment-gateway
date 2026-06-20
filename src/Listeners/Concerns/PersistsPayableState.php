<?php

namespace Ejoi8\MalaysiaPaymentGateway\Listeners\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared persistence logic for listeners that write back to the payable.
 *
 * Prefers an explicit `applyPaymentGatewayUpdate(array $attributes)` hook on the
 * model (for custom column mappings or non-Eloquent payables); otherwise force
 * fills and saves an Eloquent model. Anything else is a no-op.
 */
trait PersistsPayableState
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function persistPayableState(object $payable, array $attributes): void
    {
        if (method_exists($payable, 'applyPaymentGatewayUpdate')) {
            $payable->applyPaymentGatewayUpdate($attributes);

            return;
        }

        if (! $payable instanceof Model) {
            return;
        }

        $payable->forceFill($attributes);
        $payable->save();
    }
}
