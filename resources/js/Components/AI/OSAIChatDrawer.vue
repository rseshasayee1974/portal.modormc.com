<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';
import BaseInput from '@/Components/Base/BaseInput.vue';
import apexchart from 'vue3-apexcharts';
import {
    CpuChipIcon,
    XMarkIcon,
    ClockIcon,
    ChatBubbleOvalLeftIcon,
    ArrowPathIcon,
    PaperAirplaneIcon,
    SparklesIcon,
    PhotoIcon,
} from '@heroicons/vue/24/outline';

// ── Page / Auth ───────────────────────────────────────────────────────────────
const page = usePage();
const { can } = usePermissions();

// ── Drawer open/close ─────────────────────────────────────────────────────────
const isChatDrawerOpen = ref(false);

// ── Chat State ────────────────────────────────────────────────────────────────
const chatMessages           = ref([]);
const currentPrompt          = ref('');
const isSubmitting           = ref(false);
const preferredLanguage      = ref('ta');
const currentChatSessionId   = ref(null);

// Image Upload State
const imageInputRef = ref(null);
const selectedImageBase64 = ref('');
const selectedImageUrl = ref('');

const triggerImageUpload = () => {
    if (imageInputRef.value) {
        imageInputRef.value.click();
    }
};

const handleImageSelection = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        alert('Please select an image file.');
        return;
    }

    const reader = new FileReader();
    reader.onload = (event) => {
        selectedImageBase64.value = event.target.result;
        selectedImageUrl.value = event.target.result;
    };
    reader.readAsDataURL(file);
};

const clearSelectedImage = () => {
    selectedImageBase64.value = '';
    selectedImageUrl.value = '';
    if (imageInputRef.value) {
        imageInputRef.value.value = '';
    }
};

// Speech Recognition
const isListening            = ref(false);
const recognitionError       = ref(null);
let   activeRecognition      = null;

// Speech Synthesis / TTS
const currentlySpeakingMessageIndex = ref(null);
const currentPlayingAudio    = ref(null);
const voicesList             = ref([]);

// Active category for predefined questions
const selectedCategory       = ref('');

// Chart type overrides
const overriddenChartTypes   = ref({});

// ── Agent Config ──────────────────────────────────────────────────────────────
const activeAgent = computed(() => {
    const role = page.props.user_role;
    if (role === 'Accountant' || role === 'Senior Accountant' || role === 'CFO') {
        return {
            name: 'Accountant',
            class: 'App\\Ai\\Agents\\Accountant',
            defaultGreeting: 'Hi, I am your Accountant AI Assistant. How can I help you with your RMC ledgers or financial statements today?'
        };
    }
    return {
        name: 'Onemodo',
        class: 'App\\Ai\\Agents\\Onemodo',
        defaultGreeting: 'Hi, I am Onemodo, your RMC operations assistant. How can I help you with your plant operations or dispatches today?'
    };
});

// ── Open / Close Drawer ───────────────────────────────────────────────────────
const openGlobalChat = () => {
    currentChatSessionId.value = null;
    chatMessages.value = [
        { role: 'agent', text: activeAgent.value.defaultGreeting }
    ];
    if (activeAgent.value.name === 'Accountant') {
        selectedCategory.value = 'billing_invoices';
    } else {
        selectedCategory.value = 'operations_stock';
    }
    isChatDrawerOpen.value = true;
};

const closeGlobalChat = async () => {
    window.speechSynthesis.cancel();
    currentlySpeakingMessageIndex.value = null;
    stopSpeechRecognition();
    await saveHistoryToServer();
    isChatDrawerOpen.value = false;
    chatMessages.value = [];
    currentPrompt.value = '';
    currentChatSessionId.value = null;
};

// ── Available Categories ──────────────────────────────────────────────────────
const availableCategories = computed(() => {
    const isTamil = preferredLanguage.value === 'ta';
    if (activeAgent.value.name === 'Accountant') {
        return [
            { id: 'billing_invoices',   label: isTamil ? 'பில்லிங் & இன்வாய்ஸ்கள்' : 'Billing & Invoices' },
            { id: 'financial_reports',  label: isTamil ? 'நிதி அறிக்கைகள்'          : 'Financial Reports' },
            { id: 'expenses_inventory', label: isTamil ? 'செலவுகள் & இருப்பு'       : 'Expenses & Inventory' },
            { id: 'accounting_guide',   label: isTamil ? 'கணக்கியல் வழிகாட்டி'      : 'Accounting Guide' }
        ];
    }
    return [
        { id: 'operations_stock', label: isTamil ? 'செயல்பாடுகள் & இருப்பு' : 'Operations & Stock' },
        { id: 'troubleshooting',  label: isTamil ? 'பழுது நீக்குதல்'         : 'Troubleshooting' }
    ];
});

