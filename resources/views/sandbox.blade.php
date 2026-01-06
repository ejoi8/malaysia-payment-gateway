<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Gateway Sandbox</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .field-group {
            display: none;
        }

        .field-group.active {
            display: block;
        }
    </style>
</head>

<body class="bg-slate-100 min-h-screen py-8 px-4">
    <div class="max-w-2xl mx-auto">

        {{-- Header --}}
        <div class="text-center mb-8">
            <div
                class="inline-flex items-center gap-2 bg-amber-100 text-amber-800 px-3 py-1 rounded-full text-xs font-medium mb-3">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                Development Tool
            </div>
            <h1 class="text-2xl font-bold text-slate-800">Payment Gateway Sandbox</h1>
            <p class="text-slate-500 text-sm mt-1">Test your payment gateway integrations</p>
        </div>

        {{-- Main Form --}}
        <form action="{{ route('payment-gateway.sandbox.initiate') }}" method="POST" class="space-y-6">
            @csrf

            {{-- Section: Gateway Configuration --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                    <h2 class="font-semibold text-slate-700 text-sm">Gateway Configuration</h2>
                </div>
                <div class="p-5 space-y-4">
                    {{-- Gateway Selector --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Payment Gateway</label>
                        <select name="gateway" id="gateway-select" onchange="updateGatewayFields()"
                            class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach ($gateways as $key => $name)
                                <option value="{{ $key }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Dynamic Gateway Fields --}}
                    @foreach ($gatewayFields as $gateway => $fields)
                        <div id="fields-{{ $gateway }}"
                            class="field-group space-y-3 {{ $loop->first ? 'active' : '' }}">
                            @forelse($fields as $field)
                                <div>
                                    <label
                                        class="block text-xs font-medium text-slate-600 mb-1">{{ $field['label'] }}</label>
                                    @if ($field['type'] === 'checkbox')
                                        <label class="inline-flex items-center gap-2">
                                            <input type="checkbox" name="{{ $gateway }}_{{ $field['name'] }}"
                                                value="1"
                                                {{ $gatewayConfigs[$gateway][$field['name']] ?? false ? 'checked' : '' }}
                                                class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            <span class="text-sm text-slate-600">Enabled</span>
                                        </label>
                                    @elseif($field['type'] === 'password')
                                        <div class="relative">
                                            <input type="password" name="{{ $gateway }}_{{ $field['name'] }}"
                                                id="field-{{ $gateway }}-{{ $field['name'] }}"
                                                value="{{ $gatewayConfigs[$gateway][$field['name']] ?? '' }}"
                                                placeholder="From config or enter to override"
                                                class="w-full rounded-lg border-slate-300 border px-3 py-2 pr-10 text-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono text-xs">
                                            <button type="button"
                                                onclick="togglePassword('field-{{ $gateway }}-{{ $field['name'] }}')"
                                                class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                                <svg class="w-5 h-5 eye-icon" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </button>
                                        </div>
                                    @else
                                        <input type="{{ $field['type'] }}"
                                            name="{{ $gateway }}_{{ $field['name'] }}"
                                            value="{{ $gatewayConfigs[$gateway][$field['name']] ?? '' }}"
                                            placeholder="From config or enter to override"
                                            class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono text-xs">
                                    @endif
                                </div>
                            @empty
                                <p class="text-sm text-slate-400 italic">No configuration required for this gateway.</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Section: Payable Type --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                    <h2 class="font-semibold text-slate-700 text-sm">Payment Details</h2>
                </div>
                <div class="p-5 space-y-4">
                    {{-- Payable Type Selector --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Payment Type</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            @foreach ($payableTypes as $key => $type)
                                <label class="relative">
                                    <input type="radio" name="payable_type" value="{{ $key }}"
                                        {{ $loop->first ? 'checked' : '' }} onchange="updatePayableFields()"
                                        class="peer sr-only">
                                    <div
                                        class="p-3 rounded-lg border-2 border-slate-200 cursor-pointer hover:border-slate-300 peer-checked:border-indigo-500 peer-checked:bg-indigo-50 transition-all">
                                        <div class="font-medium text-sm text-slate-700">{{ $type['label'] }}</div>
                                        <div class="text-xs text-slate-400 mt-0.5 line-clamp-1">
                                            {{ $type['description'] }}</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Dynamic Payable Fields --}}
                    @foreach ($payableTypes as $key => $type)
                        <div id="payable-{{ $key }}"
                            class="field-group space-y-3 {{ $loop->first ? 'active' : '' }}">
                            @foreach ($type['fields'] as $field)
                                @if ($field['type'] === 'products')
                                    {{-- E-Commerce Products --}}
                                    <div>
                                        <label class="block text-xs font-medium text-slate-600 mb-2">Products</label>
                                        <div id="products-list" class="space-y-2">
                                            <div class="product-row grid grid-cols-12 gap-2">
                                                <input type="text" name="products[0][name]" value="Product 1"
                                                    placeholder="Name"
                                                    class="col-span-5 rounded border-slate-300 px-2 py-1.5 text-sm">
                                                <input type="number" name="products[0][qty]" value="1"
                                                    placeholder="Qty"
                                                    class="col-span-2 rounded border-slate-300 px-2 py-1.5 text-sm text-center">
                                                <input type="number" name="products[0][price]" value="25.00"
                                                    step="0.01" placeholder="Price"
                                                    class="col-span-4 rounded border-slate-300 px-2 py-1.5 text-sm text-right">
                                                <button type="button" onclick="this.closest('.product-row').remove()"
                                                    class="col-span-1 text-red-400 hover:text-red-600">×</button>
                                            </div>
                                        </div>
                                        <button type="button" onclick="addProduct()"
                                            class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">+ Add
                                            Product</button>
                                    </div>
                                @elseif($field['type'] === 'select')
                                    <div>
                                        <label
                                            class="block text-xs font-medium text-slate-600 mb-1">{{ $field['label'] }}</label>
                                        <select name="{{ $key }}_{{ $field['name'] }}"
                                            class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                                            @foreach ($field['options'] as $val => $label)
                                                <option value="{{ $val }}"
                                                    {{ ($field['default'] ?? '') == $val ? 'selected' : '' }}>
                                                    {{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @else
                                    <div>
                                        <label
                                            class="block text-xs font-medium text-slate-600 mb-1">{{ $field['label'] }}</label>
                                        <input type="{{ $field['type'] }}"
                                            name="{{ $key }}_{{ $field['name'] }}"
                                            value="{{ $field['default'] ?? '' }}"
                                            step="{{ $field['type'] === 'number' ? '0.01' : '' }}"
                                            class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Section: Customer --}}
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                    <h2 class="font-semibold text-slate-700 text-sm">Customer Information</h2>
                </div>
                <div class="p-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
                            <input type="text" name="customer_name" value="Test User" required
                                class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
                            <input type="email" name="customer_email" value="test@example.com" required
                                class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Phone (optional)</label>
                            <input type="tel" name="customer_phone" value="+60123456789"
                                class="w-full rounded-lg border-slate-300 border px-3 py-2 text-sm">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <button type="submit"
                class="w-full py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold rounded-xl shadow-sm transition-colors">
                Initiate Payment
            </button>
        </form>

        {{-- Result Display --}}
        @if (session('sandbox_result'))
            @php $result = session('sandbox_result'); @endphp
            <div id="api-result" class="mt-6 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex justify-between items-center">
                    <h2 class="font-semibold text-slate-700 text-sm">API Response</h2>
                    <span
                        class="px-2 py-0.5 rounded text-xs font-medium {{ ($result['type'] ?? '') === 'error' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                        {{ ($result['type'] ?? '') === 'error' ? 'Error' : 'Success' }}
                    </span>
                </div>
                <div class="p-5">
                    <pre class="bg-slate-900 text-slate-100 p-4 rounded-lg text-xs overflow-x-auto font-mono">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>

                    @if (isset($result['url']))
                        <a href="{{ $result['url'] }}" target="_blank"
                            class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Open Payment Page
                        </a>
                    @endif

                    @if (isset($result['curl_test']))
                        <div class="mt-4">
                            <h3 class="text-xs font-semibold text-slate-700 mb-2">Simulate Webhook Callback:</h3>
                            <pre
                                class="bg-amber-50 border border-amber-200 text-slate-800 p-3 rounded-lg text-xs overflow-x-auto font-mono select-all whitespace-pre-wrap">{{ $result['curl_test'] }}</pre>
                            <p class="text-[10px] text-slate-400 mt-1">Copy and paste into your terminal to trigger a
                                success event for this payment.</p>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Footer --}}
        <p class="text-center text-xs text-slate-400 mt-8">
            Malaysia Payment Gateway Package &bull; Sandbox Utility
        </p>
    </div>

    <script>
        let productIndex = 1;

        // Initialize state on load
        document.addEventListener('DOMContentLoaded', () => {
            // Restore gateway selection
            const initialGateway = "{{ old('gateway', array_key_first($gateways)) }}";
            const gwSelect = document.getElementById('gateway-select');
            if (gwSelect) {
                gwSelect.value = initialGateway;
                updateGatewayFields();
            }

            // Restore payable type selection
            const initialType = "{{ old('payable_type', array_key_first($payableTypes)) }}";
            const typeRadio = document.querySelector(`input[name="payable_type"][value="${initialType}"]`);
            if (typeRadio) {
                typeRadio.checked = true;
                updatePayableFields();
            }

            // Scroll to result if present
            const resultEl = document.getElementById('api-result');
            if (resultEl) {
                resultEl.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });

        function updateGatewayFields() {
            const gateway = document.getElementById('gateway-select').value;
            document.querySelectorAll('[id^="fields-"]').forEach(el => el.classList.remove('active'));
            const target = document.getElementById('fields-' + gateway);
            if (target) target.classList.add('active');
        }

        function updatePayableFields() {
            const type = document.querySelector('input[name="payable_type"]:checked').value;
            document.querySelectorAll('[id^="payable-"]').forEach(el => el.classList.remove('active'));
            const target = document.getElementById('payable-' + type);
            if (target) target.classList.add('active');
        }

        function addProduct() {
            const list = document.getElementById('products-list');
            const row = document.createElement('div');
            row.className = 'product-row grid grid-cols-12 gap-2';
            row.innerHTML = `
                <input type="text" name="products[${productIndex}][name]" placeholder="Name" class="col-span-5 rounded border-slate-300 px-2 py-1.5 text-sm">
                <input type="number" name="products[${productIndex}][qty]" value="1" placeholder="Qty" class="col-span-2 rounded border-slate-300 px-2 py-1.5 text-sm text-center">
                <input type="number" name="products[${productIndex}][price]" step="0.01" placeholder="Price" class="col-span-4 rounded border-slate-300 px-2 py-1.5 text-sm text-right">
                <button type="button" onclick="this.closest('.product-row').remove()" class="col-span-1 text-red-400 hover:text-red-600">×</button>
            `;
            list.appendChild(row);
            productIndex++;
        }

        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }
    </script>
</body>

</html>
