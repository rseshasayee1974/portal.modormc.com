<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import Swal2 from 'sweetalert2';
import axios from 'axios';
import apexchart from 'vue3-apexcharts';
import {
    CpuChipIcon,
    PlusIcon,
    ChatBubbleLeftEllipsisIcon,
    XMarkIcon,
    ArrowPathIcon,
    BookOpenIcon,
    PuzzlePieceIcon,
    ClockIcon,
    ChatBubbleOvalLeftIcon,
} from '@heroicons/vue/24/outline';

interface Agent {
    name: string;
    class: string;
    instructions: string;
    is_structured: boolean;
    tools: string[];
}

const props = defineProps<{
    agents: Agent[];
}>();

const page = usePage();

// Drawer/Playground State
const selectedAgent = ref<Agent | null>(null);
const isDrawerOpen = ref(false);
const chatMessages = ref<{ role: 'user' | 'agent' | 'error'; text: string; provider?: string }[]>([]);
const currentPrompt = ref('');
const isSubmitting = ref(false);
const currentChatSessionId = ref<number | null>(null);

// Speech Recognition State
const isListening = ref(false);
const recognitionError = ref<string | null>(null);
let activeRecognition: any = null;

const selectedCategory = ref('');

const availableCategories = computed(() => {
    const isTamil = preferredLanguage.value === 'ta';
    if (selectedAgent.value?.name === 'Accountant') {
        return [
            { id: 'billing_invoices', label: isTamil ? 'பில்லிங் & இன்வாய்ஸ்கள்' : 'Billing & Invoices' },
            { id: 'financial_reports', label: isTamil ? 'நிதி அறிக்கைகள்' : 'Financial Reports' },
            { id: 'expenses_inventory', label: isTamil ? 'செலவுகள் & இருப்பு' : 'Expenses & Inventory' },
            { id: 'accounting_guide', label: isTamil ? 'கணக்கியல் வழிகாட்டி' : 'Accounting Guide' }
        ];
    }
    return [
        { id: 'operations_stock', label: isTamil ? 'செயல்பாடுகள் & இருப்பு' : 'Operations & Stock' },
        { id: 'troubleshooting', label: isTamil ? 'பழுது நீக்குதல்' : 'Troubleshooting' }
    ];
});

const openPlayground = (agent: Agent) => {
    currentChatSessionId.value = null;
    selectedAgent.value = agent;
    chatMessages.value = [
        { role: 'agent', text: `Hi, I am **${agent.name}**. I am initialized and ready to follow my instructions. How can I help you today?` }
    ];
    if (agent.name === 'Accountant') {
        selectedCategory.value = 'billing_invoices';
    } else {
        selectedCategory.value = 'operations_stock';
    }
    isDrawerOpen.value = true;
};

// Speech Synthesis State
const currentlySpeakingMessageIndex = ref<number | null>(null);
let currentUtterance: SpeechSynthesisUtterance | null = null;
const preferredLanguage = ref<'auto' | 'en' | 'ta'>('auto');
const voicesList = ref<SpeechSynthesisVoice[]>([]);

if (typeof window !== 'undefined' && window.speechSynthesis) {
    const loadVoices = () => {
        voicesList.value = window.speechSynthesis.getVoices();
    };
    loadVoices();
    if (window.speechSynthesis.onvoiceschanged !== undefined) {
        window.speechSynthesis.onvoiceschanged = loadVoices;
    }
}

