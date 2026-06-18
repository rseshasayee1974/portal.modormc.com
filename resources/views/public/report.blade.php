<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report: {{ $pdfData['type'] ?? 'Report' }} - Antigravity ERP</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print-container {
                box-shadow: none !important;
                border: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col antialiased">

    <!-- Top Sticky Navigation Bar -->
    <header class="no-print sticky top-0 z-50 bg-[#1d2d3e] text-white border-b border-[#2c3e50] shadow-md">
        <div class="max-w-6xl mx-auto px-4 py-3 sm:px-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="h-8 w-8 rounded-lg bg-[#0064d2] flex items-center justify-center font-bold text-white tracking-wider text-sm shadow">
                    AG
                </div>
                <div>
                    <h1 class="text-sm font-bold tracking-tight uppercase">Antigravity ERP</h1>
                    <p class="text-[10px] text-slate-300">Public Document Share</p>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <!-- Print Button -->
                <button onclick="window.print()" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-3a2 2 0 00-2-2H9a2 2 0 00-2 2v3a2 2 0 002 2zm5-17V4a1 1 0 011-1h2a1 1 0 011 1v3" />
                    </svg>
                    Print
                </button>

                <!-- Download PDF Button -->
                <a href="{{ route('public.report.pdf', ['token' => $token]) }}" class="px-3.5 py-1.5 bg-[#0064d2] hover:bg-[#0057b8] text-white text-xs font-bold rounded-lg transition-all flex items-center gap-1.5 shadow-sm">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Download PDF
                </a>
            </div>
        </div>
    </header>

    <!-- Notice Banner -->
    <div class="no-print bg-amber-50 border-b border-amber-200 py-2.5 px-4 text-center">
        <div class="max-w-6xl mx-auto flex items-center justify-center gap-2 text-xs font-medium text-amber-800">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>You are viewing a secure read-only version of this report. No authentication required.</span>
        </div>
    </div>

    <!-- Main Content Layout -->
    <main class="flex-1 py-8 px-4 sm:px-6">
        <div class="print-container max-w-5xl mx-auto bg-white shadow-xl border border-slate-200 rounded-xl overflow-hidden p-6 sm:p-10 transition-all duration-300">
            <!-- Include the dynamic layout view directly -->
            @include($view, $pdfData)
        </div>
    </main>

    <!-- Simple Footer -->
    <footer class="no-print bg-slate-950 text-slate-500 py-6 px-4 text-center text-xs border-t border-slate-900">
        <p>&copy; {{ date('Y') }} Antigravity ERP. Secured by 64-character encrypted tokens.</p>
    </footer>

</body>
</html>
