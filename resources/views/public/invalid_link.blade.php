<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Not Available - Antigravity ERP</title>
    <!-- Premium Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Plus+Jakarta+Sans:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                        outfit: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#0f172a] text-[#f8fafc] min-h-screen flex items-center justify-center p-6 antialiased font-sans">
    <div class="relative w-full max-w-md bg-slate-900/50 border border-slate-800/80 backdrop-blur-xl rounded-2xl p-8 text-center shadow-2xl overflow-hidden">
        <!-- Sleek Ambient Light Gradients -->
        <div class="absolute -top-16 -left-16 w-32 h-32 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-16 -right-16 w-32 h-32 bg-rose-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Warning Icon Outer Ring -->
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-rose-500/10 border border-rose-500/20 mb-6 animate-pulse">
            <!-- Alert Triangle Icon -->
            <svg class="w-10 h-10 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>

        <h1 class="font-outfit text-2xl font-bold tracking-tight text-white mb-3">Link No Longer Available</h1>
        <p class="text-slate-400 text-sm leading-relaxed mb-8">
            This secure shared link has either expired, been revoked, or is invalid. Please contact the administrator to request a new link.
        </p>

        <div class="pt-6 border-t border-slate-800/80 flex flex-col gap-3">
            <a href="https://portal.modormc.com" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white font-semibold text-xs tracking-wider uppercase rounded-lg shadow-lg hover:shadow-indigo-500/25 transition-all duration-300">
                Go to Portal Login
            </a>
        </div>
    </div>
</body>
</html>
