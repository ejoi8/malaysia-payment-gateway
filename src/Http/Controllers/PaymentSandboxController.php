<?php

namespace Ejoi8\MalaysiaPaymentGateway\Http\Controllers;

use Ejoi8\MalaysiaPaymentGateway\Contracts\PayableInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Payment Sandbox Controller
 *
 * A developer utility to test payment gateway integrations.
 * Accessible only when enabled via config.
 */
class PaymentSandboxController extends Controller
{
    /**
     * Display the sandbox form.
     */
    public function index()
    {
        // Get all configured gateways (dynamic, not hardcoded)
        $gateways = $this->getGatewayList();

        // Get gateway-specific field definitions
        $gatewayFields = $this->getGatewayFields();

        // Get gateway configs for default values
        $gatewayConfigs = config('payment-gateway.gateways', []);

        // Payable type definitions
        $payableTypes = $this->getPayableTypes();

        return view('payment-gateway::sandbox', compact(
            'gateways',
            'gatewayFields',
            'gatewayConfigs',
            'payableTypes'
        ));
    }

    /**
     * Process the payment initiation.
     */
    public function initiate(Request $request, \Ejoi8\MalaysiaPaymentGateway\GatewayManager $manager)
    {
        $request->validate([
            'gateway' => 'required|string',
            'payable_type' => 'required|string',
            'customer_name' => 'required|string',
            'customer_email' => 'required|email',
        ]);

        // Build the payable from form data
        $gatewayName = $request->input('gateway');
        $payable = $this->buildPayable($request, $gatewayName);

        // Create gateway instance with user-provided credentials
        $gatewayInstance = $this->createGateway($request);

        // Register this dynamic instance with the manager
        // We use the actual gateway name to perfectly mimic the real flow
        $manager->extend($gatewayName, fn () => $gatewayInstance);

        // Initiate payment via the manager
        // This ensures events (PaymentInitiated) are fired automatically with the correct driver name
        $result = $manager->initiate($gatewayName, $payable);

        return back()->with('sandbox_result', $result)->withInput();
    }

    /**
     * Get list of available gateways from config.
     */
    protected function getGatewayList(): array
    {
        $gateways = [];
        $configs = config('payment-gateway.gateways', []);

        foreach ($configs as $key => $config) {
            // Generate a readable name from the key
            $gateways[$key] = ucwords(str_replace('_', ' ', $key));
        }

        return $gateways;
    }