const toggleSpeech = (text: string, index: number) => {
    if (currentlySpeakingMessageIndex.value === index) {
        window.speechSynthesis.cancel();
        currentlySpeakingMessageIndex.value = null;
        currentUtterance = null;
        return;
    }

    window.speechSynthesis.cancel();

    // Clean up Markdown links, tables, hashes, tabs, and newlines to prevent SpeechSynthesis engines from hanging/crashing
    let cleanText = text
        .replace(/```json[\s\S]*?```/g, '')
        .replace(/```[\s\S]*?```/g, '')
        .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1') // Strip Markdown URLs, keep text
        .replace(/\|/g, ' ')
        .replace(/\*/g, '')
        .replace(/`/g, '')
        .replace(/#/g, '')
        .replace(/-/g, ' ')
        .replace(/\t/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    if (!cleanText) return;

    const utterance = new SpeechSynthesisUtterance(cleanText);
    currentUtterance = utterance;
    currentlySpeakingMessageIndex.value = index;

    const containsTamil = /[\u0B80-\u0BFF]/.test(cleanText);
    const voices = voicesList.value.length > 0 ? voicesList.value : window.speechSynthesis.getVoices();
    let selectedVoice = null;

    // Use Tamil voice only if the response actually contains Tamil characters
    const targetLang = preferredLanguage.value === 'auto'
        ? (containsTamil ? 'ta' : 'en')
        : (preferredLanguage.value === 'ta' && !containsTamil ? 'en' : preferredLanguage.value);

    if (targetLang === 'ta') {
        utterance.lang = 'ta-IN';
        selectedVoice = voices.find(v => {
            const lang = v.lang.toLowerCase();
            const name = v.name.toLowerCase();
            return lang.startsWith('ta') || lang.includes('ta-in') || name.includes('tamil') || name.includes('valluvar');
        });
    } else {
        utterance.lang = 'en-IN';
        selectedVoice = voices.find(v => {
            const lang = v.lang.toLowerCase();
            return lang.includes('en-in') || lang.startsWith('en');
        });
    }

    if (selectedVoice) {
        utterance.voice = selectedVoice;
    }

    utterance.onend = () => {
        if (currentlySpeakingMessageIndex.value === index) {
            currentlySpeakingMessageIndex.value = null;
            currentUtterance = null;
        }
    };

    utterance.onerror = (e) => {
        console.error('SpeechSynthesis error:', e);
        currentlySpeakingMessageIndex.value = null;
        currentUtterance = null;
    };

    window.speechSynthesis.speak(utterance);
};

// ── Chat History State ────────────────────────────────────────────────────────
const isHistoryPanelOpen = ref(false);
const historyList = ref<any[]>([]);
const historyCurrentPage = ref(1);
const historyLastPage = ref(1);
const historyLoading = ref(false);
const historyViewSession = ref<any | null>(null); // full session being replayed

const saveHistoryToServer = async () => {
    // Only save if there are actual user messages (skip greeting-only sessions)
    const hasUserMsg = chatMessages.value.some(m => m.role === 'user');
    if (!hasUserMsg || !selectedAgent.value) return;

    try {
        const response = await axios.post(route('settings.agents.history.store'), {
            id:               currentChatSessionId.value,
            agent_name:       selectedAgent.value.name,
            agent_class:      selectedAgent.value.class,
            session_language: preferredLanguage.value,
            messages:         chatMessages.value,
        });
        if (response.data.success && response.data.id) {
            currentChatSessionId.value = response.data.id;
        }
    } catch (e: any) {
        console.error('Failed to save history:', e.response?.data || e);
    }
};

const closePlayground = async () => {
    window.speechSynthesis.cancel();
    currentlySpeakingMessageIndex.value = null;
    currentUtterance = null;
    stopSpeechRecognition();
    await saveHistoryToServer();
    isDrawerOpen.value = false;
    selectedAgent.value = null;
    chatMessages.value = [];
    currentPrompt.value = '';
    currentChatSessionId.value = null;
};

const fetchHistory = async (page = 1) => {
    historyLoading.value = true;
    try {
        const res = await axios.get(route('settings.agents.history.index'), {
            params: { page },
        });
        historyList.value = res.data.data;
        historyCurrentPage.value = res.data.current_page;
        historyLastPage.value = res.data.last_page;
    } catch (e) {
        // ignore
    } finally {
        historyLoading.value = false;
    }
};

const openHistoryPanel = () => {
    isHistoryPanelOpen.value = true;
    historyViewSession.value = null;
    fetchHistory(1);
};

const closeHistoryPanel = () => {
    isHistoryPanelOpen.value = false;
    historyViewSession.value = null;
};

const viewHistorySession = async (id: number) => {
    try {
        const res = await axios.get(route('settings.agents.history.show', { history: id }));
        historyViewSession.value = res.data;
    } catch (e) {
        // ignore
    }
};

const formatHistoryDate = (ts: string) => {
    if (!ts) return '';
    const d = new Date(ts);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
};



// ── Speech Recognition (Voice Input) ─────────────────────────────────────────
const startSpeechRecognition = () => {
    if (typeof window === 'undefined') return;
    const SpeechRecognition = (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition;
    if (!SpeechRecognition) {
        recognitionError.value = 'உங்கள் browser-ல் voice input ஆதரிக்கப்படவில்லை. Chrome / Edge ஐ முயற்சிக்கவும்.';
        return;
    }

    // Stop any ongoing synthesis while recording
    window.speechSynthesis.cancel();
    currentlySpeakingMessageIndex.value = null;

    const recognition = new SpeechRecognition();
    activeRecognition = recognition;

    // Map preferred language to BCP-47 locale
    const langMap: Record<string, string> = { ta: 'ta-IN', en: 'en-IN', auto: 'ta-IN' };
    recognition.lang = langMap[preferredLanguage.value] ?? 'ta-IN';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    recognition.continuous = false;

    recognitionError.value = null;
    isListening.value = true;

    recognition.onresult = (event: any) => {
        const transcript = event.results[0][0].transcript;
        currentPrompt.value = (currentPrompt.value ? currentPrompt.value + ' ' : '') + transcript;
        isListening.value = false;
        activeRecognition = null;
    };

    recognition.onerror = (event: any) => {
        if (event.error === 'aborted') {
            // User clicked stop — silent exit
        } else if (event.error === 'no-speech') {
            recognitionError.value = 'குரல் கண்டுபிடிக்கப்படவில்லை. மீண்டும் முயற்சிக்கவும்.';
        } else if (event.error === 'not-allowed') {
            recognitionError.value = 'Microphone அனுமதி மறுக்கப்பட்டது. Browser settings-ல் allow செய்யவும்.';
        } else {
            recognitionError.value = `Recognition error: ${event.error}`;
        }
        isListening.value = false;
        activeRecognition = null;
    };

    recognition.onend = () => {
        isListening.value = false;
        activeRecognition = null;
    };

    recognition.start();
};

const stopSpeechRecognition = () => {
    if (activeRecognition) {
        try { activeRecognition.abort(); } catch (_) {}
        activeRecognition = null;
    }
    isListening.value = false;
};

const toggleSpeechRecognition = () => {
    if (isListening.value) {
        stopSpeechRecognition();
    } else {
        startSpeechRecognition();
    }
};

const sendTestPrompt = async () => {
    if (!currentPrompt.value.trim() || isSubmitting.value || !selectedAgent.value) return;

    const userText = currentPrompt.value;
    chatMessages.value.push({ role: 'user', text: userText });
    currentPrompt.value = '';
    isSubmitting.value = true;

    // Save user's message immediately
    saveHistoryToServer();

    try {
        const response = await axios.post(route('settings.agents.test'), {
            prompt: userText,
            agent_class: selectedAgent.value.class,
        });

        const data = response.data;

        if (data.success) {
            let reply = data.response;
            if (typeof reply === 'object' && reply !== null) {
                if ('value' in reply && Object.keys(reply).length === 1) {
                    reply = reply.value;
                } else {
                    reply = '```json\n' + JSON.stringify(reply, null, 2) + '\n```';
                }
            }
            chatMessages.value.push({ role: 'agent', text: reply, provider: data.provider });
        } else {
            chatMessages.value.push({
                role: 'error',
                text: data.error || 'An error occurred while executing the prompt.',
            });
        }
    } catch (err: any) {
        const errMsg = err.response?.data?.error || err.response?.data?.message || err.message || 'Network request failed.';
        chatMessages.value.push({
            role: 'error',
            text: errMsg,
        });
    } finally {
        isSubmitting.value = false;
        // Save agent's reply or error response
        saveHistoryToServer();
    }
};

const activeQuestions = computed(() => {
    const isTamil = preferredLanguage.value === 'ta';
    if (selectedAgent.value?.name === 'Accountant') {
        switch (selectedCategory.value) {
            case 'billing_invoices':
                return isTamil ? [
                    'வருவாய் ஆதாரங்கள் என்ன?',
                    'வாடிக்கையாளர் பணத்தைத் திருப்பித் தருவதற்கான சரியான ஜர்னல் என்ட்ரி என்ன?',
                    'accounts receivable aging-ஐ எவ்வாறு நிர்வகிப்பது?',
                    'deferred revenue-க்கு என்ன கணக்கியல் சிகிச்சை அளிக்க வேண்டும்?'
                ] : [
                    'What are the main revenue streams for this project?',
                    'What is the correct journal entry for a customer refund?',
                    'How do we manage accounts receivable aging?',
                    'What accounting treatment should we apply for deferred revenue?'
                ];
            case 'financial_reports':
                return isTamil ? [
                    'மாதாந்திர இலாப நட்டக் கணக்கு சுருக்கத்தை உருவாக்க முடியுமா?',
                    'தற்போதைய பணப்புழக்க நிலை என்ன?',
                    'கண்காணிக்க வேண்டிய முக்கிய நிதி விகிதங்கள் யாவை?',
                    'budget vs actual வேறுபாட்டு பகுப்பாய்வை வழங்க முடியுமா?'
                ] : [
                    'Can you generate a monthly profit and loss summary?',
                    'What is the current cash flow status?',
                    'What are the key financial ratios we should monitor?',
                    'Can you provide a budget vs actual variance analysis?'
                ];
            case 'expenses_inventory':
                return isTamil ? [
                    'செலவுகளை எவ்வாறு வகைப்படுத்துவது?',
                    'இந்த திட்டத்திற்கான செலவு மையங்களை அடையாளம் காண முடியுமா?',
                    'திட்டம் சார்ந்த சரக்கு செலவுகளை எவ்வாறு கண்காணிப்பது?',
                    'செலவு ஒப்புதலுக்கு என்ன உள் கட்டுப்பாடுகள் பரிந்துரைக்கப்படுகின்றன?'
                ] : [
                    'How should we classify project expenses in the chart of accounts?',
                    'Can you identify cost centers for this project?',
                    'How should we track project-related inventory costs?',
                    'What internal controls are recommended for expense approval?'
                ];
            case 'accounting_guide':
                return isTamil ? [
                    'வங்கி பரிவர்த்தனைகளை லெட்ஜருடன் எவ்வாறு சரிசெய்வது?',
                    'prepaid மற்றும் accrued செலவுகளை எவ்வாறு பதிவு செய்வது?',
                    'தேய்மானத்தை எவ்வாறு கணக்கிட்டு அறிக்கை செய்வது?',
                    'வருவாய் அங்கீகாரக் கொள்கையின் ஜிஎஸ்டி தாக்கங்கள் யாவை?',
                    'indirect செலவுகளை பட்ஜெட்டுகளுக்கு எவ்வாறு ஒதுக்குவது?',
                    'capital expenditures-க்கான சரியான கணக்கியல் என்ன?',
                    'liabilities மற்றும் contingent liabilities-ஐ எவ்வாறு அறிக்கை செய்வது?',
                    'மாதாந்திர புத்தகங்களை மூடுவதற்கான முக்கிய படிகள் யாவை?'
                ] : [
                    'How can we reconcile bank transactions with the general ledger?',
                    'How do we record prepaid expenses versus accrued expenses?',
                    'How should we calculate and report depreciation for fixed assets?',
                    'What are the tax implications of the current revenue recognition policy?',
                    'How do we allocate indirect costs to project budgets?',
                    'What is the proper accounting for capital expenditures?',
                    'How should we report liabilities and contingent liabilities?',
                    'Can you summarize the key steps for closing the monthly books?'
                ];
            default:
                return [];
        }
    }
    
    switch (selectedCategory.value) {
        case 'operations_stock':
            return isTamil ? [
                'தயாரிப்பு பட்டியல் மற்றும் விலைகளைக் காட்டு',
                'தற்போதைய இருப்பு அளவைக் காட்டு',
                'இன்றைய விற்பனை மற்றும் கொள்முதல் சுருக்கத்தைக் காட்டு',
                'இன்வாய்ஸ் செய்யப்படாத டிஸ்பாட்ச்கள் எத்தனை உள்ளன?',
                'மொத்த செலவுகள் மற்றும் போக்குவரத்து செலவுகளைக் காட்டு',
                'கணக்கியல் லெட்ஜர்களைப் பட்டியலிடு',
                'டிரைவர் விவரங்கள் மற்றும் டிஸ்பாட்ச்களைக் காட்டு'
            ] : [
                'Show active products and prices',
                'Show current physical stock levels',
                'Show today\'s sales and purchases summary',
                'How many uninvoiced dispatches do we have?',
                'Show total expenses and transport costs',
                'List our accounting ledgers',
                'Show driver details and completed dispatches'
            ];
        case 'troubleshooting':
            return isTamil ? [
                'weighbridge சீரியல் போர்ட் எடைக் சிக்கலை எவ்வாறு சரிசெய்வது?',
                'mixer லாரிகள் வெளியேறின ஆனால் geofence பதிவுகள் வரவில்லை',
                'இன்வாய்ஸ்களில் டெபிட்/கிரெடிட் பொருந்தா பிழைகளை எவ்வாறு தீர்ப்பது?'
            ] : [
                'How do I fix a weighbridge serial port weight issue?',
                'Mixer trucks exited but geofence logs did not trigger',
                'How to resolve mismatched debit/credit errors on invoices?'
            ];
        default:
            return [];
    }
});

const runPredefinedQuestion = (q: string) => {
    currentPrompt.value = q;
    sendTestPrompt();
};

const formatInstructions = (text: string) => {
    if (!text) return '—';
    return text.length > 120 ? text.slice(0, 120) + '…' : text;
};

const formatMarkdown = (text: string) => {
    if (!text) return '';
    // Escape HTML first to prevent XSS
    let escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    
    // Parse Markdown Tables
    const lines = escaped.split('\n');
    let inTable = false;
    let tableHtml = '';
    let headerLength = 0;
    
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        if (line.startsWith('|') && line.endsWith('|')) {
            const cells = line.split('|').map(c => c.trim()).slice(1, -1);
            const isSeparator = cells.every(c => /^:?-+:?$/.test(c));
            
            if (isSeparator) {
                lines[i] = '<!-- TABLE_ROW -->';
                continue;
            }
            
            if (!inTable) {
                inTable = true;
                tableHtml = '<div class="overflow-x-auto my-3 border border-slate-200 rounded-xl shadow-sm"><table class="min-w-full text-xs text-left border-collapse bg-white">';
                tableHtml += '<thead class="bg-indigo-50/70 border-b border-slate-200 text-indigo-850"><tr>';
                cells.forEach(cell => {
                    tableHtml += `<th class="px-3 py-2 font-black uppercase tracking-wider">${cell}</th>`;
                });
                tableHtml += '</tr></thead><tbody class="divide-y divide-slate-100">';
                headerLength = cells.length;
            } else {
                tableHtml += '<tr class="hover:bg-slate-50/50 transition-colors">';
                for (let j = 0; j < headerLength; j++) {
                    const val = cells[j] || '';
                    const isNumeric = val.startsWith('₹') || /^\d+/.test(val) || val === '0.00' || val.endsWith('%');
                    const alignClass = isNumeric ? 'text-right' : 'text-left';
                    tableHtml += `<td class="px-3 py-2 text-slate-700 font-semibold whitespace-nowrap ${alignClass}">${val}</td>`;
                }
                tableHtml += '</tr>';
            }
            lines[i] = '<!-- TABLE_ROW -->';
        } else {
            if (inTable) {
                inTable = false;
                tableHtml += '</tbody></table></div>';
                let lastTableRowIdx = -1;
                for (let k = 0; k < i; k++) {
                    if (lines[k] === '<!-- TABLE_ROW -->') {
                        lastTableRowIdx = k;
                    }
                }
                if (lastTableRowIdx !== -1) {
                    lines[lastTableRowIdx] = tableHtml;
                }
            }
        }
    }
    
    if (inTable) {
        tableHtml += '</tbody></table></div>';
        let lastTableRowIdx = -1;
        for (let k = 0; k < lines.length; k++) {
            if (lines[k] === '<!-- TABLE_ROW -->') {
                lastTableRowIdx = k;
            }
        }
        if (lastTableRowIdx !== -1) {
            lines[lastTableRowIdx] = tableHtml;
        }
    }
    
    let formattedText = lines.filter(l => l !== '<!-- TABLE_ROW -->').join('\n');
    
    // Bold
    formattedText = formattedText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    // Italics
    formattedText = formattedText.replace(/\*(.*?)\*/g, '<em>$1</em>');
    // Inline code
    formattedText = formattedText.replace(/`(.*?)`/g, '<code class="bg-indigo-50 text-indigo-700 px-1 py-0.5 rounded font-mono text-xs font-semibold">$1</code>');
    // Bullet points (handle lines starting with - or *)
    formattedText = formattedText.replace(/^\s*[-*]\s+(.*?)$/gm, '<li class="ml-4 list-disc mt-1 text-slate-750">$1</li>');

    return formattedText;
};

// Provider Styling and Icons helpers
const getProviderClass = (provider?: string) => {
    if (!provider) return 'bg-indigo-50/70 text-indigo-750 border-indigo-200/60';
    const p = provider.toLowerCase();
    if (p.includes('gemini') || p.includes('google')) {
        return 'bg-blue-50/70 text-blue-700 border-blue-200/60';
    }
    if (p.includes('openai') || p.includes('chatgpt') || p.includes('gpt')) {
        return 'bg-emerald-50/70 text-emerald-700 border-emerald-200/60';
    }
    if (p.includes('groq')) {
        return 'bg-orange-50/70 text-orange-700 border-orange-200/60';
    }
    if (p.includes('ollama')) {
        return 'bg-slate-100 text-slate-800 border-slate-350';
    }
    return 'bg-indigo-50/70 text-indigo-750 border-indigo-200/60';
};

const getProviderIcon = (provider?: string) => {
    if (!provider) return '';
    const p = provider.toLowerCase();
    if (p.includes('gemini') || p.includes('google')) {
        return `<svg viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 text-blue-500">
            <path d="M12 2a1 1 0 0 1 1 1v3.5a5.5 5.5 0 0 0 5.5 5.5H22a1 1 0 0 1 0 2h-3.5a5.5 5.5 0 0 0-5.5 5.5V22a1 1 0 0 1-2 0v-3.5A5.5 5.5 0 0 0 5.5 13H2a1 1 0 0 1 0-2h3.5A5.5 5.5 0 0 0 11 5.5V3a1 1 0 0 1 1-1Z" />
        </svg>`;
    }
    if (p.includes('openai') || p.includes('chatgpt') || p.includes('gpt')) {
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-emerald-600">
            <circle cx="12" cy="12" r="10" />
            <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
            <path d="M2 12h20" />
        </svg>`;
    }
    if (p.includes('groq')) {
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-orange-600">
            <path d="M12 2a10 10 0 1 0 10 10H12V2z"/>
            <path d="M12 12L19 5"/>
        </svg>`;
    }
    if (p.includes('ollama')) {
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-slate-600">
            <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
        </svg>`;
    }
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="w-3.5 h-3.5 text-indigo-500">
        <rect x="4" y="4" width="16" height="16" rx="2" />
        <path d="M9 9h6v6H9zM9 1v3M15 1v3M9 20v3M15 20v3M20 9h3M20 15h3M1 9h3M1 15h3" />
    </svg>`;
};

const formatProviderName = (provider?: string) => {
    if (!provider) return '';
    const p = provider.toLowerCase();
    if (p.includes('gemini')) return 'Gemini';
    if (p.includes('openai') || p.includes('chatgpt') || p.includes('gpt')) return 'ChatGPT';
    if (p.includes('groq')) return 'Groq';
    if (p.includes('ollama')) return 'Ollama';
    return provider.charAt(0).toUpperCase() + provider.slice(1);
};

// Chart parsing and data extraction helpers
const parseMessageContent = (text: string) => {
    if (!text) return { hasChart: false, text: '' };
    
    const chartMarkerIndex = text.toLowerCase().indexOf('`chart');
    const threeBackticksIndex = text.toLowerCase().indexOf('```chart');
    
    let markerIndex = -1;
    let markerLength = 0;
    
    if (threeBackticksIndex !== -1) {
        markerIndex = threeBackticksIndex;
        markerLength = 8;
    } else if (chartMarkerIndex !== -1) {
        markerIndex = chartMarkerIndex;
        markerLength = 6;
    }
    
    if (markerIndex !== -1) {
        const startBrace = text.indexOf('{', markerIndex + markerLength);
        if (startBrace !== -1) {
            const endBrace = text.lastIndexOf('}');
            if (endBrace !== -1 && endBrace > startBrace) {
                const jsonString = text.substring(startBrace, endBrace + 1).trim();
                const beforeText = text.substring(0, markerIndex);
                
                let afterTextStart = endBrace + 1;
                const possibleClosing = text.substring(afterTextStart, afterTextStart + 10);
                const closingMatch = possibleClosing.match(/^\s*```|^\s*`/);
                if (closingMatch) {
                    afterTextStart += closingMatch[0].length;
                }
                const afterText = text.substring(afterTextStart);
                
                try {
                    const chartData = JSON.parse(jsonString);
                    return {
                        hasChart: true,
                        beforeText,
                        afterText,
                        chartData
                    };
                } catch (e) {
                    console.error('Failed to parse chart JSON:', e);
                }
            }
        }
    }
    
    return {
        hasChart: false,
        text
    };
};