// ── Predefined Questions ──────────────────────────────────────────────────────
const activeQuestions = computed(() => {
    const isTamil = preferredLanguage.value === 'ta';
    if (activeAgent.value.name === 'Accountant') {
        switch (selectedCategory.value) {
            case 'billing_invoices':
                return isTamil ? [
                    'வருவாய் ஆதாரங்கள் என்ன?',
                    'வாடிக்கையாளர் பணத்தைத் திருப்பித் தருவதற்கான ஜர்னல் என்ட்ரி என்ன?',
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

const runPredefinedQuestion = (q) => {
    currentPrompt.value = q;
    sendChatPrompt();
};

// ── TTS (Text-to-Speech) ──────────────────────────────────────────────────────
if (typeof window !== 'undefined' && window.speechSynthesis) {
    const loadVoices = () => { voicesList.value = window.speechSynthesis.getVoices(); };
    loadVoices();
    if (window.speechSynthesis.onvoiceschanged !== undefined) {
        window.speechSynthesis.onvoiceschanged = loadVoices;
    }
}

const browserSpeak = (txt, lang, idx) => {
    if (typeof window === 'undefined' || !window.speechSynthesis) {
        currentlySpeakingMessageIndex.value = null;
        return;
    }
    window.speechSynthesis.cancel();
    const utter = new SpeechSynthesisUtterance(txt);
    utter.lang = lang;
    const loadVoice = () => {
        const voices = window.speechSynthesis.getVoices();
        const match  = voices.find(v => v.lang === lang || v.lang.startsWith(lang.split('-')[0]));
        if (match) utter.voice = match;
    };
    loadVoice();
    if (window.speechSynthesis.getVoices().length === 0) {
        window.speechSynthesis.onvoiceschanged = loadVoice;
    }
    utter.rate  = 0.95;
    utter.onend = () => { if (currentlySpeakingMessageIndex.value === idx) currentlySpeakingMessageIndex.value = null; };
    utter.onerror = () => { currentlySpeakingMessageIndex.value = null; };
    window.speechSynthesis.speak(utter);
};

const toggleSpeech = async (text, index) => {
    if (currentlySpeakingMessageIndex.value === index) {
        if (currentPlayingAudio.value) { currentPlayingAudio.value.pause(); currentPlayingAudio.value = null; }
        window.speechSynthesis?.cancel();
        currentlySpeakingMessageIndex.value = null;
        return;
    }
    if (currentPlayingAudio.value) { currentPlayingAudio.value.pause(); currentPlayingAudio.value = null; }
    window.speechSynthesis?.cancel();
    currentlySpeakingMessageIndex.value = null;

    let cleanText = text
        .replace(/```json[\s\S]*?```/g, '')
        .replace(/```[\s\S]*?```/g, '')
        .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
        .replace(/\|/g, ' ').replace(/\*/g, '').replace(/`/g, '')
        .replace(/#/g, '').replace(/-/g, ' ').replace(/\t/g, ' ')
        .replace(/\s+/g, ' ').trim();

    if (!cleanText) return;

    const containsTamil = /[\u0B80-\u0BFF]/.test(cleanText);
    const targetLang = preferredLanguage.value === 'auto'
        ? (containsTamil ? 'ta-IN' : 'en-IN')
        : (preferredLanguage.value === 'ta' ? 'ta-IN' : 'en-IN');

    currentlySpeakingMessageIndex.value = index;

    try {
        const { data } = await axios.post('/api/ai/text-to-speech', { text: cleanText, language: targetLang });
        if (data.success && data.audio_base64 && currentlySpeakingMessageIndex.value === index) {
            const audio = new Audio(`data:${data.content_type};base64,${data.audio_base64}`);
            currentPlayingAudio.value = audio;
            audio.play();
            audio.onended = () => { if (currentlySpeakingMessageIndex.value === index) { currentlySpeakingMessageIndex.value = null; currentPlayingAudio.value = null; } };
            audio.onerror = () => { if (currentlySpeakingMessageIndex.value === index) { currentlySpeakingMessageIndex.value = null; currentPlayingAudio.value = null; } };
        } else if (data.fallback_to_browser) {
            browserSpeak(cleanText, targetLang, index);
        } else {
            currentlySpeakingMessageIndex.value = null;
        }
    } catch (err) {
        const shouldFallback = err?.response?.data?.fallback_to_browser === true
            || err?.response?.status === 503 || err?.response?.status === 422;
        if (shouldFallback) browserSpeak(cleanText, targetLang, index);
        else { console.error('TTS failed', err); currentlySpeakingMessageIndex.value = null; }
    }
};

// ── STT (Speech Recognition) ──────────────────────────────────────────────────
const startSpeechRecognition = () => {
    if (typeof window === 'undefined') return;
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        recognitionError.value = 'உங்கள் browser-ல் voice input ஆதரிக்கப்படவில்லை. Chrome / Edge ஐ முயற்சிக்கவும்.';
        return;
    }
    window.speechSynthesis.cancel();
    currentlySpeakingMessageIndex.value = null;

    const recognition = new SpeechRecognition();
    activeRecognition = recognition;
    const langMap = { ta: 'ta-IN', en: 'en-IN', auto: 'ta-IN' };
    recognition.lang = langMap[preferredLanguage.value] ?? 'ta-IN';
    recognition.interimResults = false;
    recognition.maxAlternatives = 1;
    recognition.continuous = false;
    recognitionError.value = null;
    isListening.value = true;

    recognition.onresult = (event) => {
        const transcript = event.results[0][0].transcript;
        currentPrompt.value = (currentPrompt.value ? currentPrompt.value + ' ' : '') + transcript;
        isListening.value = false;
        activeRecognition = null;
    };
    recognition.onerror = (event) => {
        if (event.error === 'aborted') { /* silent */ }
        else if (event.error === 'no-speech') recognitionError.value = 'குரல் கண்டுபிடிக்கப்படவில்லை. மீண்டும் முயற்சிக்கவும்.';
        else if (event.error === 'not-allowed') recognitionError.value = 'Microphone அனுமதி மறுக்கப்பட்டது. Browser settings-ல் allow செய்யவும்.';
        else recognitionError.value = `Recognition error: ${event.error}`;
        isListening.value = false;
        activeRecognition = null;
    };
    recognition.onend = () => { isListening.value = false; activeRecognition = null; };
    recognition.start();
};

const stopSpeechRecognition = () => {
    if (activeRecognition) { try { activeRecognition.abort(); } catch (_) {} activeRecognition = null; }
    isListening.value = false;
};

const toggleSpeechRecognition = () => {
    if (isListening.value) stopSpeechRecognition();
    else startSpeechRecognition();
};

// ── Send Prompt ───────────────────────────────────────────────────────────────
const sendChatPrompt = async () => {
    if ((!currentPrompt.value.trim() && !selectedImageBase64.value) || isSubmitting.value) return;
    const userText = currentPrompt.value;
    const userImage = selectedImageUrl.value;
    
    chatMessages.value.push({ role: 'user', text: userText, image: userImage });
    
    const payload = {
        prompt:      userText,
        agent_class: activeAgent.value.class,
    };
    if (selectedImageBase64.value) {
        payload.image = selectedImageBase64.value;
    }
    
    currentPrompt.value = '';
    clearSelectedImage();
    isSubmitting.value = true;
    saveHistoryToServer();

    try {
        const response = await axios.post(route('settings.agents.test'), payload);
        const data = response.data;
        if (data.success) {
            let reply = data.response;
            if (typeof reply === 'object' && reply !== null) {
                if ('value' in reply && Object.keys(reply).length === 1) reply = reply.value;
                else reply = '```json\n' + JSON.stringify(reply, null, 2) + '\n```';
            }
            chatMessages.value.push({ role: 'agent', text: reply });
        } else {
            chatMessages.value.push({ role: 'error', text: data.error || 'An error occurred while executing the prompt.' });
        }
    } catch (err) {
        const errMsg = err.response?.data?.error || err.response?.data?.message || err.message || 'Network request failed.';
        chatMessages.value.push({ role: 'error', text: errMsg });
    } finally {
        isSubmitting.value = false;
        saveHistoryToServer();
    }
};

// ── Save / Load History ───────────────────────────────────────────────────────
const saveHistoryToServer = async () => {
    const hasUserMsg = chatMessages.value.some(m => m.role === 'user');
    if (!hasUserMsg) return;
    try {
        const response = await axios.post(route('settings.agents.history.store'), {
            id:               currentChatSessionId.value,
            agent_name:       activeAgent.value.name,
            agent_class:      activeAgent.value.class,
            session_language: preferredLanguage.value,
            messages:         chatMessages.value,
        });
        if (response.data.success && response.data.id) {
            currentChatSessionId.value = response.data.id;
        }
    } catch (e) {
        console.error('Failed to save history:', e.response?.data || e);
    }
};

// ── Chat History Panel ────────────────────────────────────────────────────────
const isHistoryPanelOpen  = ref(false);
const historyList         = ref([]);
const historyCurrentPage  = ref(1);
const historyLastPage     = ref(1);
const historyLoading      = ref(false);
const historyViewSession  = ref(null);

const fetchHistory = async (page = 1) => {
    historyLoading.value = true;
    try {
        const res = await axios.get(route('settings.agents.history.index'), {
            params: { page, agent: activeAgent.value.name },
        });
        historyList.value        = res.data.data;
        historyCurrentPage.value = res.data.current_page;
        historyLastPage.value    = res.data.last_page;
    } catch (e) { /* ignore */ }
    finally { historyLoading.value = false; }
};

const openHistoryPanel  = () => { isHistoryPanelOpen.value = true; historyViewSession.value = null; fetchHistory(1); };
const closeHistoryPanel = () => { isHistoryPanelOpen.value = false; historyViewSession.value = null; };

const viewHistorySession = async (id) => {
    try {
        const res = await axios.get(route('settings.agents.history.show', { history: id }));
        historyViewSession.value = res.data;
    } catch (e) { /* ignore */ }
};

const formatHistoryDate = (ts) => {
    if (!ts) return '';
    const d = new Date(ts);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
};

// ── Chart Helpers ─────────────────────────────────────────────────────────────
const parseMessageContent = (text) => {
    if (!text) return { hasChart: false, text: '' };
    const chartMarkerIndex   = text.toLowerCase().indexOf('`chart');
    const threeBackticksIndex = text.toLowerCase().indexOf('```chart');
    let markerIndex = -1, markerLength = 0;
    if (threeBackticksIndex !== -1) { markerIndex = threeBackticksIndex; markerLength = 8; }
    else if (chartMarkerIndex !== -1) { markerIndex = chartMarkerIndex; markerLength = 6; }

    if (markerIndex !== -1) {
        const startBrace = text.indexOf('{', markerIndex + markerLength);
        if (startBrace !== -1) {
            const endBrace = text.lastIndexOf('}');
            if (endBrace !== -1 && endBrace > startBrace) {
                const jsonString = text.substring(startBrace, endBrace + 1).trim();
                const beforeText = text.substring(0, markerIndex);
                let afterTextStart = endBrace + 1;
                const closingMatch = text.substring(afterTextStart, afterTextStart + 10).match(/^\s*```|^\s*`/);
                if (closingMatch) afterTextStart += closingMatch[0].length;
                const afterText = text.substring(afterTextStart);
                try {
                    const chartData = JSON.parse(jsonString);
                    return { hasChart: true, beforeText, afterText, chartData };
                } catch (e) { console.error('Failed to parse chart JSON:', e); }
            }
        }
    }
    return { hasChart: false, text };
};

const normalizeChartType = (type) => {
    if (!type) return 'bar';
    const t = type.toLowerCase().trim();
    if (t === 'doughnut') return 'donut';
    if (t === 'radius' || t === 'radious' || t === 'radial') return 'radialBar';
    return t;
};

const getChartType = (msgText, key) => {
    const overrideKey = String(key);
    if (overriddenChartTypes.value[overrideKey]) return overriddenChartTypes.value[overrideKey];
    const chartData = parseMessageContent(msgText).chartData;
    let parsedType = chartData?.chartType;
    if (!parsedType) {
        const types = ['bar', 'line', 'area', 'radar', 'pie', 'donut', 'radialBar'];
        let seed = 0;
        const keyStr = String(key);
        for (let i = 0; i < keyStr.length; i++) seed += keyStr.charCodeAt(i);
        parsedType = types[seed % types.length];
    }
    return normalizeChartType(parsedType);
};

const setChartOverride = (key, type) => { overriddenChartTypes.value[String(key)] = type; };

const randomizeChartType = (key) => {
    const types = ['bar', 'line', 'area', 'radar', 'pie', 'donut', 'radialBar'];
    const current = getChartType('', key);
    const available = types.filter(t => t !== current);
    setChartOverride(key, available[Math.floor(Math.random() * available.length)]);
};

const getChartOptions = (chartData, type) => {
    const normalizedType = normalizeChartType(type);
    const isCircular = ['pie', 'donut', 'radialBar'].includes(normalizedType);
    const baseOptions = {
        chart: { background: 'transparent', foreColor: '#94a3b8', toolbar: { show: false }, zoom: { enabled: false } },
        theme: { mode: 'dark' },
        colors: chartData.colors || ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#a855f7', '#ec4899', '#06b6d4'],
        tooltip: { theme: 'dark' },
        legend: { position: 'bottom', labels: { colors: '#94a3b8' } }
    };
    if (isCircular) {
        baseOptions.labels = chartData.labels || [];
        if (normalizedType === 'radialBar') {
            baseOptions.plotOptions = { radialBar: { dataLabels: { name: { show: true, fontSize: '13px', color: '#94a3b8' }, value: { show: true, fontSize: '15px', color: '#f8fafc', formatter: val => val }, total: { show: true, label: 'Total', color: '#94a3b8', formatter: w => (w.globals.series || []).reduce((a, b) => a + b, 0).toLocaleString('en-IN') } } } };
        } else if (normalizedType === 'donut') {
            baseOptions.plotOptions = { pie: { donut: { labels: { show: true, name: { show: true, fontSize: '13px', color: '#94a3b8' }, value: { show: true, fontSize: '16px', color: '#f8fafc', formatter: val => val }, total: { show: true, label: 'Total', color: '#94a3b8', formatter: w => (w.globals.series || []).reduce((a, b) => a + b, 0).toLocaleString('en-IN') } } } } };
        }
    } else {
        baseOptions.xaxis = { categories: chartData.labels || [], labels: { style: { colors: '#94a3b8' } }, axisBorder: { show: false }, axisTicks: { show: false } };
        baseOptions.yaxis = { labels: { style: { colors: '#94a3b8' } } };
        baseOptions.grid  = { borderColor: '#334155', strokeDashArray: 4 };
        baseOptions.stroke = { curve: 'smooth', width: normalizedType === 'line' || normalizedType === 'area' ? 3 : 0 };
        if (normalizedType === 'bar') baseOptions.plotOptions = { bar: { borderRadius: 4, columnWidth: '55%' } };
    }
    return baseOptions;
};

const getChartSeries = (chartData, type) => {
    const normalizedType = normalizeChartType(type);
    const isCircular = ['pie', 'donut', 'radialBar'].includes(normalizedType);
    let rawSeries = [];
    if (chartData.series) rawSeries = chartData.series;
    else if (chartData.datasets) rawSeries = chartData.datasets.map(d => ({ name: d.label || chartData.title || 'Data', data: d.data }));
    else if (chartData.data) rawSeries = [{ name: chartData.title || 'Data', data: chartData.data }];
    if (isCircular) { return rawSeries.length > 0 && typeof rawSeries[0] === 'object' ? rawSeries[0].data || [] : rawSeries; }
    if (rawSeries.length > 0 && typeof rawSeries[0] !== 'object') return [{ name: chartData.title || 'Data', data: rawSeries }];
    return rawSeries;
};

// ── Markdown formatter ────────────────────────────────────────────────────────
const formatMarkdown = (text) => {
    if (!text) return '';
    let escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const lines = escaped.split('\n');
    let inTable = false, tableHtml = '', headerLength = 0;
    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        if (line.startsWith('|') && line.endsWith('|')) {
            const cells = line.split('|').map(c => c.trim()).slice(1, -1);
            const isSeparator = cells.every(c => /^:?-+:?$/.test(c));
            if (isSeparator) { lines[i] = '<!-- TABLE_ROW -->'; continue; }
            if (!inTable) {
                inTable = true;
                tableHtml = '<div class="overflow-x-auto my-3 border border-slate-200 rounded-xl shadow-sm"><table class="min-w-full text-xs text-left border-collapse bg-white"><thead class="bg-indigo-50/70 border-b border-slate-200 text-indigo-850"><tr>';
                cells.forEach(cell => { tableHtml += `<th class="px-3 py-2 font-black uppercase tracking-wider">${cell}</th>`; });
                tableHtml += '</tr></thead><tbody class="divide-y divide-slate-100">'; headerLength = cells.length;
            } else {
                tableHtml += '<tr class="hover:bg-slate-50/50 transition-colors">';
                for (let j = 0; j < headerLength; j++) {
                    const val = cells[j] || '';
                    const isNumeric = val.startsWith('₹') || /^\d+/.test(val) || val === '0.00' || val.endsWith('%');
                    tableHtml += `<td class="px-3 py-2 text-slate-700 font-semibold whitespace-nowrap ${isNumeric ? 'text-right' : 'text-left'}">${val}</td>`;
                }
                tableHtml += '</tr>';
            }
            lines[i] = '<!-- TABLE_ROW -->';
        } else {
            if (inTable) {
                inTable = false; tableHtml += '</tbody></table></div>';
                let lastIdx = -1;
                for (let k = 0; k < i; k++) if (lines[k] === '<!-- TABLE_ROW -->') lastIdx = k;
                if (lastIdx !== -1) lines[lastIdx] = tableHtml;
            }
        }
    }
    if (inTable) {
        tableHtml += '</tbody></table></div>';
        let lastIdx = -1;
        for (let k = 0; k < lines.length; k++) if (lines[k] === '<!-- TABLE_ROW -->') lastIdx = k;
        if (lastIdx !== -1) lines[lastIdx] = tableHtml;
    }
    let out = lines.filter(l => l !== '<!-- TABLE_ROW -->').join('\n');
    out = out.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    out = out.replace(/\*(.*?)\*/g, '<em>$1</em>');
    out = out.replace(/`(.*?)`/g, '<code class="bg-indigo-50 text-indigo-700 px-1 py-0.5 rounded font-mono text-xs font-semibold">$1</code>');
    out = out.replace(/^\s*[-*]\s+(.*?)$/gm, '<li class="ml-4 list-disc mt-1 text-slate-750">$1</li>');
    return out;
};

// Chart type icon helper (SVG markup)
const chartTypeIcons = {
    bar:       `<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7 17v-4m4 4V9m4 8v-6m4 6V5" /></svg>`,
    line:      `<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19l4-4 4 4 8-8m0 0l-4 0m4 0l0 4" /></svg>`,
    area:      `<svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M3 3v18h18v-2H5V3H3zm4 14l4-4 4 3 6-7v9H7z" /></svg>`,
    radar:     `<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2l9 6v8l-9 6-9-6V8l9-6zm0 0v20m-9-10h18M5 8l14 8M5 16l14-8" /></svg>`,
    pie:       `<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.003 9.003 0 1020.945 13H11V3.055z" /><path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" /></svg>`,
    donut:     `<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="4" /></svg>`,
    radialBar: `<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2M12 18C15.3137 18 18 15.3137 18 12C18 8.68629 15.3137 6 12 6" /></svg>`,
};
const CHART_TYPES = ['bar', 'line', 'area', 'radar', 'pie', 'donut', 'radialBar'];
</script>

<template>
    <!-- ── Floating AI Action Button (FAB) ── -->
    <div
        v-if="$page.props.auth.user && !isChatDrawerOpen"
        class="fixed bottom-6 left-6 z-[90] animate-float animate-in fade-in slide-in-from-bottom-5 duration-500"
    >
        <button
            @click="openGlobalChat"
            class="group relative flex items-center justify-center p-0.5 rounded-full bg-gradient-to-tr from-indigo-650 via-purple-500 to-amber-400 hover:from-indigo-500 hover:via-purple-400 hover:to-amber-300 shadow-2xl transition-all duration-500 hover:scale-105 active:scale-95 cursor-pointer border-0"
            title="Ask AI Assistant"
        >
            <span class="absolute inset-0 rounded-full bg-indigo-500/40 blur-xl opacity-70 group-hover:opacity-100 group-hover:bg-indigo-500/60 transition-opacity duration-300"></span>
            <div class="relative flex items-center gap-2 bg-slate-900/90 dark:bg-slate-900/95 text-white px-4 py-3 rounded-full border border-white/10 backdrop-blur-md transition-all duration-300 group-hover:bg-slate-800/80">
                <div class="absolute inset-0 rounded-full overflow-hidden opacity-0 group-hover:opacity-10 transition-opacity duration-500">
                    <div class="absolute inset-0 bg-gradient-to-r from-indigo-500 to-purple-500 animate-rotate-slow"></div>
                </div>
                <SparklesIcon class="h-5.5 w-5.5 text-amber-400 group-hover:text-white transition-all duration-500 ease-out transform group-hover:rotate-180 scale-100 group-hover:scale-110" />
                <span class="text-xs font-black uppercase tracking-wider pr-1">Ask OSAI</span>
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-400"></span>
                </span>
            </div>
        </button>
    </div>

    <!-- ── Global Chat Sidebar Drawer ── -->
    <div v-if="isChatDrawerOpen" class="fixed inset-0 z-[100] overflow-hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 overflow-hidden">
            <!-- Overlay -->
            <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-md transition-opacity duration-500" @click="closeGlobalChat"></div>

            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div class="pointer-events-auto w-screen max-w-md md:max-w-xl">
                    <div class="flex h-full flex-col bg-[#f0f3f6] shadow-sm">

                        <!-- Drawer Header -->
                        <div class="bg-[#f0f3f6] px-6 py-5 border-none flex items-center justify-between z-10">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 bg-[#f0f3f6] rounded-xl shadow-sm">
                                    <CpuChipIcon class="w-5 h-5 text-slate-600" />
                                </div>
                                <div>
                                    <h2 class="text-base font-black text-slate-900 dark:text-white leading-none">{{ activeAgent?.name }} AI</h2>
                                    <div class="flex items-center gap-1.5 mt-1.5">
                                        <span class="relative flex h-1.5 w-1.5">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                                        </span>
                                        <span class="text-[9px] font-mono text-slate-450 dark:text-slate-400 uppercase tracking-widest leading-none">Live Assistant</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <!-- Language toggle -->
                                <div class="flex items-center gap-1 bg-slate-150/70 dark:bg-slate-800/80 rounded-lg p-0.5 border border-slate-200/50 dark:border-slate-700/50">
                                    <button
                                        v-for="lang in ['auto', 'en', 'ta']"
                                        :key="lang"
                                        type="button"
                                        @click="preferredLanguage = lang"
                                        class="text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded transition-all cursor-pointer"
                                        :class="[preferredLanguage === lang ? 'bg-white dark:bg-slate-700 text-indigo-700 dark:text-indigo-300 shadow-sm' : 'text-slate-500 hover:text-slate-800 dark:hover:text-white']"
                                    >{{ lang === 'auto' ? 'Auto' : lang === 'en' ? 'English' : 'Tamil' }}</button>
                                </div>
                                <!-- History button -->
                                <button type="button" @click="openHistoryPanel" class="rounded-xl p-2 text-slate-400 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-all cursor-pointer" title="Chat History">
                                    <ClockIcon class="h-5 w-5" />
                                </button>
                                <button type="button" class="rounded-xl p-2 text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-all cursor-pointer" @click="closeGlobalChat">
                                    <XMarkIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </div>

                        <!-- Messages -->
                        <div class="flex-1 overflow-y-auto p-6 space-y-5 bg-[#f0f3f6]">
                            <div v-for="(msg, idx) in chatMessages" :key="idx" class="flex flex-col" :class="[msg.role === 'user' ? 'items-end' : 'items-start']">
                                <div class="max-w-[85%] rounded-2xl px-4 py-3.5 text-sm leading-relaxed transition-all duration-300"
                                     :class="[msg.role === 'user' ? 'bg-indigo-200 text-white rounded-br-none shadow-md' : msg.role === 'error' ? 'bg-rose-50 text-rose-700 rounded-bl-none border border-rose-100 shadow-sm' : 'bg-white text-slate-800 border border-slate-100 rounded-bl-none shadow-md']"
                                >
                                    <!-- User image attachment if present -->
                                    <div v-if="msg.image" class="mb-2 max-w-full">
                                        <img :src="msg.image" class="max-w-full max-h-48 rounded-lg object-contain shadow-sm" />
                                    </div>
                                    <template v-if="msg.role === 'agent'">
                                        <div v-if="parseMessageContent(msg.text).hasChart" class="w-full">
                                            <div class="whitespace-pre-wrap text-slate-700" v-html="formatMarkdown(parseMessageContent(msg.text).beforeText)"></div>
                                            <!-- Chart block -->
                                            <div class="my-4 p-4 bg-slate-900 border border-slate-800 rounded-2xl shadow-inner text-white min-w-[280px]">
                                                <div class="flex flex-col sm:flex-row items-center justify-between gap-2 mb-3 pb-2 border-b border-slate-800/60">
                                                    <p class="text-xs font-black uppercase tracking-widest text-indigo-400">{{ parseMessageContent(msg.text).chartData.title || 'Data Analysis Chart' }}</p>
                                                    <div class="flex items-center gap-1 bg-slate-800/80 rounded-lg p-0.5 border border-slate-700">
                                                        <button v-for="t in CHART_TYPES" :key="t" type="button" @click="setChartOverride('live-' + idx, t)"
                                                            class="p-1 rounded transition-all hover:bg-slate-700 cursor-pointer text-slate-400 border-none bg-transparent"
                                                            :class="[getChartType(msg.text, 'live-' + idx) === t ? 'bg-indigo-600 !text-white' : '']"
                                                            :title="'Show as ' + t" v-html="chartTypeIcons[t]">
                                                        </button>
                                                        <button type="button" @click="randomizeChartType('live-' + idx)" class="p-1 rounded transition-all hover:bg-slate-700 cursor-pointer text-slate-400 border-l border-slate-700 pl-1.5 bg-transparent" title="Randomize">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 00-3.7-3.7 48.678 48.678 0 00-7.324 0 4.006 4.006 0 00-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3l-3-3M3 12a9 9 0 0115 0" /></svg>
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
                                            <div class="whitespace-pre-wrap text-slate-700" v-html="formatMarkdown(parseMessageContent(msg.text).afterText)"></div>
                                        </div>
                                        <div v-else-if="msg.text.startsWith('```json')" class="font-mono text-xs">
                                            <pre class="overflow-x-auto whitespace-pre bg-slate-900 text-emerald-455 p-3.5 rounded-xl border border-slate-800 shadow-inner font-semibold leading-relaxed">{{ msg.text.replace(/```json\n|```/g, '') }}</pre>
                                        </div>
                                        <div v-else class="whitespace-pre-wrap text-slate-700" v-html="formatMarkdown(msg.text)"></div>
                                    </template>
                                    <template v-else>
                                        <div class="whitespace-pre-wrap">{{ msg.text }}</div>
                                    </template>
                                </div>
                                <div class="flex items-center gap-1.5 mt-1.5 px-1.5">
                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">{{ msg.role === 'user' ? 'You' : activeAgent?.name }}</span>
                                    <button v-if="msg.role === 'agent'" type="button" @click="toggleSpeech(msg.text, idx)" class="p-0.5 rounded text-slate-400 hover:text-indigo-600 hover:bg-slate-200 transition-colors cursor-pointer" title="Listen to response">
                                        <svg v-if="currentlySpeakingMessageIndex === idx" class="w-3.5 h-3.5 text-indigo-600 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" /></svg>
                                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.009 9.009 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" /></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Thinking indicator -->
                            <div v-if="isSubmitting" class="flex flex-col gap-2 bg-indigo-50/50 border border-indigo-150/40 rounded-2xl p-4 shadow-sm animate-pulse-glow">
                                <div class="flex items-center gap-2.5 text-indigo-700">
                                    <ArrowPathIcon class="w-4 h-4 animate-spin" />
                                    <span class="text-xs font-bold tracking-wide">AI is invoking tools and processing...</span>
                                </div>
                                <div class="h-1 bg-indigo-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 animate-shimmer" style="width: 100%;"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Drawer Footer: Input -->
                        <div class="bg-[#f0f3f6] px-6 py-4 shadow-[0_-4px_8px_-2px_#d1d9e6,0_4px_8px_-2px_#ffffff] z-10">
                            <!-- Category tabs -->
                            <div class="flex gap-1.5 mb-3 overflow-x-auto pb-1.5 border-b border-slate-100 scrollbar-none">
                                <button v-for="cat in availableCategories" :key="cat.id" type="button" @click="selectedCategory = cat.id"
                                    class="text-[9px] font-black uppercase tracking-wider px-3 py-1.5 rounded-full transition-all cursor-pointer whitespace-nowrap border-none"
                                    :class="[selectedCategory === cat.id ? 'bg-[#f0f3f6] text-slate-800 shadow-[inset_-4px_-4px_8px_#ffffff,inset_4px_4px_8px_#d1d9e6] font-black' : 'text-slate-500 hover:text-slate-700 bg-[#f0f3f6] shadow-[-2px_-2px_4px_#ffffff,2px_2px_4px_#d1d9e6]']"
                                >{{ cat.label }}</button>
                            </div>

                            <!-- Predefined Questions -->
                            <div class="flex flex-wrap gap-1.5 mb-4">
                                <button v-for="q in activeQuestions" :key="q" type="button" @click="runPredefinedQuestion(q)" :disabled="isSubmitting"
                                    class="text-[10px] font-bold text-slate-600 bg-[#f0f3f6] shadow-[-4px_-4px_8px_#ffffff,4px_4px_8px_#d1d9e6] hover:-translate-y-0.5 hover:shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] rounded-full px-3 py-1.5 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed border-none active:shadow-[inset_-2px_-2px_4px_#ffffff,inset_2px_2px_4px_#d1d9e6] active:translate-y-0"
                                >{{ q }}</button>
                            </div>

                            <!-- Recognition error -->
                            <div v-if="recognitionError" class="mb-3 flex items-center gap-2 bg-red-50 border border-red-200/50 rounded-xl px-3.5 py-2.5">
                                <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                <span class="text-[10px] font-bold text-red-700 flex-1 leading-snug">{{ recognitionError }}</span>
                                <button type="button" @click="recognitionError = null" class="text-red-400 hover:text-red-650 cursor-pointer"><svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg></button>
                            </div>

                            <!-- Hidden Image File Input -->
                            <input
                                ref="imageInputRef"
                                type="file"
                                accept="image/*"
                                class="hidden"
                                @change="handleImageSelection"
                            />

                            <!-- Selected Image Preview -->
                            <div v-if="selectedImageUrl" class="mb-3 relative inline-block">
                                <img :src="selectedImageUrl" class="h-16 w-auto rounded-lg border border-slate-250 object-cover shadow-sm" />
                                <button
                                    type="button"
                                    @click="clearSelectedImage"
                                    class="absolute -top-1.5 -right-1.5 bg-rose-500 hover:bg-rose-600 text-white rounded-full p-0.5 shadow-md border-none transition cursor-pointer flex items-center justify-center"
                                >
                                    <XMarkIcon class="w-3.5 h-3.5" />
                                </button>
                            </div>

                            <!-- Input row -->
                            <div class="flex gap-3 items-center">
                                <!-- Attach image button -->
                                <button type="button" @click="triggerImageUpload" :disabled="isSubmitting"
                                    :title="preferredLanguage === 'ta' ? 'படம் சேர்க்க' : 'Attach image'"
                                    class="relative flex-shrink-0 w-11 h-11 flex items-center justify-center rounded-xl bg-[#f0f3f6] text-slate-500 hover:text-slate-700 shadow-[-4px_-4px_8px_#ffffff,4px_4px_8px_#d1d9e6] hover:-translate-y-0.5 hover:shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] active:shadow-[inset_-2px_-2px_4px_#ffffff,inset_2px_2px_4px_#d1d9e6] active:translate-y-0 transition-all duration-300 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed border-none"
                                >
                                    <PhotoIcon class="w-5 h-5" />
                                </button>

                                <div class="relative flex-1 flex items-center">
                                    <BaseInput
                                        v-model="currentPrompt"
                                        :placeholder="preferredLanguage === 'ta' ? 'உங்கள் கேள்வியை தட்டச்சு செய்யுங்கள்...' : 'Type a message...'"
                                        fieldClass="flex-1"
                                        inputClass="!py-3 !rounded-xl !pr-10 !bg-[#f0f3f6] !border-none !shadow-[inset_-4px_-4px_8px_#ffffff,inset_4px_4px_8px_#d1d9e6] text-slate-700 focus:ring-2 focus:ring-amber-500/20"
                                        @keydown.enter="sendChatPrompt"
                                        :disabled="isSubmitting"
                                    />
                                </div>
                                <!-- Mic button -->
                                <button type="button" @click="toggleSpeechRecognition" :disabled="isSubmitting"
                                    :title="isListening ? (preferredLanguage === 'ta' ? 'நிறுத்து' : 'Stop listening') : (preferredLanguage === 'ta' ? 'குரல் உள்ளீடு' : 'Voice input')"
                                    class="relative flex-shrink-0 w-11 h-11 flex items-center justify-center rounded-xl border-none transition-all duration-300 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="isListening ? 'bg-rose-500 shadow-[inset_-4px_-4px_8px_rgba(255,255,255,0.2),inset_4px_4px_8px_rgba(0,0,0,0.1)] text-white' : 'bg-[#f0f3f6] text-slate-500 hover:text-slate-700 shadow-[-4px_-4px_8px_#ffffff,4px_4px_8px_#d1d9e6] hover:-translate-y-0.5 hover:shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] active:shadow-[inset_-2px_-2px_4px_#ffffff,inset_2px_2px_4px_#d1d9e6] active:translate-y-0'"
                                >
                                    <span v-if="isListening" class="absolute inset-0 rounded-xl bg-red-400 animate-ping opacity-30"></span>
                                    <svg :class="isListening ? 'text-white' : 'text-slate-500'" class="w-5 h-5 relative z-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" /></svg>
                                </button>
                                <!-- Send button -->
                                <button type="button" @click="sendChatPrompt" :disabled="isSubmitting"
                                    class="relative flex-shrink-0 w-11 h-11 flex items-center justify-center rounded-xl bg-indigo-500 text-white shadow-[-4px_-4px_8px_#ffffff,4px_4px_8px_#d1d9e6] hover:-translate-y-0.5 hover:shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] active:shadow-[inset_-2px_-2px_4px_rgba(255,255,255,0.3),inset_2px_2px_4px_rgba(0,0,0,0.2)] active:translate-y-0 transition-all duration-300 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed border-none"
                                    title="Send message"
                                >
                                    <ArrowPathIcon v-if="isSubmitting" class="w-5 h-5 animate-spin" />
                                    <PaperAirplaneIcon v-else class="w-5 h-5" />
                                </button>
                            </div>

                            <!-- Listening indicator -->
                            <div v-if="isListening" class="mt-2.5 flex items-center gap-2 bg-red-50/50 px-3 py-1.5 rounded-lg border border-red-100/30 max-w-max">
                                <span class="flex gap-0.5 items-end h-3">
                                    <span class="w-0.5 bg-red-500 rounded-full animate-bounce" style="height:4px; animation-delay:0ms"></span>
                                    <span class="w-0.5 bg-red-500 rounded-full animate-bounce" style="height:8px; animation-delay:100ms"></span>
                                    <span class="w-0.5 bg-red-500 rounded-full animate-bounce" style="height:11px; animation-delay:200ms"></span>
                                    <span class="w-0.5 bg-red-500 rounded-full animate-bounce" style="height:8px; animation-delay:300ms"></span>
                                    <span class="w-0.5 bg-red-500 rounded-full animate-bounce" style="height:4px; animation-delay:400ms"></span>
                                </span>
                                <span class="text-[9px] font-black text-red-500 uppercase tracking-widest">
                                    {{ preferredLanguage === 'ta' ? 'கேட்கிறேன்... பேசுங்கள்' : 'Listening... speak now' }}
                                </span>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Chat History Slide-Over Panel ── -->
    <div v-if="isHistoryPanelOpen" class="fixed inset-0 z-[110] overflow-hidden" role="dialog" aria-modal="true">
        <div class="absolute inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="closeHistoryPanel"></div>
            <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                <div class="pointer-events-auto w-screen max-w-2xl">
                    <div class="flex h-full flex-col bg-white shadow-2xl border-l border-slate-200">

                        <!-- Header -->
                        <div class="bg-gradient-to-r from-amber-50 to-orange-50 px-6 py-5 border-b border-amber-100 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="p-2.5 bg-amber-100 rounded-xl"><ClockIcon class="w-5 h-5 text-amber-600" /></div>
                                <div>
                                    <h2 class="text-base font-black text-slate-900">Chat History</h2>
                                    <p class="text-[10px] text-amber-700 font-semibold uppercase tracking-widest">Past conversations — stored for analysis</p>
                                </div>
                            </div>
                            <button type="button" @click="closeHistoryPanel" class="rounded-xl p-2 text-slate-400 hover:text-slate-700 hover:bg-white/70 transition-all cursor-pointer">
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <!-- Two-column layout -->
                        <div class="flex flex-1 min-h-0">

                            <!-- LEFT: Session List -->
                            <div class="w-80 shrink-0 flex flex-col border-r border-slate-100 bg-slate-50">
                                <div class="px-4 py-3 border-b border-slate-100">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Sessions</p>
                                </div>
                                <div v-if="historyLoading" class="flex-1 flex items-center justify-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <ArrowPathIcon class="w-6 h-6 text-amber-500 animate-spin" />
                                        <span class="text-xs text-slate-400">Loading history...</span>
                                    </div>
                                </div>
                                <div v-else-if="historyList.length === 0" class="flex-1 flex items-center justify-center">
                                    <div class="text-center px-4">
                                        <ChatBubbleOvalLeftIcon class="w-10 h-10 text-slate-200 mx-auto mb-2" />
                                        <p class="text-xs text-slate-400 font-semibold">No chat history yet</p>
                                        <p class="text-[10px] text-slate-300 mt-1">Sessions will appear here after you close a conversation</p>
                                    </div>
                                </div>
                                <div v-else class="flex-1 overflow-y-auto divide-y divide-slate-100">
                                    <button v-for="item in historyList" :key="item.id" type="button" @click="viewHistorySession(item.id)"
                                        class="w-full text-left px-4 py-3 hover:bg-white transition-colors group cursor-pointer"
                                        :class="historyViewSession?.id === item.id ? 'bg-white border-l-2 border-amber-400' : ''"
                                    >
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.5">{{ item.agent_name }}</span>
                                            <span class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded border"
                                                :class="item.session_language === 'ta' ? 'text-green-700 bg-green-50 border-green-100' : 'text-slate-500 bg-slate-50 border-slate-200'"
                                            >{{ item.session_language === 'ta' ? 'Tamil' : item.session_language === 'auto' ? 'Auto' : 'English' }}</span>
                                        </div>
                                        <p class="text-[11px] text-slate-600 font-medium leading-snug line-clamp-2 mb-1.5">{{ item.summary || '(No summary)' }}</p>
                                        <div class="flex items-center justify-between">
                                            <span class="text-[9px] text-slate-400">{{ item.message_count }} messages</span>
                                            <span class="text-[9px] text-slate-400">{{ formatHistoryDate(item.created_at) }}</span>
                                        </div>
                                    </button>
                                </div>
                                <!-- Paginator -->
                                <div v-if="historyLastPage > 1" class="border-t border-slate-100 px-4 py-2 flex items-center justify-between">
                                    <button type="button" :disabled="historyCurrentPage <= 1" @click="fetchHistory(historyCurrentPage - 1)" class="text-[10px] font-bold px-2.5 py-1 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-all">← Prev</button>
                                    <span class="text-[10px] text-slate-400 font-semibold">{{ historyCurrentPage }} / {{ historyLastPage }}</span>
                                    <button type="button" :disabled="historyCurrentPage >= historyLastPage" @click="fetchHistory(historyCurrentPage + 1)" class="text-[10px] font-bold px-2.5 py-1 rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer transition-all">Next →</button>
                                </div>
                            </div>

                            <!-- RIGHT: Session Viewer -->
                            <div class="flex-1 flex flex-col min-h-0">
                                <div v-if="!historyViewSession" class="flex-1 flex items-center justify-center">
                                    <div class="text-center">
                                        <ClockIcon class="w-12 h-12 text-slate-150 mx-auto mb-3" />
                                        <p class="text-sm font-semibold text-slate-400">Select a session to view</p>
                                        <p class="text-xs text-slate-300 mt-1">Full conversation will appear here</p>
                                    </div>
                                </div>
                                <div v-else class="flex flex-col h-full">
                                    <div class="px-5 py-3 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-700 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.5">{{ historyViewSession.agent_name }}</span>
                                            <span class="text-[10px] text-slate-400 font-semibold">{{ historyViewSession.message_count }} messages</span>
                                            <span class="text-[10px] text-slate-400">{{ formatHistoryDate(historyViewSession.created_at) }}</span>
                                        </div>
                                    </div>
                                    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3 bg-slate-50/50">
                                        <div v-for="(msg, mi) in historyViewSession.messages" :key="mi" class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                                            <div class="max-w-[82%] rounded-2xl px-4 py-2.5 text-xs leading-relaxed shadow-sm"
                                                :class="[msg.role === 'user' ? 'bg-indigo-600 text-white rounded-br-none' : msg.role === 'error' ? 'bg-red-50 text-red-700 border border-red-200 rounded-bl-none' : 'bg-white text-slate-700 border border-slate-200 rounded-bl-none']"
                                            >
                                                <p class="text-[8px] font-black uppercase tracking-widest mb-1 opacity-60">{{ msg.role === 'user' ? 'You' : historyViewSession.agent_name }}</p>
                                                <div v-if="msg.image" class="mb-2 max-w-full">
                                                    <img :src="msg.image" class="max-w-full max-h-36 rounded-lg object-contain shadow-sm" />
                                                </div>
                                                <div v-if="msg.role === 'agent'">
                                                    <div v-if="parseMessageContent(msg.text).hasChart" class="w-full">
                                                        <div class="whitespace-pre-wrap text-slate-800" v-html="formatMarkdown(parseMessageContent(msg.text).beforeText)"></div>
                                                        <div class="my-4 p-4 bg-slate-900 border border-slate-800 rounded-2xl shadow-inner text-white min-w-[280px]">
                                                            <p class="text-xs font-black uppercase tracking-widest text-indigo-400 mb-3">{{ parseMessageContent(msg.text).chartData.title || 'Data Analysis Chart' }}</p>
                                                            <apexchart
                                                                :type="getChartType(msg.text, 'hist-' + mi)"
                                                                height="220"
                                                                :options="getChartOptions(parseMessageContent(msg.text).chartData, getChartType(msg.text, 'hist-' + mi))"
                                                                :series="getChartSeries(parseMessageContent(msg.text).chartData, getChartType(msg.text, 'hist-' + mi))"
                                                            />
                                                        </div>
                                                        <div class="whitespace-pre-wrap text-slate-800" v-html="formatMarkdown(parseMessageContent(msg.text).afterText)"></div>
                                                    </div>
                                                    <div v-else class="whitespace-pre-wrap text-slate-800" v-html="formatMarkdown(msg.text)"></div>
                                                </div>
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
</template>

<style scoped>
@keyframes shimmer {
    0%   { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50%       { transform: translateY(-8px); }
}
@keyframes rotate-slow {
    0%   { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 12px rgba(99, 102, 241, 0.25), 0 0 4px rgba(99, 102, 241, 0.15); }
    50%       { box-shadow: 0 0 22px rgba(99, 102, 241, 0.65), 0 0 8px rgba(99, 102, 241, 0.35); }
}
.animate-shimmer    { animation: shimmer 2s infinite linear; }
.animate-float      { animation: float 5s ease-in-out infinite; }
.animate-rotate-slow { animation: rotate-slow 25s linear infinite; }
.animate-pulse-glow { animation: pulse-glow 2s infinite ease-in-out; }
</style>
