<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Pass Verification - Modor MC</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'Outfit', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #f8fafc;
            background-image: radial-gradient(#e2e8f0 1.2px, transparent 1.2px);
            background-size: 24px 24px;
        }
        .premium-glass {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }
        .pulse-border {
            animation: pulseGlow 2s infinite alternate;
        }
        @keyframes pulseGlow {
            0% { box-shadow: 0 0 5px rgba(239, 68, 68, 0.2); }
            100% { box-shadow: 0 0 15px rgba(239, 68, 68, 0.6); }
        }
        .pulse-border-green {
            animation: pulseGlowGreen 2s infinite alternate;
        }
        @keyframes pulseGlowGreen {
            0% { box-shadow: 0 0 5px rgba(16, 185, 129, 0.2); }
            100% { box-shadow: 0 0 15px rgba(16, 185, 129, 0.6); }
        }
    </style>
</head>
<body class="font-sans min-h-screen text-slate-800 flex flex-col justify-between py-6 px-4">

    <!-- Main Container -->
    <div class="max-w-md w-full mx-auto bg-white/90 rounded-3xl shadow-xl overflow-hidden premium-glass border border-slate-100/80 transition-all duration-300">
        
        <!-- Status Header Banner -->
        @if ($batch->is_verified)
            <div class="bg-gradient-to-r from-emerald-500 to-teal-600 px-6 py-5 text-center text-white relative overflow-hidden">
                <div class="absolute inset-0 bg-white/10 rotate-12 scale-150 translate-y-4"></div>
                <div class="relative z-10 flex flex-col items-center gap-1">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center pulse-border-green mb-1">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h1 class="text-xl font-extrabold tracking-wide uppercase font-mono">Trip Verified</h1>
                    <p class="text-xs text-emerald-100 font-medium">Verified on {{ \Carbon\Carbon::parse($batch->verified_at)->format('d-M-Y H:i:s') }}</p>
                </div>
            </div>
        @else
            <div class="bg-gradient-to-r from-rose-500 to-orange-500 px-6 py-5 text-center text-white relative overflow-hidden">
                <div class="absolute inset-0 bg-white/10 -rotate-12 scale-150 translate-y-4"></div>
                <div class="relative z-10 flex flex-col items-center gap-1">
                    <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center pulse-border mb-1">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </div>
                    <h1 class="text-xl font-extrabold tracking-wide uppercase font-mono">Pending Verification</h1>
                    <p class="text-xs text-rose-100 font-medium">Awaiting site check-in confirmation</p>
                </div>
            </div>
        @endif

        <!-- Card Body -->
        <div class="p-6 space-y-6">
            <!-- Plant Brand Info -->
            <div class="text-center pb-4 border-b border-slate-100">
                <h2 class="text-lg font-bold text-slate-900 tracking-tight">{{ $batch->workOrder?->plant?->name ?? 'MODOR MC' }}</h2>
                <p class="text-xs text-slate-500 mt-0.5">{{ $batch->workOrder?->plant?->addresses?->first()?->line_1 ?? '' }}</p>
            </div>

            <!-- Pass Identity -->
            <div class="flex justify-between items-center bg-slate-50 rounded-2xl p-4 border border-slate-100">
                <div>
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Gate Pass No</span>
                    <div class="text-lg font-extrabold text-indigo-600 font-mono">B{{ str_pad($batch->batch_no ?? $batch->id, 4, '0', STR_PAD_LEFT) }}</div>
                </div>
                <div class="text-right">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Load Time</span>
                    <div class="text-sm font-bold text-slate-700 font-mono">{{ optional($batch->load_time ?? $batch->created_at)->format('d-M-Y H:i') }}</div>
                </div>
            </div>

            <!-- Segment 1: Customer / Location -->
            <div>
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Delivery & Customer Info</h3>
                <div class="space-y-3 bg-white border border-slate-100 rounded-2xl p-4 shadow-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-xs font-semibold text-slate-500">Customer</span>
                        <span class="text-xs font-bold text-slate-950 text-right">{{ $batch->workOrder?->customer?->legal_name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-xs font-semibold text-slate-500">Site Location</span>
                        <span class="text-xs font-bold text-slate-950 text-right">{{ $batch->workOrder?->site?->name ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-xs font-semibold text-slate-500">Order Ref</span>
                        <span class="text-xs font-bold text-slate-950 font-mono">{{ $batch->workOrder?->order_no ?? '-' }}</span>
                    </div>
                </div>
            </div>

            <!-- Segment 2: Mix Design / Concrete -->
            <div>
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Mix Details</h3>
                <div class="space-y-3 bg-white border border-slate-100 rounded-2xl p-4 shadow-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-xs font-semibold text-slate-500">Concrete Grade</span>
                        <span class="text-xs font-bold text-slate-950 text-right">{{ $batch->workOrder?->mixDesign?->concrete_grade?->name ?? ($batch->workOrder?->mixDesign?->design_name ?? '-') }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-xs font-semibold text-slate-500">Design Code</span>
                        <span class="text-xs font-bold text-slate-950 font-mono">{{ $batch->workOrder?->mixDesign?->design_code ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between gap-4 items-center">
                        <span class="text-xs font-semibold text-slate-500">Total Quantity</span>
                        <span class="text-base font-extrabold text-indigo-600 font-mono">{{ number_format((float) $batch->batch_size, 2) }} m³</span>
                    </div>
                </div>
            </div>

            <!-- Segment 3: Vehicle & Driver -->
            @php
                $dispatch = $batch->dispatches->first();
            @endphp
            <div>
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Transport Details</h3>
                <div class="space-y-3 bg-white border border-slate-100 rounded-2xl p-4 shadow-sm">
                    <div class="flex justify-between gap-4">
                        <span class="text-xs font-semibold text-slate-500">Truck No</span>
                        <span class="text-xs font-extrabold text-slate-950 font-mono tracking-wide">{{ $dispatch?->truck?->registration ?? '-' }}</span>
                    </div>
                    <div class="flex justify-between gap-4">
                        <span class="text-xs font-semibold text-slate-500">Driver</span>
                        <span class="text-xs font-bold text-slate-950">{{ trim(($dispatch?->driver?->first_name ?? '') . ' ' . ($dispatch?->driver?->last_name ?? '')) ?: '-' }}</span>
                    </div>
                    @if ($dispatch?->transport?->legal_name)
                        <div class="flex justify-between gap-4">
                            <span class="text-xs font-semibold text-slate-500">Transporter</span>
                            <span class="text-xs font-bold text-slate-950 text-right">{{ $dispatch->transport->legal_name }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Segment 4: Weights -->
            @php
                $isMetricTon = !empty($settings['InvoiceInMetricTon']) && $settings['InvoiceInMetricTon'] == 1;
                $unitLabel = $isMetricTon ? ' MT' : ' KGS';
                $decimals  = $isMetricTon ? 3 : 0;
                $emptyWeight  = (float) ($dispatch?->empty_weight_truck ?? 0);
                $loadedWeight = (float) ($dispatch?->loaded_weight_truck ?? 0);
                $netWeight    = (float) ($dispatch?->net_weight ?? max(0, $loadedWeight - $emptyWeight));
            @endphp
            <div>
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Weight Record</h3>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-3">
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Empty Weight</span>
                        <span class="text-sm font-bold text-slate-700 font-mono">{{ number_format($emptyWeight, $decimals) }}{{ $unitLabel }}</span>
                    </div>
                    <div class="bg-slate-50 border border-slate-100 rounded-2xl p-3">
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider block mb-0.5">Loaded Weight</span>
                        <span class="text-sm font-bold text-slate-700 font-mono">{{ number_format($loadedWeight, $decimals) }}{{ $unitLabel }}</span>
                    </div>
                    <div class="col-span-2 bg-indigo-50/50 border border-indigo-100/80 rounded-2xl p-4 flex justify-between items-center">
                        <span class="text-xs font-bold text-indigo-900 uppercase tracking-wider">Net Material Weight</span>
                        <span class="text-lg font-extrabold text-indigo-700 font-mono">{{ number_format($netWeight, $decimals) }}{{ $unitLabel }}</span>
                    </div>
                </div>
            </div>

            <!-- Verification Action -->
            <div class="pt-4 border-t border-slate-100">
                @if ($batch->is_verified)
                    <div class="bg-emerald-50 border border-emerald-200 rounded-2xl p-4 flex items-start gap-3">
                        <svg class="w-5 h-5 text-emerald-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <div>
                            <span class="text-xs font-bold text-emerald-800 block">Gate Pass Verified</span>
                            <span class="text-[10px] text-emerald-600 block mt-0.5">This shipment has been officially received and marked verified in the system database.</span>
                        </div>
                    </div>
                @else
                    <form action="{{ route('public.gatepass.confirm', ['batch' => $batch->id, 'hash' => $hash]) }}" method="POST" class="space-y-3">
                        @csrf
                        <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white text-sm font-bold uppercase tracking-wider py-4 px-6 rounded-2xl shadow-lg shadow-indigo-600/15 hover:shadow-indigo-600/25 active:scale-[0.98] transition-all duration-150 flex items-center justify-center gap-2">
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Verify & Confirm Trip
                        </button>
                        <p class="text-[10px] text-slate-400 text-center">Clicking verify confirms that the vehicle and RMC shipment have arrived at the site matching the above details.</p>
                    </form>
                @endif
            </div>

        </div>
    </div>

    <!-- Footer -->
    <div class="text-center text-[10px] text-slate-400 mt-8 font-medium">
        Modor MC Portal &copy; {{ date('Y') }} &mdash; Secured Verification System
    </div>

</body>
</html>