const normalizeChartType = (type: string): string => {
    if (!type) return 'bar';
    const t = type.toLowerCase().trim();
    if (t === 'doughnut') return 'donut';
    if (t === 'radius' || t === 'radious' || t === 'radial') return 'radialBar';
    return t;
};

const overriddenChartTypes = ref<Record<string, string>>({});

const getChartType = (msgText: string, key: string | number): string => {
    const overrideKey = String(key);
    if (overriddenChartTypes.value[overrideKey]) {
        return overriddenChartTypes.value[overrideKey];
    }
    const chartData = parseMessageContent(msgText).chartData;
    let parsedType = chartData?.chartType;
    if (!parsedType) {
        const types = ['bar', 'line', 'area', 'radar', 'pie', 'donut', 'radialBar'];
        let seed = 0;
        const keyStr = String(key);
        for (let i = 0; i < keyStr.length; i++) {
            seed += keyStr.charCodeAt(i);
        }
        const index = seed % types.length;
        parsedType = types[index];
    }
    return normalizeChartType(parsedType);
};

const setChartOverride = (key: string | number, type: string) => {
    overriddenChartTypes.value[String(key)] = type;
};

const randomizeChartType = (key: string | number) => {
    const types = ['bar', 'line', 'area', 'radar', 'pie', 'donut', 'radialBar'];
    const current = getChartType('', key);
    const available = types.filter(t => t !== current);
    const randomType = available[Math.floor(Math.random() * available.length)];
    setChartOverride(key, randomType);
};

