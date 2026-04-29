{{-- Floating Print Button for Browser View --}}
<div class="print-actions no-print">
    <button onclick="window.print()" class="btn-print-trigger">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.2rem;height:1.2rem;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.618 0-1.113-.491-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.658" />
        </svg>
        <span>Print Document</span>
    </button>
</div>

<style>
    @media print {
        .no-print { display: none !important; }
    }
    .print-actions {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 9999;
    }
    .btn-print-trigger {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #4f46e5;
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 99px;
        font-family: 'Inter', sans-serif;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
        transition: all 0.2s;
    }
    .btn-print-trigger:hover {
        background: #4338ca;
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px rgba(79, 70, 229, 0.4);
    }
</style>
