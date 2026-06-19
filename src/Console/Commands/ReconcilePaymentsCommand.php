<?php

namespace Ejoi8\MalaysiaPaymentGateway\Console\Commands;

use Ejoi8\MalaysiaPaymentGateway\Enums\PaymentStatus;
use Ejoi8\MalaysiaPaymentGateway\GatewayManager;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Recover stuck pending payments by polling the gateway for their real status.
 *
 * If a gateway's webhook was never delivered (gateway hiccup, your server down
 * during the callback), the payment is left 'pending' forever even though the
 * money moved. This command finds those, asks the gateway, and fires the
 * matching event so the status updates and notifications go out.
 *
 * Schedule it, e.g. in routes/console.php or app/Console/Kernel.php:
 *   Schedule::command('payment:reconcile')->everyFiveMinutes();
 */
class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'payment:reconcile
        {--minutes=15 : Only reconcile payments at least this many minutes old}
        {--limit=100 : Maximum number of payments to process in one run}';

    protected $description = 'Resolve stuck pending payments by polling the gateway (recovers missed webhooks).';

    public function handle(GatewayManager $manager): int
    {
        $modelClass = config('payment-gateway.model');

        if (! is_string($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            $this->error('payment:reconcile requires an Eloquent payable model (config payment-gateway.model).');

            return self::FAILURE;
        }

        $cutoff = now()->subMinutes((int) $this->option('minutes'));

        $pending = $modelClass::query()
            ->whereIn('status', PaymentStatus::pendingStatuses())
            ->where('created_at', '<=', $cutoff)
            ->limit((int) $this->option('limit'))
            ->get();

        if ($pending->isEmpty()) {
            $this->info('No pending payments to reconcile.');

            return self::SUCCESS;
        }

        $resolved = 0;

        foreach ($pending as $payable) {
            $driver = $payable->getAttribute('gateway') ?: config('payment-gateway.default', 'chip');

            try {
                $status = $manager->reconcile($driver, $payable);

                if (! PaymentStatus::isPending($status)) {
                    $resolved++;
                    $this->line("  {$payable->getPaymentReference()} → {$status}");
                }
            } catch (\Throwable $e) {
                $this->warn("  {$payable->getPaymentReference()} → error: {$e->getMessage()}");
            }
        }

        $this->info("Reconciled {$resolved} of {$pending->count()} pending payment(s).");

        return self::SUCCESS;
    }
}