const getChartOptions = (chartData: any, type: string) => {
    const normalizedType = normalizeChartType(type);
    const isCircular = ['pie', 'donut', 'radialBar'].includes(normalizedType);

    const baseOptions: any = {
        chart: {
            background: 'transparent',
            foreColor: '#94a3b8',
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        theme: {
            mode: 'dark'
        },
        colors: chartData.colors || ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#a855f7', '#ec4899', '#06b6d4'],
        tooltip: {
            theme: 'dark'
        },
        legend: {
            position: 'bottom',
            labels: {
                colors: '#94a3b8'
            }
        }
    };

    if (isCircular) {
        baseOptions.labels = chartData.labels || [];
        if (normalizedType === 'radialBar') {
            baseOptions.plotOptions = {
                radialBar: {
                    dataLabels: {
                        name: {
                            show: true,
                            fontSize: '13px',
                            color: '#94a3b8'
                        },
                        value: {
                            show: true,
                            fontSize: '15px',
                            color: '#f8fafc',
                            formatter: function (val: any) {
                                return val;
                            }
                        },
                        total: {
                            show: true,
                            label: 'Total',
                            color: '#94a3b8',
                            formatter: function (w: any) {
                                const series = w.globals.series || [];
                                const total = series.reduce((a: number, b: number) => a + b, 0);
                                return total.toLocaleString('en-IN');
                            }
                        }
                    }
                }
            };
        } else if (normalizedType === 'donut') {
            baseOptions.plotOptions = {
                pie: {
                    donut: {
                        labels: {
                            show: true,
                            name: {
                                show: true,
                                color: '#94a3b8'
                            },
                            value: {
                                show: true,
                                color: '#f8fafc',
                                formatter: function (val: any) {
                                    return Number(val).toLocaleString('en-IN');
                                }
                            },
                            total: {
                                show: true,
                                color: '#94a3b8',
                                formatter: function (w: any) {
                                    const series = w.globals.series || [];
                                    const total = series.reduce((a: number, b: number) => a + b, 0);
                                    return total.toLocaleString('en-IN');
                                }
                            }
                        }
                    }
                }
            };
        }
    } else {
        baseOptions.xaxis = {
            categories: chartData.labels || [],
            labels: {
                style: {
                    colors: '#94a3b8',
                    fontSize: '10px'
                }
            }
        };
        
        baseOptions.yaxis = {
            labels: {
                style: {
                    colors: '#94a3b8',
                    fontSize: '10px'
                },
                formatter: function (val: any) {
                    return Number(val).toLocaleString('en-IN');
                }
            }
        };

        baseOptions.grid = {
            borderColor: '#334155',
            strokeDashArray: 4
        };

        baseOptions.stroke = {
            curve: 'smooth',
            width: ['line', 'area', 'radar'].includes(normalizedType) ? 3 : 0
        };

        if (normalizedType === 'radar') {
            baseOptions.plotOptions = {
                radar: {
                    polygons: {
                        strokeColors: '#334155',
                        connectorColors: '#334155',
                        fill: {
                            colors: ['#1e293b', '#0f172a']
                        }
                    }
                }
            };
        }
    }

    return baseOptions;
};

const getChartSeries = (chartData: any, type: string) => {
    const normalizedType = normalizeChartType(type);
    
    let rawSeries: any[] = [];
    if (chartData.series) {
        rawSeries = chartData.series;
    } else if (chartData.datasets) {
        rawSeries = chartData.datasets.map((d: any) => ({
            name: d.label || 'Data',
            data: d.data || []
        }));
    } else if (chartData.data) {
        rawSeries = [{
            name: 'Value',
            data: chartData.data
        }];
    }

    const isCircular = ['pie', 'donut', 'radialBar'].includes(normalizedType);
    if (isCircular) {
        if (rawSeries.length > 0) {
            if (typeof rawSeries[0] === 'object' && rawSeries[0] !== null && 'data' in rawSeries[0]) {
                return rawSeries[0].data || [];
            }
            return rawSeries;
        }
        return [];
    } else {
        if (rawSeries.length > 0) {
            if (typeof rawSeries[0] === 'object' && rawSeries[0] !== null && 'data' in rawSeries[0]) {
                return rawSeries;
            } else {
                return [{
                    name: chartData.title || 'Data',
                    data: rawSeries
                }];
            }
        }
        return [];
    }
};

</script>

<template>
    <AppLayout title="AI Agent Directory">
        <template #header>
            <div class="flex items-center gap-4">
                <div class="p-3 bg-indigo-50 rounded-2xl">
                    <CpuChipIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Enterprise AI Core</p>
                    <h1 class="text-xl font-black tracking-tight text-slate-900">AI Agents</h1>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Stats Strip -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-center gap-4 shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <CpuChipIcon class="w-6 h-6 text-indigo-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Classes Registered</p>
                        <p class="text-3xl font-black text-slate-800">{{ agents.length }}</p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-center gap-4 shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <PuzzlePieceIcon class="w-6 h-6 text-emerald-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Active Integrations</p>
                        <p class="text-3xl font-black text-slate-800">
                            {{ agents.filter(a => a.tools.length > 0).length }}
                        </p>
                    </div>
                </div>
                <div class="bg-white rounded-2xl border border-slate-200 p-5 flex items-center gap-4 shadow-sm">
                    <div class="w-12 h-12 rounded-xl bg-amber-50 flex items-center justify-center">
                        <BookOpenIcon class="w-6 h-6 text-amber-500" />
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Output Modes</p>
                        <p class="text-xs font-bold text-slate-600 mt-1">
                            Structured: <span class="text-amber-600 font-extrabold">{{ agents.filter(a => a.is_structured).length }}</span> | 
                            Plain Text: <span class="text-slate-700 font-extrabold">{{ agents.filter(a => !a.is_structured).length }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- List Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-800">Registered SDK Agents</h3>
                        <p class="text-xs text-slate-400">All agent files compiled dynamically under <code>app/Ai/Agents/</code></p>
                    </div>
                    <Link :href="route('settings.agents.create')">
                        <BaseButton label="Create Agent" :icon="PlusIcon" severity="primary" />
                    </Link>
                </div>

                <BaseDataTable
                    :value="agents"
                    dataKey="name"
                    stripedRows
                    heading="Agent Inventory"
                    headingIcon="CpuChipIcon"
                    showSerial
                    paginator
                    :rows="10"
                    :totalRecords="agents.length"
                    class="p-datatable-sm"
                >
                    <Column header="Name" sortable field="name">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-bold text-slate-700 text-sm">{{ data.name }}</span>
                                <span class="text-[10px] text-slate-400 font-mono">{{ data.class }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Instructions" field="instructions">
                        <template #body="{ data }">
                            <span class="text-xs text-slate-500 max-w-sm block" :title="data.instructions">
                                {{ formatInstructions(data.instructions) }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Output Format" field="is_structured" sortable>
                        <template #body="{ data }">
                            <span v-if="data.is_structured" class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded border text-amber-700 bg-amber-50 border-amber-200">
                                Structured JSON
                            </span>
                            <span v-else class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded border text-slate-600 bg-slate-50 border-slate-200">
                                Plain Text
                            </span>
                        </template>
                    </Column>

                    <Column header="Tools Bound" field="tools">
                        <template #body="{ data }">
                            <div class="flex flex-wrap gap-1">
                                <span v-for="t in data.tools" :key="t" class="text-[9px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.5">
                                    {{ t }}
                                </span>
                                <span v-if="!data.tools.length" class="text-xs text-slate-450 italic">None</span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Actions" alignFrozen="right" frozen>
                        <template #body="{ data }">
                            <BaseButton
                                label="Chat Playground"
                                :icon="ChatBubbleLeftEllipsisIcon"
                                severity="secondary"
                                size="small"
                                class="!px-3 !py-1 !text-xs"
                                @click="openPlayground(data)"
                            />
                        </template>
                    </Column>
                </BaseDataTable>
            </div>
        </div>

        <!-- Chat Playground Sidebar Drawer -->
        <div v-if="isDrawerOpen" class="fixed inset-0 z-50 overflow-hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
            <div class="absolute inset-0 overflow-hidden">
                <!-- Overlay -->
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" @click="closePlayground"></div>

                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div class="pointer-events-auto w-screen max-w-md md:max-w-xl">
                        <div class="flex h-full flex-col bg-slate-50 shadow-2xl border-l border-slate-200">
                            <!-- Drawer Header -->
                            <div class="bg-white px-6 py-5 border-b border-slate-200 flex items-center justify-between shadow-sm">
                                <div class="flex items-center gap-3">
                                    <div class="p-2.5 bg-indigo-50 rounded-xl">
                                        <CpuChipIcon class="w-5 h-5 text-indigo-600" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-slate-900 leading-none">{{ selectedAgent?.name }}</h2>
                                        <span class="text-[9px] font-mono text-slate-450 uppercase tracking-widest mt-1 block">Live Playground</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <!-- Language toggle switch -->
                                    <div class="flex items-center gap-1 bg-slate-100 rounded-lg p-0.5 border border-slate-200">
                                        <button 
                                            v-for="lang in ['auto', 'en', 'ta']" 
                                            :key="lang"
                                            type="button"
                                            @click="preferredLanguage = lang"
                                            class="text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded transition-all cursor-pointer"
                                            :class="[
                                                preferredLanguage === lang 
                                                    ? 'bg-white text-indigo-700 shadow-sm' 
                                                    : 'text-slate-500 hover:text-slate-800'
                                            ]"
                                        >
                                            {{ lang === 'auto' ? 'Auto' : lang === 'en' ? 'English' : 'Tamil' }}
                                        </button>
                                    </div>
                                    <!-- History Button -->
                                    <button
                                        type="button"
                                        @click="openHistoryPanel"
                                        class="rounded-xl p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 transition-all cursor-pointer"
                                        title="Chat History"
                                    >
                                        <ClockIcon class="h-5 w-5" />
                                    </button>
                                    <button type="button" class="rounded-xl p-2 text-slate-400 hover:text-slate-650 hover:bg-slate-100 transition-all cursor-pointer" @click="closePlayground">
                                        <XMarkIcon class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Drawer Body: Chat History -->
                            <div class="flex-1 overflow-y-auto p-6 space-y-4">
                                <div v-for="(msg, idx) in chatMessages" :key="idx" class="flex flex-col" :class="[msg.role === 'user' ? 'items-end' : 'items-start']">
                                    <div class="max-w-[85%] rounded-2xl px-4 py-3 text-sm shadow-sm leading-relaxed"
                                         :class="[
                                             msg.role === 'user' 
                                                 ? 'bg-indigo-600 text-white rounded-br-none' 
                                                 : msg.role === 'error'
                                                 ? 'bg-red-50 text-red-700 border border-red-200 rounded-bl-none'
                                                 : 'bg-white text-slate-750 border border-slate-200 rounded-bl-none'
                                         ]"
                                    >
                                        <template v-if="msg.role === 'agent'">
                                            <!-- Parse chart if present -->
                                            <div v-if="parseMessageContent(msg.text).hasChart" class="w-full">
                                                <div class="whitespace-pre-wrap" v-html="formatMarkdown(parseMessageContent(msg.text).beforeText)"></div>
                                                
                                                <!-- Render Chart -->
                                                <div class="my-4 p-4 bg-slate-900 border border-slate-800 rounded-2xl shadow-inner text-white min-w-[280px]">
                                                    <div class="flex flex-col sm:flex-row items-center justify-between gap-2 mb-3 pb-2 border-b border-slate-800/60">
                                                        <p class="text-xs font-black uppercase tracking-widest text-indigo-400">
                                                            {{ parseMessageContent(msg.text).chartData.title || 'Data Analysis Chart' }}
                                                        </p>
                                                        <div class="flex items-center gap-1 bg-slate-800/80 rounded-lg p-0.5 border border-slate-700">
                                                            <button
                                                                v-for="t in ['bar', 'line', 'area', 'radar', 'pie', 'donut', 'radialBar']"
                                                                :key="t"
                                                                type="button"
                                                                @click="setChartOverride('live-' + idx, t)"
                                                                class="p-1 rounded transition-all hover:bg-slate-700 cursor-pointer text-slate-400"
                                                                :class="[getChartType(msg.text, 'live-' + idx) === t ? 'bg-indigo-600 !text-white' : '']"
                                                                :title="'Show as ' + t"
                                                            >
                                                                <svg v-if="t === 'bar'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 17v-4m4 4V9m4 8v-6m4 6V5" />
                                                                </svg>
                                                                <svg v-else-if="t === 'line'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19l4-4 4 4 8-8m0 0l-4 0m4 0l0 4" />
                                                                </svg>
                                                                <svg v-else-if="t === 'area'" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                                    <path d="M3 3v18h18v-2H5V3H3zm4 14l4-4 4 3 6-7v9H7z" />
                                                                </svg>
                                                                <svg v-else-if="t === 'radar'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l9 6v8l-9 6-9-6V8l9-6zm0 0v20m-9-10h18M5 8l14 8M5 16l14-8" />
                                                                </svg>
                                                                <svg v-else-if="t === 'pie'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                                                                </svg>
                                                                <svg v-else-if="t === 'donut'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <circle cx="12" cy="12" r="9" />
                                                                    <circle cx="12" cy="12" r="4" />
                                                                </svg>
                                                                <svg v-else-if="t === 'radialBar'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2M12 18C15.3137 18 18 15.3137 18 12C18 8.68629 15.3137 6 12 6" />
                                                                </svg>
                                                            </button>
                                                            <button
                                                                type="button"
                                                                @click="randomizeChartType('live-' + idx)"
                                                                class="p-1 rounded transition-all hover:bg-slate-700 cursor-pointer text-slate-400 border-l border-slate-700 pl-1.5"
                                                                title="Randomize Chart Type"
                                                            >
                                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.678 48.678 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3M3 12a9 9 0 0115 0" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <apexchart 
                                                        :type="getChartType(msg.text, 'live-' + idx)" 
                                                        height="260" 
                                                        :options="getChartOptions(parseMessageContent(msg.text).chartData, getChartType(msg.text, 'live-' + idx))" 
                                                        :series="getChartSeries(parseMessageContent(msg.text).chartData, getChartType(msg.text, 'live-' + idx))"
                                                    />
                                                </div>
                                                
                                                <div class="whitespace-pre-wrap" v-html="formatMarkdown(parseMessageContent(msg.text).afterText)"></div>
                                            </div>
                                            <!-- Default rendering if no chart -->
                                            <div v-else-if="msg.text.startsWith('```json')" class="font-mono text-xs">
                                                <pre class="overflow-x-auto whitespace-pre">{{ msg.text.replace(/```json\n|```/g, '') }}</pre>
                                            </div>
                                            <div v-else class="whitespace-pre-wrap" v-html="formatMarkdown(msg.text)"></div>
                                        </template>
                                        <template v-else>
                                            <div class="whitespace-pre-wrap">{{ msg.text }}</div>
                                        </template>
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-1 px-1">
                                        <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">
                                            {{ msg.role === 'user' ? 'You' : selectedAgent?.name }}
                                        </span>
                                        <!-- Provider Badge -->
                                        <span v-if="msg.role === 'agent' && msg.provider" 
                                              class="inline-flex items-center gap-1 text-[9px] font-bold px-1.5 py-0.5 rounded border shadow-sm"
                                              :class="getProviderClass(msg.provider)">
                                            <span v-html="getProviderIcon(msg.provider)" class="w-3.5 h-3.5 flex items-center justify-center shrink-0"></span>
                                            <span>{{ formatProviderName(msg.provider) }}</span>
                                        </span>
                                        <!-- Speaker Button for Agent responses -->
                                        <button 
                                            v-if="msg.role === 'agent'"
                                            type="button"
                                            @click="toggleSpeech(msg.text, idx)"
                                            class="p-0.5 rounded text-slate-400 hover:text-indigo-650 hover:bg-slate-200 transition-colors cursor-pointer"
                                            title="Listen to response"
                                        >
                                            <svg v-if="currentlySpeakingMessageIndex === idx" class="w-3.5 h-3.5 text-indigo-600 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                                            </svg>
                                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="isSubmitting" class="flex items-center gap-2 text-slate-400 text-xs px-2">
                                    <ArrowPathIcon class="w-4 h-4 animate-spin text-indigo-500" />
                                    <span>Agent is invoking tools and processing...</span>
                                </div>
                            </div>

                            <!-- Drawer Footer: Chat Input -->
                            <div class="bg-white border-t border-slate-200 px-6 py-4 shadow-inner">
                                <!-- Predefined Questions Categories -->
                                <div class="flex gap-1.5 mb-2.5 overflow-x-auto pb-1.5 border-b border-slate-100 scrollbar-none">
                                    <button 
                                        v-for="cat in availableCategories" 
                                        :key="cat.id"
                                        type="button"
                                        @click="selectedCategory = cat.id"
                                        class="text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded transition-all cursor-pointer whitespace-nowrap"
                                        :class="[
                                            selectedCategory === cat.id 
                                                ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 shadow-sm' 
                                                : 'text-slate-450 hover:text-slate-700 bg-slate-50 border border-slate-200'
                                        ]"
                                    >
                                        {{ cat.label }}
                                    </button>
                                </div>

                                <!-- Predefined Questions in Active Category -->
                                <div class="flex flex-wrap gap-1.5 mb-3">
                                    <button 
                                        v-for="q in activeQuestions" 
                                        :key="q"
                                        type="button"
                                        @click="runPredefinedQuestion(q)"
                                        :disabled="isSubmitting"
                                        class="text-[10px] font-medium text-indigo-700 bg-indigo-50/50 hover:bg-indigo-100 border border-indigo-100/60 rounded-full px-2.5 py-1 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {{ q }}
                                    </button>
                                </div>
                                <!-- Recognition error banner -->
                                <div v-if="recognitionError" class="mb-2 flex items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-3 py-2">
                                    <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                    <span class="text-[10px] font-semibold text-red-700 flex-1">{{ recognitionError }}</span>
                                    <button type="button" @click="recognitionError = null" class="text-red-400 hover:text-red-600 cursor-pointer">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>

                                <div class="flex gap-3 items-center">
                                    <BaseInput
                                        v-model="currentPrompt"
                                        :placeholder="preferredLanguage === 'ta' ? 'உங்கள் கேள்வியை தட்டச்சு செய்யுங்கள்...' : 'Type a message...'"
                                        fieldClass="flex-1"
                                        inputClass="!py-3 !rounded-xl"
                                        @keydown.enter="sendTestPrompt"
                                        :disabled="isSubmitting"
                                    />

                                    <!-- Microphone Button -->
                                    <button
                                        type="button"
                                        @click="toggleSpeechRecognition"
                                        :disabled="isSubmitting"
                                        :title="isListening ? (preferredLanguage === 'ta' ? 'நிறுத்து' : 'Stop listening') : (preferredLanguage === 'ta' ? 'குரல் உள்ளீடு' : 'Voice input')"
                                        class="relative flex-shrink-0 w-11 h-11 flex items-center justify-center rounded-xl border transition-all duration-200 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                        :class="isListening
                                            ? 'bg-red-500 border-red-400 shadow-lg shadow-red-200'
                                            : 'bg-slate-100 border-slate-200 hover:bg-indigo-50 hover:border-indigo-200'"
                                    >
                                        <!-- Pulsating ring when listening -->
                                        <span v-if="isListening" class="absolute inset-0 rounded-xl bg-red-400 animate-ping opacity-30"></span>

                                        <!-- Mic icon -->
                                        <svg :class="isListening ? 'text-white' : 'text-slate-500'" class="w-5 h-5 relative z-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                                        </svg>
                                    </button>

                                    <BaseButton
                                        label="Send"
                                        severity="primary"
                                        class="!rounded-xl"
                                        :loading="isSubmitting"
                                        @click="sendTestPrompt"
                                    />
                                </div>

                                <!-- Listening status indicator -->
                                <div v-if="isListening" class="mt-2 flex items-center gap-2">
                                    <span class="flex gap-0.5 items-end h-4">
                                        <span class="w-0.5 bg-red-500 rounded-full animate-bounce" style="height:6px; animation-delay:0ms"></span>
                                        <span class="w-0.5 bg-red-500 rounded-full animate-bounce" style="height:10px; animation-delay:100ms"></span>
                                        <span class="w-0.5 bg-red-500 rounded-full animate-bounce" style="height:14px; animation-delay:200ms"></span>
                                        <span class="w-0.5 bg-red-500 rounded-full animate-bounce" style="height:10px; animation-delay:300ms"></span>
                                        <span class="w-0.5 bg-red-500 rounded-full animate-bounce" style="height:6px; animation-delay:400ms"></span>
                                    </span>
                                    <span class="text-[10px] font-bold text-red-500 tracking-wide">
                                        {{ preferredLanguage === 'ta' ? 'கேட்கிறேன்... பேசுங்கள்' : 'Listening... speak now' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ Chat History Slide-Over Panel ══ -->
        <div v-if="isHistoryPanelOpen" class="fixed inset-0 z-[60] overflow-hidden" role="dialog" aria-modal="true">
            <div class="absolute inset-0 overflow-hidden">
                <!-- Overlay -->
                <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeHistoryPanel"></div>

                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div class="pointer-events-auto w-screen max-w-2xl">
                        <div class="flex h-full flex-col bg-white shadow-2xl border-l border-slate-200">

                            <!-- Panel Header -->
                            <div class="bg-gradient-to-r from-amber-50 to-orange-50 px-6 py-5 border-b border-amber-100 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="p-2.5 bg-amber-100 rounded-xl">
                                        <ClockIcon class="w-5 h-5 text-amber-600" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-slate-900">Chat History</h2>
                                        <p class="text-[10px] text-amber-700 font-semibold uppercase tracking-widest">Past conversations — stored for analysis</p>
                                    </div>
                                </div>
                                <button type="button" @click="closeHistoryPanel" class="rounded-xl p-2 text-slate-400 hover:text-slate-700 hover:bg-white/70 transition-all cursor-pointer">
                                    <XMarkIcon class="h-5 w-5" />
                                </button>
                            </div>

                            <!-- Two-column layout: list | session viewer -->
                            <div class="flex flex-1 min-h-0">

                                <!-- LEFT: Session List -->
                                <div class="w-80 shrink-0 flex flex-col border-r border-slate-100 bg-slate-50">
                                    <div class="px-4 py-3 border-b border-slate-100">
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Sessions</p>
                                    </div>

                                    <!-- Loading state -->
                                    <div v-if="historyLoading" class="flex-1 flex items-center justify-center">
                                        <div class="flex flex-col items-center gap-2">
                                            <ArrowPathIcon class="w-6 h-6 text-amber-500 animate-spin" />
                                            <span class="text-xs text-slate-400">Loading history...</span>
                                        </div>
                                    </div>

                                    <!-- Empty state -->
                                    <div v-else-if="historyList.length === 0" class="flex-1 flex items-center justify-center">
                                        <div class="text-center px-4">
                                            <ChatBubbleOvalLeftIcon class="w-10 h-10 text-slate-200 mx-auto mb-2" />
                                            <p class="text-xs text-slate-400 font-semibold">No chat history yet</p>
                                            <p class="text-[10px] text-slate-300 mt-1">Sessions will appear here after you close a conversation</p>
                                        </div>
                                    </div>

                                    <!-- List -->
                                    <div v-else class="flex-1 overflow-y-auto divide-y divide-slate-100">
                                        <button
                                            v-for="item in historyList"
                                            :key="item.id"
                                            type="button"
                                            @click="viewHistorySession(item.id)"
                                            class="w-full text-left px-4 py-3 hover:bg-white transition-colors group cursor-pointer"
                                            :class="historyViewSession?.id === item.id ? 'bg-white border-l-2 border-amber-400' : ''"
                                        >
                                            <!-- Agent + Language badge -->
                                            <div class="flex items-center justify-between mb-1">
                                                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.5">
                                                    {{ item.agent_name }}
                                                </span>
                                                <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded border"
                                                    :class="item.session_language === 'ta'
                                                        ? 'text-green-700 bg-green-50 border-green-100'
                                                        : 'text-slate-500 bg-slate-50 border-slate-200'"
                                                >
                                                    {{ item.session_language === 'ta' ? 'Tamil' : item.session_language === 'auto' ? 'Auto' : 'English' }}
                                                </span>
                                            </div>
                                            <!-- Summary -->
                                            <p class="text-[11px] text-slate-600 font-medium leading-snug line-clamp-2 mb-1.5">
                                                {{ item.summary || '(No summary)' }}
                                            </p>
                                            <!-- Meta -->
                                            <div class="flex items-center justify-between">
                                                <span class="text-[9px] text-slate-400">{{ item.message_count }} messages</span>
                                                <span class="text-[9px] text-slate-400">{{ formatHistoryDate(item.created_at) }}</span>
                                            </div>
                                        </button>
                                    </div>

                                    <!-- Paginator -->
                                    <div v-if="historyLastPage > 1" class="border-t border-slate-100 px-4 py-2 flex items-center justify-between">
                                        <button
                                            type="button"
                                            :disabled="historyCurrentPage <= 1"
                                            @click="fetchHistory(historyCurrentPage - 1)"
                                            class="text-[10px] font-bold px-2.5 py-1 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-all"
                                        >← Prev</button>
                                        <span class="text-[10px] text-slate-400 font-semibold">{{ historyCurrentPage }} / {{ historyLastPage }}</span>
                                        <button
                                            type="button"
                                            :disabled="historyCurrentPage >= historyLastPage"
                                            @click="fetchHistory(historyCurrentPage + 1)"
                                            class="text-[10px] font-bold px-2.5 py-1 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-all"
                                        >Next →</button>
                                    </div>
                                </div>

                                <!-- RIGHT: Session Viewer -->
                                <div class="flex-1 flex flex-col min-h-0">
                                    <!-- Empty state -->
                                    <div v-if="!historyViewSession" class="flex-1 flex items-center justify-center">
                                        <div class="text-center">
                                            <ClockIcon class="w-12 h-12 text-slate-150 mx-auto mb-3" />
                                            <p class="text-sm font-semibold text-slate-400">Select a session to view</p>
                                            <p class="text-xs text-slate-300 mt-1">Full conversation will appear here</p>
                                        </div>
                                    </div>

                                    <!-- Session detail -->
                                    <div v-else class="flex flex-col h-full">
                                        <!-- Session meta bar -->
                                        <div class="px-5 py-3 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.5">
                                        {{ historyViewSession.agent_name }}
                                                </span>
                                                <span class="text-[10px] text-slate-400 font-semibold">
                                                    {{ historyViewSession.message_count }} messages
                                                </span>
                                                <span class="text-[10px] text-slate-400">
                                                    {{ formatHistoryDate(historyViewSession.created_at) }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Messages replay -->
                                        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3 bg-slate-50/50">
                                            <div
                                                v-for="(msg, mi) in historyViewSession.messages"
                                                :key="mi"
                                                class="flex"
                                                :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                                            >
                                                <div
                                                    class="max-w-[82%] rounded-2xl px-4 py-2.5 text-xs leading-relaxed shadow-sm"
                                                    :class="[
                                                        msg.role === 'user'
                                                            ? 'bg-indigo-600 text-white rounded-br-none'
                                                            : msg.role === 'error'
                                                            ? 'bg-red-50 text-red-700 border border-red-200 rounded-bl-none'
                                                            : 'bg-white text-slate-700 border border-slate-200 rounded-bl-none'
                                                    ]"
                                                >
                                                    <!-- Role label -->
                                                     <div class="flex items-center justify-between gap-4 mb-1">
                                                         <p class="text-[8px] font-black uppercase tracking-widest opacity-60">
                                                             {{ msg.role === 'user' ? 'You' : historyViewSession.agent_name }}
                                                         </p>
                                                         <!-- Provider Badge in History -->
                                                         <span v-if="msg.role === 'agent' && msg.provider" 
                                                               class="inline-flex items-center gap-1 text-[8px] font-bold px-1.5 py-0.2 rounded border shadow-sm scale-90 origin-right"
                                                               :class="getProviderClass(msg.provider)">
                                                             <span v-html="getProviderIcon(msg.provider)" class="w-3 h-3 flex items-center justify-center shrink-0"></span>
                                                             <span>{{ formatProviderName(msg.provider) }}</span>
                                                         </span>
                                                     </div>
                                                    <template v-if="msg.role === 'agent'">
                                                        <!-- Parse chart if present -->
                                                        <div v-if="parseMessageContent(msg.text).hasChart" class="w-full">
                                                            <div class="whitespace-pre-wrap" v-html="formatMarkdown(parseMessageContent(msg.text).beforeText)"></div>
                                                            
                                                            <!-- Render Chart -->
                                                            <div class="my-4 p-4 bg-slate-900 border border-slate-800 rounded-2xl shadow-inner text-white min-w-[280px]">
                                                                <div class="flex flex-col sm:flex-row items-center justify-between gap-2 mb-2 pb-2 border-b border-slate-800/60">
                                                                    <p class="text-[10px] font-black uppercase tracking-widest text-indigo-400">
                                                                        {{ parseMessageContent(msg.text).chartData.title || 'Data Analysis Chart' }}
                                                                    </p>
                                                                    <div class="flex items-center gap-1 bg-slate-800/80 rounded-lg p-0.5 border border-slate-700 scale-90 origin-right">
                                                                        <button
                                                                            v-for="t in ['bar', 'line', 'area', 'radar', 'pie', 'donut', 'radialBar']"
                                                                            :key="t"
                                                                            type="button"
                                                                            @click="setChartOverride('history-' + mi, t)"
                                                                            class="p-1 rounded transition-all hover:bg-slate-700 cursor-pointer text-slate-400"
                                                                            :class="[getChartType(msg.text, 'history-' + mi) === t ? 'bg-indigo-600 !text-white' : '']"
                                                                            :title="'Show as ' + t"
                                                                        >
                                                                            <svg v-if="t === 'bar'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 17v-4m4 4V9m4 8v-6m4 6V5" />
                                                                            </svg>
                                                                            <svg v-else-if="t === 'line'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 19l4-4 4 4 8-8m0 0l-4 0m4 0l0 4" />
                                                                            </svg>
                                                                            <svg v-else-if="t === 'area'" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                                                                <path d="M3 3v18h18v-2H5V3H3zm4 14l4-4 4 3 6-7v9H7z" />
                                                                            </svg>
                                                                            <svg v-else-if="t === 'radar'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l9 6v8l-9 6-9-6V8l9-6zm0 0v20m-9-10h18M5 8l14 8M5 16l14-8" />
                                                                            </svg>
                                                                            <svg v-else-if="t === 'pie'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" />
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                                                                            </svg>
                                                                            <svg v-else-if="t === 'donut'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                <circle cx="12" cy="12" r="9" />
                                                                                <circle cx="12" cy="12" r="4" />
                                                                            </svg>
                                                                            <svg v-else-if="t === 'radialBar'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2M12 18C15.3137 18 18 15.3137 18 12C18 8.68629 15.3137 6 12 6" />
                                                                            </svg>
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            @click="randomizeChartType('history-' + mi)"
                                                                            class="p-1 rounded transition-all hover:bg-slate-700 cursor-pointer text-slate-400 border-l border-slate-700 pl-1.5"
                                                                            title="Randomize Chart Type"
                                                                        >
                                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.678 48.678 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3M3 12a9 9 0 0115 0" />
                                                                            </svg>
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                                <apexchart 
                                                                    :type="getChartType(msg.text, 'history-' + mi)" 
                                                                    height="220" 
                                                                    :options="getChartOptions(parseMessageContent(msg.text).chartData, getChartType(msg.text, 'history-' + mi))" 
                                                                    :series="getChartSeries(parseMessageContent(msg.text).chartData, getChartType(msg.text, 'history-' + mi))"
                                                                />
                                                            </div>
                                                            
                                                            <div class="whitespace-pre-wrap" v-html="formatMarkdown(parseMessageContent(msg.text).afterText)"></div>
                                                        </div>
                                                        <div v-else class="whitespace-pre-wrap" v-html="formatMarkdown(msg.text)"></div>
                                                    </template>
                                                    <p v-else class="whitespace-pre-wrap">{{ msg.text }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