    /**
     * Define which fields each gateway needs.
     * This is used by the frontend to show/hide fields.
     */
    protected function getGatewayFields(): array
    {
        return [
            'chip' => [
                ['name' => 'brand_id', 'label' => 'Brand ID', 'type' => 'text'],
                ['name' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password'],
                ['name' => 'sandbox', 'label' => 'Sandbox Mode', 'type' => 'checkbox'],
            ],
            'toyyibpay' => [
                ['name' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password'],
                ['name' => 'category_code', 'label' => 'Category Code', 'type' => 'text'],
                ['name' => 'sandbox', 'label' => 'Sandbox Mode', 'type' => 'checkbox'],
            ],
            'stripe' => [
                ['name' => 'secret_key', 'label' => 'Secret Key', 'type' => 'password'],
                ['name' => 'public_key', 'label' => 'Public Key', 'type' => 'text'],
            ],
            'paypal' => [
                ['name' => 'client_id', 'label' => 'Client ID', 'type' => 'text'],
                ['name' => 'client_secret', 'label' => 'Client Secret', 'type' => 'password'],
                ['name' => 'sandbox', 'label' => 'Sandbox Mode', 'type' => 'checkbox'],
            ],
            'manual_proof' => [],
        ];
    }

    /**
     * Define available payable types (real-life scenarios).
     */
    protected function getPayableTypes(): array
    {
        return [
            'simple' => [
                'label' => 'Simple Payment',
                'description' => 'Single amount payment (donations, tips, quick pay)',
                'fields' => [
                    ['name' => 'amount', 'label' => 'Amount (MYR)', 'type' => 'number', 'default' => '10.00'],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'text', 'default' => 'Payment'],
                ],
            ],
            'invoice' => [
                'label' => 'Invoice Payment',
                'description' => 'Pay an invoice with reference number',
                'fields' => [
                    ['name' => 'invoice_no', 'label' => 'Invoice Number', 'type' => 'text', 'default' => 'INV-001'],
                    ['name' => 'amount', 'label' => 'Amount (MYR)', 'type' => 'number', 'default' => '250.00'],
                    ['name' => 'due_date', 'label' => 'Due Date', 'type' => 'date', 'default' => ''],
                ],
            ],
            'booking' => [
                'label' => 'Booking / Reservation',
                'description' => 'Court booking, hotel, appointment',
                'fields' => [
                    ['name' => 'booking_ref', 'label' => 'Booking Reference', 'type' => 'text', 'default' => 'BK-001'],
                    ['name' => 'service_name', 'label' => 'Service Name', 'type' => 'text', 'default' => 'Court Booking'],
                    ['name' => 'booking_date', 'label' => 'Date', 'type' => 'date', 'default' => ''],
                    ['name' => 'booking_time', 'label' => 'Time', 'type' => 'time', 'default' => '10:00'],
                    ['name' => 'amount', 'label' => 'Amount (MYR)', 'type' => 'number', 'default' => '60.00'],
                ],
            ],
            'ecommerce' => [
                'label' => 'E-Commerce Cart',
                'description' => 'Multiple products with quantities',
                'fields' => [
                    ['name' => 'products', 'label' => 'Products', 'type' => 'products'],
                ],
            ],
            'event' => [
                'label' => 'Event Ticket',
                'description' => 'Concert, seminar, workshop tickets',
                'fields' => [
                    ['name' => 'event_name', 'label' => 'Event Name', 'type' => 'text', 'default' => 'Tech Conference 2026'],
                    ['name' => 'event_date', 'label' => 'Event Date', 'type' => 'date', 'default' => ''],
                    ['name' => 'ticket_type', 'label' => 'Ticket Type', 'type' => 'text', 'default' => 'General Admission'],
                    ['name' => 'quantity', 'label' => 'Quantity', 'type' => 'number', 'default' => '1'],
                    ['name' => 'price_per_ticket', 'label' => 'Price per Ticket (MYR)', 'type' => 'number', 'default' => '150.00'],
                ],
            ],
            'subscription' => [
                'label' => 'Subscription',
                'description' => 'Recurring membership or service plan',
                'fields' => [
                    ['name' => 'plan_name', 'label' => 'Plan Name', 'type' => 'text', 'default' => 'Pro Membership'],
                    ['name' => 'amount', 'label' => 'Amount (MYR)', 'type' => 'number', 'default' => '99.00'],
                    ['name' => 'interval', 'label' => 'Billing Interval', 'type' => 'select', 'options' => ['monthly' => 'Monthly', 'yearly' => 'Yearly'], 'default' => 'monthly'],
                ],
            ],
        ];
    }

    /**
     * Build a Payable object from the form data.
     */
    protected function buildPayable(Request $request, string $gateway): PayableInterface
    {
        $type = $request->input('payable_type');
        $customer = [
            'name' => $request->input('customer_name'),
            'email' => $request->input('customer_email'),
            'phone' => $request->input('customer_phone', ''),
        ];

        // Build items and amount based on payable type
        $items = [];
        $amount = 0;
        $description = 'Payment';
        $reference = 'SBX-'.strtoupper(uniqid()); // Use SBX prefix to identify sandbox transactions

        switch ($type) {
            case 'simple':
                $amount = (int) round(floatval($request->input('simple_amount', 10)) * 100);
                $description = $request->input('simple_description', 'Simple Payment');
                $items = [['name' => $description, 'quantity' => 1, 'price' => $amount]];
                break;

            case 'invoice':
                $amount = (int) round(floatval($request->input('invoice_amount', 250)) * 100);
                $reference = $request->input('invoice_invoice_no', 'INV-001');
                if ($reference === 'INV-001') {
                    $reference .= '-'.time();
                } // Ensure unique
                $description = 'Invoice '.$reference;
                $items = [['name' => $description, 'quantity' => 1, 'price' => $amount]];
                break;

            case 'booking':
                $amount = (int) round(floatval($request->input('booking_amount', 60)) * 100);
                $refInput = $request->input('booking_booking_ref', 'BK-001');
                $reference = ($refInput === 'BK-001') ? $refInput.'-'.time() : $refInput;
                $serviceName = $request->input('booking_service_name', 'Booking');
                $description = $serviceName.' - '.$request->input('booking_booking_date').' '.$request->input('booking_booking_time');
                $items = [['name' => $serviceName, 'quantity' => 1, 'price' => $amount]];
                break;

            case 'ecommerce':
                $products = $request->input('products', []);
                foreach ($products as $product) {
                    if (! empty($product['name']) && ! empty($product['price'])) {
                        $qty = (int) ($product['qty'] ?? 1);
                        $price = (int) round(floatval($product['price']) * 100);
                        $items[] = ['name' => $product['name'], 'quantity' => $qty, 'price' => $price];
                        $amount += $price * $qty;
                    }
                }
                $description = 'E-Commerce Order ('.count($items).' items)';
                break;

            case 'event':
                $qty = (int) $request->input('event_quantity', 1);
                $pricePerTicket = (int) round(floatval($request->input('event_price_per_ticket', 150)) * 100);
                $amount = $pricePerTicket * $qty;
                $eventName = $request->input('event_event_name', 'Event');
                $ticketType = $request->input('event_ticket_type', 'General');
                $description = $eventName.' - '.$ticketType;
                $items = [['name' => $description, 'quantity' => $qty, 'price' => $pricePerTicket]];
                break;

            case 'subscription':
                $amount = (int) round(floatval($request->input('subscription_amount', 99)) * 100);
                $planName = $request->input('subscription_plan_name', 'Subscription');
                $interval = $request->input('subscription_interval', 'monthly');
                $description = $planName.' ('.ucfirst($interval).')';
                $items = [['name' => $planName, 'quantity' => 1, 'price' => $amount]];
                break;
        }

        // Determine currency from gateway config or use default
        $gatewayConfig = config("payment-gateway.gateways.{$gateway}", []);
        $currency = $gatewayConfig['currency'] ?? config('payment-gateway.settings.default_currency', 'MYR');

        // Create REAL Payment record
        return \Ejoi8\MalaysiaPaymentGateway\Models\Payment::create([
            'reference' => $reference,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'customer_name' => $customer['name'],
            'customer_email' => $customer['email'],
            'customer_phone' => $customer['phone'],
            'items' => $items,
            'gateway' => $gateway,
            'status' => 'pending',
            'metadata' => [
                'source' => 'sandbox',
                'payable_type' => $type,
            ],
        ]);
    }

    /**
     * Create gateway instance with user-provided or config credentials.
     */
    protected function createGateway(Request $request)
    {
        $gatewayName = $request->input('gateway');
        $configKey = "payment-gateway.gateways.{$gatewayName}";
        $config = config($configKey, []);
        $driverClass = $config['driver_class'] ?? null;

        if (! $driverClass || ! class_exists($driverClass)) {
            throw new \Exception("Gateway driver not found: {$gatewayName}");
        }

        // Merge user-provided values with config defaults
        $mergedConfig = [];
        $fields = $this->getGatewayFields()[$gatewayName] ?? [];

        foreach ($fields as $field) {
            $fieldName = $field['name'];
            // Use gateway-prefixed input names to avoid collisions (e.g. chip_secret_key)
            $inputName = "{$gatewayName}_{$fieldName}";
            $userValue = $request->input($inputName);

            // Use user value if provided (and not empty), otherwise fall back to config
            if ($field['type'] === 'checkbox') {
                $mergedConfig[$fieldName] = $request->has($inputName);
            } else {
                // Only use user value if it's not null AND not empty string
                $mergedConfig[$fieldName] = (! empty($userValue))
                    ? $userValue
                    : ($config[$fieldName] ?? null);
            }
        }

        // Debug: uncomment to see what's being passed
        // dd($driverClass, $mergedConfig, $config);

        // Create gateway using the make() factory method
        return $driverClass::make($mergedConfig);
    }
}

/**
 * Simple Payable implementation for sandbox testing.
 */
class SandboxPayable implements PayableInterface
{
    public function __construct(
        protected string $reference,
        protected int $amount,
        protected string $description,
        protected array $customer,
        protected array $items,
        protected string $currency = 'MYR'
    ) {}

    public function getPaymentReference(): string
    {
        return $this->reference;
    }

    public function getPaymentAmount(): int
    {
        return $this->amount;
    }

    public function getPaymentCurrency(): string
    {
        return $this->currency;
    }

    public function getPaymentDescription(): string
    {
        return $this->description;
    }

    public function getPaymentCustomer(): array
    {
        return $this->customer;
    }

    public function getPaymentItems(): array
    {
        return $this->items;
    }

    public function getPaymentUrls(): array
    {
        return [
            'return_url' => url('/payment-gateway/sandbox?status=return'),
            'cancel_url' => url('/payment-gateway/sandbox?status=cancel'),
            'callback_url' => url('/payment-gateway/sandbox/callback'),
        ];
    }

    public function getPaymentSettings(): array
    {
        return config('payment-gateway.settings', []);
    }

    public static function findByReference(string $reference): ?self
    {
        // For sandbox testing, we can return a dummy instance if the reference starts with 'test'
        // or just return null to simulate not found.
        // A better approach for the sandbox might be unnecessary complexity,
        // as the sandbox is mostly for *creating* payments.
        // However, to prevent interface errors if the sandbox model is used elsewhere:
        return null;
    }
}
