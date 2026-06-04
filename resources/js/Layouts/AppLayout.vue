<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { usePermissions } from '@/Composables/usePermissions';
import ApplicationMark from '@/Components/ApplicationMark.vue';
import Banner from '@/Components/Banner.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import NavLink from '@/Components/NavLink.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { 
    HomeIcon, 
    Square3Stack3DIcon, 
    CreditCardIcon, 
    Cog6ToothIcon, 
    SwatchIcon, 
    ClipboardDocumentListIcon, 
    IdentificationIcon, 
    BriefcaseIcon, 
    ChartBarIcon,
    BellIcon,
    UserCircleIcon,
    ArrowUpRightIcon,
    ArrowDownLeftIcon,
    ClockIcon,
    DocumentTextIcon,
    ArrowDownOnSquareIcon,
    ArrowUpOnSquareIcon,
    ChartPieIcon,
    CogIcon,
    UserPlusIcon,
    ReceiptPercentIcon,
    ScaleIcon,
    PaintBrushIcon,
    BuildingLibraryIcon,
    TruckIcon,
    ShoppingCartIcon,
    ChatBubbleLeftEllipsisIcon,
    XMarkIcon,
    ArrowPathIcon,
    CpuChipIcon,
    ChatBubbleOvalLeftIcon,
    PaperAirplaneIcon,
    SparklesIcon
} from '@heroicons/vue/24/outline';
import Toast from 'primevue/toast';
import ConfirmDialog from 'primevue/confirmdialog';
import BaseButton from '@/Components/Base/BaseButton.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';


const IconMap = {
    HomeIcon, 
    Square3Stack3DIcon, 
    CreditCardIcon, 
    Cog6ToothIcon, 
    SwatchIcon, 
    ClipboardDocumentListIcon, 
    IdentificationIcon, 
    BriefcaseIcon, 
    ChartBarIcon, 
    BellIcon, 
    UserCircleIcon, 
    ArrowUpRightIcon, 
    ArrowDownLeftIcon, 
    ClockIcon, 
    DocumentTextIcon, 
    ArrowDownOnSquareIcon, 
    ArrowUpOnSquareIcon, 
    ChartPieIcon, 
    CogIcon, 
    UserPlusIcon,
    ReceiptPercentIcon,
    ScaleIcon,
    PaintBrushIcon,
    BuildingLibraryIcon,
    TruckIcon,
    ShoppingCartIcon,
    ChatBubbleLeftEllipsisIcon,
    XMarkIcon,
    ArrowPathIcon,
    CpuChipIcon,
    ChatBubbleOvalLeftIcon,
    SparklesIcon
};

const page = usePage();
const { can, isSuperAdmin } = usePermissions();
const activeEntity = computed(() => page.props.active_entity);

// Dynamically source top navigation from database menus
const visibleNav = computed(() => {
    const menus = page.props.menus?.top_nav || [];
    return menus.filter(item => !item.permission_name || can(item.permission_name));
});

const isTopMenuActive = (item) => {
    const currentUrl = page.url.toLowerCase();

    if (item.alias && currentUrl.startsWith('/' + item.alias.toLowerCase())) return true;
    if (item.link && item.link !== '#' && currentUrl.startsWith('/' + item.link.toLowerCase())) return true;

    // Only consider children the current user is permitted to see
    const children = (page.props.menus?.sidebar_nav?.[item.id] || []).filter(
        child => !child.permission_name || can(child.permission_name)
    );
    for (const child of children) {
        if (child.alias && currentUrl.startsWith('/' + child.alias.toLowerCase())) return true;
        if (child.link && child.link !== '#' && currentUrl.startsWith('/' + child.link.toLowerCase())) return true;
    }

    return false;
};

/**
 * Returns sidebar children for a given top-nav item, filtered by permission.
 * Use this wherever the sidebar is rendered instead of reading sidebar_nav directly.
 */
const visibleSideNav = (topItemId) => {
    const children = page.props.menus?.sidebar_nav?.[topItemId] || [];
    return children.filter(child => !child.permission_name || can(child.permission_name));
};
console.log(page.props.auth.user);
defineProps({
    title: String,
});

const showingNavigationDropdown = ref(false);

const switchToTeam = (team) => {
    router.put(route('currentteam.update'), {
        team_id: team.id,
    }, {
        preserveState: false,
    });
};

const logout = () => {
    router.post(route('logout'));
};

const switchEntity = async (entityId) => {
    try {
        await axios.post('/context/selectentity', { entity_id: entityId });
        // Hard navigate so Inertia shared props (active_entity) are refreshed with the new role
        window.location.href = '/dashboard';
    } catch (e) {
        console.error('Entity switch failed', e);
    }
};

const toggleFooterSuspension = async () => {
    if (!activeEntity.value) return;
    if (activeEntity.value.is_suspended !== 0 && !isSuperAdmin.value) {
        alert("Action Denied:\nOnly a Super Administrator can reactivate a suspended organization.");
        return;
    }
    try {
        const { data } = await axios.post('/context/toggle-suspension', { entity_id: activeEntity.value.entity_id });
        if (data.status === 'success') {
            window.location.href = '/context/selectentity';
        }
    } catch (error) {
        alert(error.response?.data?.error || 'Failed to toggle suspension.');
    }
};

// --- Session Idle Timeout Logic ---
const IDLE_WARN_TIME = 10 * 60 * 1000; // 10 minutes warning
const IDLE_LOGOUT_TIME = 15 * 60 * 1000; // 15 minutes logout
const CHECK_INTERVAL = 1000; // 1 second

const lastActivity = ref(Date.now());
const showTimeoutModal = ref(false);
const remainingTime = ref(0);
let idleInterval = null;
let heartbeatInterval = null;

const resetTimer = () => {
    const now = Date.now();
    // Throttle activity updates to once every 2 seconds
    if (now - lastActivity.value < 2000) return;
    
    lastActivity.value = now;
    if (showTimeoutModal.value) {
        showTimeoutModal.value = false;
        pingSession();
    }
};

const pingSession = async () => {
    try {
        await axios.get(route('session.ping'));
    } catch (e) {
        console.error('Session heartbeat failed', e);
    }
};

const checkIdleTime = () => {
    const now = Date.now();
    const idleDuration = now - lastActivity.value;

    if (idleDuration >= IDLE_LOGOUT_TIME) {
        logout();
        clearInterval(idleInterval);
        if (heartbeatInterval) clearInterval(heartbeatInterval);
    } else if (idleDuration >= IDLE_WARN_TIME) {
        showTimeoutModal.value = true;
        remainingTime.value = Math.floor((IDLE_LOGOUT_TIME - idleDuration) / 1000);
    } else {
        showTimeoutModal.value = false;
    }
};

onMounted(() => {
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
    events.forEach(event => window.addEventListener(event, resetTimer));

    idleInterval = setInterval(checkIdleTime, CHECK_INTERVAL);
    
    // Send a heartbeat to the server every 4 minutes if the user is active
    heartbeatInterval = setInterval(() => {
        const idleDuration = Date.now() - lastActivity.value;
        if (idleDuration < 5 * 60 * 1000) {
            pingSession();
        }
    }, 4 * 60 * 1000);
});

onUnmounted(() => {
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
    events.forEach(event => window.removeEventListener(event, resetTimer));
    
    if (idleInterval) clearInterval(idleInterval);
    if (heartbeatInterval) clearInterval(heartbeatInterval);
});

const mobileMenuOpen = ref(false);

// --- AI Chat Assistant Drawer Logic ---
const isChatDrawerOpen = ref(false);
const chatMessages = ref([]);
const currentPrompt = ref('');
const isSubmitting = ref(false);
const preferredLanguage = ref('auto');
const currentChatSessionId = ref(null);

// Speech Recognition (Voice Input)
const isListening = ref(false);
const recognitionError = ref(null);
let activeRecognition = null;

// Speech Synthesis
const currentlySpeakingMessageIndex = ref(null);
let currentUtterance = null;
const voicesList = ref([]);

// Active Category for predetermined questions
const selectedCategory = ref('');

// Get the user's role-based agent configuration
const activeAgent = computed(() => {
    const role = page.props.user_role;
    if (role === 'Accountant' || role === 'Senior Accountant' || role === 'CFO') {
        return {
            name: 'Accountant',
            class: 'App\\Ai\\Agents\\Accountant',
            defaultGreeting: 'Hi, I am your Accountant AI Assistant. How can I help you with your RMC ledgers or financial statements today?'
        };
    }
    // Fallback to Onemodo
    return {
        name: 'Onemodo',
        class: 'App\\Ai\\Agents\\Onemodo',
        defaultGreeting: 'Hi, I am Onemodo, your RMC operations assistant. How can I help you with your plant operations or dispatches today?'
    };
});

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
    currentUtterance = null;
    stopSpeechRecognition();
    await saveHistoryToServer();
    isChatDrawerOpen.value = false;
    chatMessages.value = [];
    currentPrompt.value = '';
    currentChatSessionId.value = null;
};

// Available Categories depending on the agent
const availableCategories = computed(() => {
    const isTamil = preferredLanguage.value === 'ta';
    if (activeAgent.value.name === 'Accountant') {
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

// Predefined Questions
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

// Text-to-Speech (TTS)
if (typeof window !== 'undefined' && window.speechSynthesis) {
    const loadVoices = () => {
        voicesList.value = window.speechSynthesis.getVoices();
    };
    loadVoices();
    if (window.speechSynthesis.onvoiceschanged !== undefined) {
        window.speechSynthesis.onvoiceschanged = loadVoices;
    }
}

const toggleSpeech = (text, index) => {
    if (currentlySpeakingMessageIndex.value === index) {
        window.speechSynthesis.cancel();
        currentlySpeakingMessageIndex.value = null;
        currentUtterance = null;
        return;
    }

    window.speechSynthesis.cancel();

    let cleanText = text
        .replace(/```json[\s\S]*?```/g, '')
        .replace(/```[\s\S]*?```/g, '')
        .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
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

// Speech Recognition (Speech to Text)
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
        if (event.error === 'aborted') {
            // silent
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

// Send Prompt to Agent Test Sandbox
const sendChatPrompt = async () => {
    if (!currentPrompt.value.trim() || isSubmitting.value) return;

    const userText = currentPrompt.value;
    chatMessages.value.push({ role: 'user', text: userText });
    currentPrompt.value = '';
    isSubmitting.value = true;

    // Save user's message immediately
    saveHistoryToServer();

    try {
        const response = await axios.post(route('settings.agents.test'), {
            prompt: userText,
            agent_class: activeAgent.value.class,
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
            chatMessages.value.push({ role: 'agent', text: reply });
        } else {
            chatMessages.value.push({
                role: 'error',
                text: data.error || 'An error occurred while executing the prompt.',
            });
        }
    } catch (err) {
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

// Save chat history session on close
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

// ── Chat History Replay Drawer in AppLayout ────────────────────
const isHistoryPanelOpen = ref(false);
const historyList = ref([]);
const historyCurrentPage = ref(1);
const historyLastPage = ref(1);
const historyLoading = ref(false);
const historyViewSession = ref(null);

const fetchHistory = async (page = 1) => {
    historyLoading.value = true;
    try {
        const res = await axios.get(route('settings.agents.history.index'), {
            params: { page, agent: activeAgent.value.name },
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

const viewHistorySession = async (id) => {
    try {
        const res = await axios.get(route('settings.agents.history.show', { history: id }));
        historyViewSession.value = res.data;
    } catch (e) {
        // ignore
    }
};

const formatHistoryDate = (ts) => {
    if (!ts) return '';
    const d = new Date(ts);
    return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
        + ' ' + d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
};

// Helper Markdown formatter
const formatMarkdown = (text) => {
    if (!text) return '';
    let escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    
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
    formattedText = formattedText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    formattedText = formattedText.replace(/\*(.*?)\*/g, '<em>$1</em>');
    formattedText = formattedText.replace(/`(.*?)`/g, '<code class="bg-indigo-50 text-indigo-700 px-1 py-0.5 rounded font-mono text-xs font-semibold">$1</code>');
    formattedText = formattedText.replace(/^\s*[-*]\s+(.*?)$/gm, '<li class="ml-4 list-disc mt-1 text-slate-750">$1</li>');

    return formattedText;
};
</script>

<template>
    <div>
        <Head :title="title" />
        <Banner />
        <Toast :life="1500" />
        <ConfirmDialog />

        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex flex-col">
            <nav class="bg-customBlue-600 dark:bg-customBlue-800 border-b border-customBlue-800 w-full z-50 shadow-md relative">
                <div class="w-full px-2 sm:px-4 lg:px-8">
                    <div class="flex items-center justify-between h-16 md:h-20">
                        
                        <!-- Mobile Hamburger & Logo Block (visible below md) -->
                        <div class="flex items-center md:hidden gap-3 pl-2">
                            <button 
                                @click="mobileMenuOpen = true"
                                class="p-2 rounded-lg text-blue-200 hover:text-amber-300 hover:bg-white/10 focus:outline-none transition-colors"
                            >
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                                </svg>
                            </button>
                            <Link :href="route('dashboard')" class="flex items-center">
                                <img
                                    v-if="$page.props.active_plant?.plant_logo"
                                    :src="$page.props.active_plant.plant_logo"
                                    alt="Plant Logo"
                                    class="w-auto object-contain bg-white/10 rounded-lg p-1"
                                />
                                <img
                                    v-else-if="activeEntity?.entity_logo"
                                    :src="`/storage/${activeEntity.entity_logo}`"
                                    alt="Logo"
                                    class="h-9 w-auto object-contain bg-white/10 rounded-lg p-1"
                                />
                                <ApplicationMark v-else class="h-8 w-auto" />
                            </Link>
                        </div>

                        <!-- Desktop Logo (visible on md and up) -->
                        <div class="hidden md:flex w-64 shrink-0 items-center mr-2 pl-2">
                            <Link :href="route('dashboard')" class="flex items-center gap-3 group">
                                <!-- Plant Logo Priority -->
                                <div v-if="$page.props.active_plant?.plant_logo" class=" flex items-center justify-center rounded-xl p-1.5 transition-all group-hover:bg-white/20">
                                    <img
                                        :src="$page.props.active_plant.plant_logo"
                                        alt="Plant Logo"
                                        class="h-full w-full object-contain"
                                    />
                                </div>
                                <!-- Entity Logo Fallback -->
                                <div v-else-if="activeEntity?.entity_logo" class="h-16 w-32 flex items-center justify-center bg-white/10 rounded-xl p-1.5 transition-all group-hover:bg-white/20">
                                    <img
                                        :src="`/storage/${activeEntity.entity_logo}`"
                                        alt="Logo"
                                        class="h-full w-full object-contain"
                                    />
                                </div>
                                <ApplicationMark v-else class="h-12 w-auto transition-transform group-hover:scale-105" />
                            </Link>
                        </div>

                        <!-- Scrollable Navigation Items (Hidden on mobile/tablet below md, visible on md and up) -->
                        <div class="hidden md:flex flex-1 overflow-x-auto no-scrollbar">
                            <div class="flex items-stretch justify-evenly min-w-max md:min-w-0 h-full">
                                <Link
                                    v-for="item in visibleNav"
                                    :key="item.id"
                                    :href="item.link === '#' ? '#' : (item.link.startsWith('/') ? item.link : '/' + item.link)"
                                    :class="[
                                        isTopMenuActive(item)
                                            ? 'border-b-4 border-amber-400 text-amber-400 bg-white/10'
                                            : 'border-b-4 border-transparent text-blue-200 hover:text-amber-300 hover:bg-white/10 hover:border-amber-300/50',
                                        'flex flex-col items-center justify-center py-2 px-3 md:px-5 min-w-[3.5rem] md:min-w-[5rem] group focus:outline-none transition-all duration-200 cursor-pointer'
                                    ]"
                                >
                                    <component
                                        :is="IconMap[item.icon] || IconMap['HomeIcon']"
                                        :class="[
                                            isTopMenuActive(item)
                                                ? 'text-amber-400'
                                                : 'text-blue-300 group-hover:text-amber-300',
                                            'h-6 w-6 md:h-7 md:w-7 mb-0.5 transition-colors duration-200 shrink-0'
                                        ]"
                                        aria-hidden="true"
                                    />
                                    <span
                                        :class="[
                                            isTopMenuActive(item)
                                                ? 'text-amber-400 font-bold'
                                                : 'text-blue-200 group-hover:text-amber-300 font-semibold',
                                            'text-[9px] md:text-[10px] uppercase tracking-wide whitespace-nowrap transition-colors duration-200'
                                        ]"
                                    >{{ item.title }}</span>
                                </Link>
                            </div>
                        </div>

                        <div class="flex items-center shrink-0 ml-2">
                            <!-- Entity Switcher Dropdown -->
                            <!-- <div v-if="$page.props.user_entities && $page.props.user_entities.length > 0" class="ms-2 relative">
                                <Dropdown align="right" width="64">
                                    <template #trigger>
                                        <button type="button" class="inline-flex items-center gap-2 px-2 py-1 rounded-lg text-xs font-medium border border-blue-400/40 bg-white/10 text-blue-100 hover:border-amber-400/60 hover:text-amber-300 transition-all duration-150 focus:outline-none">
                                            <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0" />
                                            <span class="max-w-[100px] truncate hidden sm:block">{{ $page.props.active_entity?.entity_name ?? 'Workspace' }}</span>
                                            <svg class="w-3.5 h-3.5 text-blue-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                            </svg>
                                        </button>
                                    </template>
                                    <template #content>
                                        <div class="w-64 py-1">
                                            <div class="px-4 py-2 text-xs text-gray-400 font-semibold uppercase tracking-wide border-b border-gray-100 dark:border-gray-700">
                                                Switch Workspace
                                            </div>
                                            <div class="max-h-[250px] overflow-y-auto">
                                                <button
                                                    v-for="eu in $page.props.user_entities"
                                                    :key="eu.entity_id"
                                                    @click="switchEntity(eu.entity_id)"
                                                    class="w-full text-left flex items-center gap-3 px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                                                    :class="eu.is_active ? 'text-pastelbeaver-600 dark:text-pastelbeaver-400 bg-pastelbeaver-50 dark:bg-pastelbeaver-900/20' : 'text-gray-700 dark:text-gray-300'"
                                                >
                                                    <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                                        <img v-if="eu.entity_logo" :src="`/storage/${eu.entity_logo}`" class="w-full h-full object-contain p-0.5" />
                                                        <span v-else class="text-sm font-bold text-gray-500">{{ eu.entity_name.charAt(0) }}</span>
                                                    </div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="font-medium truncate">{{ eu.entity_name }}</p>
                                                        <p class="text-xs text-gray-400 truncate">{{ eu.role_name }}</p>
                                                    </div>
                                                    <svg v-if="eu.is_active" class="w-4 h-4 text-pastelbeaver-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div> -->

                            <!-- Notification Bell -->
                            <!-- <button class="relative p-2 text-blue-200 hover:text-amber-300 transition-colors mr-1 sm:mr-2 focus:outline-none rounded-full">
                                <BellIcon class="h-6 w-6 md:h-7 md:w-7" aria-hidden="true" />
                                <span class="absolute top-1.5 right-1.5 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-500 rounded-full ring-2 ring-[#1e3a5f]">
                                    0
                                </span>
                            </button> -->

                            <!-- AI Chat Assistant Icon -->
                            <!-- <button
                                v-if="$page.props.auth.user"
                                @click="openGlobalChat"
                                class="group relative flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-indigo-400/30 hover:border-indigo-400/60 bg-gradient-to-r from-indigo-500/10 via-blue-500/5 to-indigo-500/10 dark:from-indigo-500/20 dark:to-blue-500/10 text-indigo-200 hover:text-amber-300 transition-all duration-300 shadow-[0_0_12px_rgba(99,102,241,0.15)] hover:shadow-[0_0_20px_rgba(99,102,241,0.45)] focus:outline-none mr-2 overflow-hidden cursor-pointer"
                                title="AI Assistant Chat"
                            >
                                 
                                <div class="absolute inset-0 -translate-x-full group-hover:animate-shimmer bg-gradient-to-r from-transparent via-white/10 to-transparent z-0"></div>
                                
                                <div class="relative z-10 flex items-center gap-1.5">
                                    <component 
                                        :is="IconMap['SparklesIcon']" 
                                        class="h-4.5 w-4.5 text-indigo-300 group-hover:text-amber-300 transition-all duration-300 group-hover:rotate-12"
                                    />
                                    <span class="text-xs font-bold tracking-wide hidden sm:inline-block">AI Assistant</span>
                                    
                                     
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                                    </span>
                                </div>
                            </button> -->

                            <div class="ms-3 relative">
                                <!-- Teams Dropdown -->
                                <Dropdown v-if="$page.props.jetstream.hasTeamFeatures" align="right" width="60">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none focus:bg-gray-50 dark:focus:bg-gray-700 active:bg-gray-50 dark:active:bg-gray-700 transition ease-in-out duration-150">
                                                <img v-if="$page.props.jetstream.managesProfilePhotos" class="size-6 rounded-full object-cover me-2 focus:border-pastelbeaver-400" :src="'/storage/'+$page.props.auth.user.profile_photo_path" :alt="$page.props.auth.user.username">
                                                <svg v-else class="me-2 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                                </svg>
                                                {{ $page.props.auth.user.current_team.name }}

                                                <svg class="ms-2 -me-0.5 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15L12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
                                                </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="w-60">
                                            <!-- Team Management -->
                                            <div class="block px-4 py-2 text-xs text-gray-400">
                                                Manage Team
                                            </div>

                                            <!-- Team Settings -->
                                            <DropdownLink :href="route('teams.show', $page.props.auth.user.current_team)">
                                                Team Settings
                                            </DropdownLink>

                                            <DropdownLink v-if="$page.props.jetstream.canCreateTeams" :href="route('teams.create')">
                                                Create New Team
                                            </DropdownLink>

                                            <!-- Team Switcher -->
                                            <template v-if="$page.props.auth.user.all_teams.length > 1">
                                                <div class="border-t border-gray-200 dark:border-gray-600" />

                                                <div class="block px-4 py-2 text-xs text-gray-400">
                                                    Switch Teams
                                                </div>

                                                <template v-for="team in $page.props.auth.user.all_teams" :key="team.id">
                                                    <form @submit.prevent="switchToTeam(team)">
                                                        <DropdownLink as="button">
                                                            <div class="flex items-center">
                                                                <svg v-if="team.id == $page.props.auth.user.current_team_id" class="me-2 size-5 text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                </svg>

                                                                <div>{{ team.name }}</div>
                                                            </div>
                                                        </DropdownLink>
                                                    </form>
                                                </template>
                                            </template>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Settings Dropdown -->
                            <div class="ms-3 relative">
                                <Dropdown align="right" width="72" :contentClasses="['bg-[#f0f3f6] rounded-[24px] shadow-[-2px_-2px_3px_#ffffff,2px_2px_3px_#d1d9e6] overflow-hidden p-2']">
                                    <template #trigger>
                                        <button v-if="$page.props.jetstream.managesProfilePhotos" class="flex items-center gap-2 p-1 pr-3 text-sm bg-white/10 hover:bg-white/20 border border-white/10 rounded-full transition-all duration-300 group focus:outline-none">
                                            <div class="relative">
                                                <img class="size-8 rounded-full object-cover border-2 border-indigo-400/50 group-hover:border-indigo-400" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.username">
                                                <div class="absolute bottom-0 right-0 size-2.5 bg-emerald-500 border-2 border-[#1e293b] rounded-full"></div>
                                            </div>
                                            <div class="flex flex-col items-start leading-tight">
                                                <span class="text-xs font-bold text-white tracking-tight">{{ $page.props.auth.user.username }}</span>
                                                <span class="text-[9px] text-indigo-200 font-medium uppercase tracking-widest">Active</span>
                                            </div>
                                            <svg class="size-3.5 text-indigo-300 group-hover:text-white transition-colors ml-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>

                                        <button v-else type="button" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-bold rounded-xl bg-white/10 text-indigo-100 hover:bg-white/20 hover:text-white border border-white/5 transition-all duration-200 focus:outline-none">
                                            <div class="size-6 rounded-lg bg-indigo-500/20 flex items-center justify-center text-indigo-300">
                                                {{ $page.props.auth.user.username.charAt(0).toUpperCase() }}
                                            </div>
                                            {{ $page.props.auth.user.username }}
                                            <svg class="size-4 text-indigo-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                            </svg>
                                        </button>
                                    </template>

                                    <template #content>
                                        <!-- User Branding Header -->
                                        <div class="px-5 py-5 border-b border-slate-200/50 mb-2">
                                            <div class="flex items-center gap-3">
                                                <div class="size-10 rounded-xl bg-[#f0f3f6] shadow-[inset_-4px_-4px_8px_#ffffff,inset_4px_4px_8px_#d1d9e6] flex items-center justify-center text-slate-700 font-black text-lg">
                                                    {{ $page.props.auth.user.username.charAt(0).toUpperCase() }}
                                                </div>
                                                <div class="flex flex-col min-w-0">
                                                    <span class="text-[15px] font-bold text-slate-800 leading-tight truncate">{{ $page.props.auth.user.username }}</span>
                                                    <span class="text-[11px] text-slate-500 font-medium mt-0.5 truncate">{{ $page.props.auth.user.email }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Primary Actions -->
                                        <div class="px-2 space-y-4 pb-3">
                                            <Link :href="route('profile.show')" class="group block w-full rounded-2xl bg-[#f0f3f6] shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] p-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-[-8px_-8px_16px_#ffffff,8px_8px_16px_#cbd5e1]">
                                                <div class="flex items-center gap-4"> 
                                                    <div class="text-slate-500 group-hover:text-amber-500 transition-colors">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                        </svg>
                                                    </div>
                                                    <span class="text-[14px] font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Edit profile</span>
                                                </div>
                                            </Link>

                                            <Link :href="route('profile.show')" class="group block w-full rounded-2xl bg-[#f0f3f6] shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] p-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-[-8px_-8px_16px_#ffffff,8px_8px_16px_#cbd5e1]">
                                                <div class="flex items-center gap-4"> 
                                                    <div class="text-slate-500 group-hover:text-amber-500 transition-colors">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 1 1 15 0 7.5 7.5 0 0 1-15 0Z" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                                        </svg>
                                                    </div>
                                                    <span class="text-[14px] font-bold text-slate-700 group-hover:text-slate-900 transition-colors">Account settings</span>
                                                </div>
                                            </Link>

                                            <!-- Switch Context Options -->
                                            <div class="border-t border-slate-200/50 my-2 mx-2" />

                                            <Link :href="route('entity-context.index')" v-if="$page.props.user_role === 'Platform Admin' || $page.props.user_role === 'Saas Owner'" class="group block w-full rounded-2xl bg-[#f0f3f6] shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] p-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-[-8px_-8px_16px_#ffffff,8px_8px_16px_#cbd5e1]">
                                                <div class="flex items-center justify-between w-full">
                                                    <div class="flex items-center gap-4"> 
                                                        <div class="text-slate-500 group-hover:text-amber-500 transition-colors">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h5.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819" />
                                                            </svg>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="text-[14px] font-bold text-slate-700 leading-tight">Switch Entity</span>
                                                            <span class="text-[11px] text-slate-400 font-medium truncate max-w-[120px]">{{ $page.props.active_entity?.entity_name || 'Select Entity' }}</span>
                                                        </div>
                                                    </div>
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3 text-slate-400 group-hover:text-slate-600 transition-colors">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                    </svg>
                                                </div>
                                            </Link>

                                            <Link :href="route('entity-context.index')" v-if="$page.props.plants_count > 1" class="group block w-full rounded-2xl bg-[#f0f3f6] shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] p-4 transition-all duration-300 hover:-translate-y-1 hover:shadow-[-8px_-8px_16px_#ffffff,8px_8px_16px_#cbd5e1]">
                                                <div class="flex items-center justify-between w-full">
                                                    <div class="flex items-center gap-4"> 
                                                        <div class="text-slate-500 group-hover:text-amber-500 transition-colors">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6h1.5m-1.5 3h1.5m-1.5 3h1.5M9 16.5h1.5m3 0h1.5" />
                                                            </svg>
                                                        </div>
                                                        <div class="flex flex-col">
                                                            <span class="text-[14px] font-bold text-slate-700 leading-tight">Switch Plant</span>
                                                            <span class="text-[11px] text-slate-400 font-medium truncate max-w-[120px]">{{ $page.props.active_plant?.plant_name || 'Select Plant' }}</span>
                                                        </div>
                                                    </div>
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-3 text-slate-400 group-hover:text-slate-600 transition-colors">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                                    </svg>
                                                </div>
                                            </Link>

                                            <div class="border-t border-slate-200/50 my-2 mx-2" />

                                            <!-- Authentication -->
                                            <form @submit.prevent="logout">
                                                <button type="submit" class="group block w-full rounded-2xl bg-[#f0f3f6] shadow-[inset_-4px_-4px_8px_#ffffff,inset_4px_4px_8px_#d1d9e6] p-4 transition-all duration-300 hover:shadow-[inset_-6px_-6px_12px_#ffffff,inset_6px_6px_12px_#cbd5e1] text-left">
                                                    <div class="flex items-center gap-4 w-full">
                                                        <div class="text-rose-500 group-hover:text-rose-500 transition-colors">
                                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                                            </svg>
                                                        </div>
                                                        <span class="text-[14px] font-bold text-rose-700 group-hover:text-rose-600 transition-colors">Sign out</span>
                                                    </div>
                                                </button>
                                            </form>
                                        </div>
                                    </template>
                                </Dropdown>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Mobile Menu Drawer Backdrop (visible below md breakpoint) -->
                <div 
                    v-if="mobileMenuOpen" 
                    @click="mobileMenuOpen = false" 
                    class="fixed inset-0 z-[60] bg-slate-900/60 backdrop-blur-sm transition-opacity md:hidden"
                ></div>

                <!-- Mobile Menu Drawer Panel (visible below md breakpoint) -->
                <div 
                    class="fixed inset-y-0 left-0 z-[70] w-80 max-w-xs bg-white dark:bg-gray-800 shadow-2xl transition-transform duration-300 transform md:hidden flex flex-col"
                    :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
                >
                    <!-- Drawer Header -->
                    <div class="px-5 py-5 border-b border-slate-100 dark:border-gray-700 bg-slate-50 dark:bg-gray-900/50 flex justify-between items-center">
                        <div class="flex items-center gap-3">
                            <img
                                v-if="$page.props.active_plant?.plant_logo"
                                :src="$page.props.active_plant.plant_logo"
                                alt="Plant Logo"
                                class="h-9 w-auto object-contain bg-slate-200 dark:bg-gray-700 rounded-lg p-0.5"
                            />
                            <span class="text-xs font-bold text-slate-800 dark:text-white uppercase tracking-wider">Modo Portal</span>
                        </div>
                        <button 
                            @click="mobileMenuOpen = false"
                            class="p-1.5 rounded-lg hover:bg-slate-200 dark:hover:bg-gray-700 text-slate-400 hover:text-slate-600 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Active User Profile Overview -->
                    <div class="px-5 py-4 border-b border-slate-100 dark:border-gray-700 flex items-center gap-3">
                        <img class="size-9 rounded-full object-cover border border-slate-200" :src="$page.props.auth.user.profile_photo_url" :alt="$page.props.auth.user.username">
                        <div class="flex flex-col min-w-0">
                            <span class="text-sm font-bold text-slate-800 dark:text-white truncate">{{ $page.props.auth.user.username }}</span>
                            <span class="text-[10px] text-slate-500 dark:text-gray-400 truncate">{{ $page.props.auth.user.email }}</span>
                        </div>
                    </div>

                    <!-- Drawer Navigation Items -->
                    <div class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest px-3 block mb-2">Main Navigation</span>
                        <Link
                            v-for="item in visibleNav"
                            :key="item.id"
                            :href="item.link === '#' ? '#' : (item.link.startsWith('/') ? item.link : '/' + item.link)"
                            @click="mobileMenuOpen = false"
                            class="flex items-center gap-4 px-4 py-3 text-sm font-semibold rounded-xl transition-all"
                            :class="[
                                isTopMenuActive(item)
                                    ? 'bg-amber-50 dark:bg-amber-950/20 text-amber-600 dark:text-amber-400 border-l-4 border-amber-500'
                                    : 'text-slate-700 dark:text-gray-300 hover:bg-slate-50 dark:hover:bg-gray-700 border-l-4 border-transparent'
                            ]"
                        >
                            <component
                                :is="IconMap[item.icon] || IconMap['HomeIcon']"
                                class="h-5 w-5 shrink-0"
                                :class="isTopMenuActive(item) ? 'text-amber-500' : 'text-slate-400'"
                            />
                            <span>{{ item.title }}</span>
                        </Link>
                    </div>

                    <!-- Drawer Footer Actions -->
                    <div class="p-4 border-t border-slate-100 dark:border-gray-700 bg-slate-50 dark:bg-gray-900/30 space-y-2">
                        <div v-if="$page.props.plants_count > 1" class="w-full">
                            <Link 
                                :href="route('entity-context.index')"
                                @click="mobileMenuOpen = false"
                                class="flex items-center justify-between w-full px-3 py-2.5 bg-white dark:bg-gray-800 rounded-xl border border-slate-200 dark:border-gray-700 text-xs font-semibold text-slate-700 dark:text-gray-300 hover:bg-slate-50 transition-colors"
                            >
                                <div class="flex items-center gap-2">
                                    <component :is="IconMap['BuildingLibraryIcon']" class="w-4 h-4 text-slate-400" />
                                    <span>Switch Plant</span>
                                </div>
                                <span class="text-[10px] text-indigo-600 bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 rounded font-black max-w-[100px] truncate">
                                    {{ $page.props.active_plant?.plant_name || 'Select' }}
                                </span>
                            </Link>
                        </div>
                        
                        <form @submit.prevent="logout">
                            <button 
                                type="submit" 
                                class="w-full py-3 bg-rose-50 dark:bg-rose-950/20 hover:bg-rose-100 text-rose-650 dark:text-rose-455 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-2 border border-rose-100/50"
                            >
                                <svg xmlns="http://www.w3.org/2050/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                </svg>
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <header v-if="$slots.header" class=" dark:bg-gray-800 ">
                <div class="max-w-7xl mx-auto  ">
                    <slot name="header" />
                </div>
            </header>

                <main class="flex-1 max-w-7xl w-full mx-auto sm:px-6 lg:px-8">
                <slot />
            </main>

            <!-- Global Footer -->
            <footer class="bg-[#1d2d3e] text-slate-300 border-t border-[#2a3c50] mt-auto py-8 px-4 sm:px-6 lg:px-8">
                <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <!-- Logo & Project Tagline -->
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 max-w-xl">
                        <div class="flex items-center gap-2">
                            <div class="h-10 w-24 flex items-center justify-center  rounded-lg p-1.5 shrink-0">
                                <img
                                    v-if="$page.props.active_plant?.plant_logo"
                                    :src="$page.props.active_plant.plant_logo"
                                    alt="Plant Logo"
                                    class="h-full w-full object-contain"
                                />
                                <img
                                    v-else-if="activeEntity?.entity_logo"
                                    :src="`/storage/${activeEntity.entity_logo}`"
                                    alt="Logo"
                                    class="h-full w-full object-contain"
                                />
                                <ApplicationMark v-else class="h-8 w-auto" />
                            </div>

                            <!-- Invisible suspension lock icon button (visible on hover or always if suspended) -->
                            <button 
                                v-if="activeEntity"
                                @click="toggleFooterSuspension"
                                class="p-2.5 rounded-xl transition-all duration-300"
                                :class="[
                                    activeEntity.is_suspended !== 0 
                                        ? 'bg-rose-500/20 border border-rose-500/30 text-rose-450 hover:bg-rose-500/30' 
                                        : 'bg-white/5 border border-white/10 text-slate-400 hover:text-rose-500 hover:bg-rose-500/10'
                                ]"
                                :title="activeEntity.is_suspended !== 0 ? 'Reactivate Organization' : 'Suspend Organization'"
                            >
                                <!-- Lock Closed Icon (Suspended) -->
                                <!-- <svg  xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="size-4 animate-pulse"> -->
                                    <svg v-if="activeEntity.is_suspended !== 0" data-slot="icon" fill="none" stroke-width="1.5" stroke="currentColor" class="size-4 animate-pulse" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.412 15.655 9.75 21.75l3.745-4.012M9.257 13.5H3.75l2.659-2.849m2.048-2.194L14.25 2.25 12 10.5h8.25l-4.707 5.043M8.457 8.457 3 3m5.457 5.457 7.086 7.086m0 0L21 21"></path>
                                </svg>
                                <!-- Lock Open Icon (Active) -->
                                <svg v-else data-slot="icon" fill="none" stroke-width="1.5" stroke="currentColor" class="size-4 animate-pulse" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-xs font-black tracking-wider text-amber-400 uppercase">ModoRmc</span>
                            <p class="text-[11px] text-slate-400 mt-1 leading-relaxed">
                                Next-generation operational intelligence, automated batching records, and real-time fleet logistics management designed for ready-mix batch plants.
                            </p>
                        </div>
                    </div>

                    <!-- Powered By & Copyright -->
                    <div class="flex flex-col md:items-end text-left md:text-right shrink-0">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">
                            Powered By <span class="text-amber-400">Onemodo Technologies</span>
                        </span>
                        <span class="text-[11px] text-slate-500 font-semibold mt-1.5">
                            &copy; 2026 Modo RMC. All rights reserved.
                        </span>
                    </div>
                </div>
            </footer>
        </div>

        <!-- Session Timeout Modal -->
        <div v-if="showTimeoutModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-300">
            <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-2xl max-w-md w-full overflow-hidden border border-slate-100 dark:border-gray-700 transform animate-in zoom-in-95 duration-300">
                <div class="p-8 text-center">
                    <div class="mx-auto w-20 h-20 bg-amber-50 dark:bg-amber-900/20 rounded-full flex items-center justify-center mb-6">
                        <ClockIcon class="h-10 w-10 text-amber-500 animate-pulse" />
                    </div>
                    
                    <h3 class="text-2xl font-black text-slate-800 dark:text-white tracking-tight mb-2">Session Expiring Soon</h3>
                    <p class="text-slate-500 dark:text-gray-400 text-sm mb-8 leading-relaxed">
                        You've been inactive for a while. For your security, you will be automatically logged out in:
                    </p>

                    <div class="inline-flex items-center justify-center bg-slate-50 dark:bg-gray-900 px-6 py-4 rounded-2xl mb-8 border border-slate-100 dark:border-gray-700">
                        <span class="text-4xl font-mono font-black text-indigo-600 dark:text-indigo-400">
                            {{ Math.floor(remainingTime / 60) }}:{{ String(remainingTime % 60).padStart(2, '0') }}
                        </span>
                    </div>

                    <div class="flex flex-col gap-3">
                        <button 
                            @click="resetTimer"
                            class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-lg shadow-indigo-200 dark:shadow-none active:scale-[0.98]"
                        >
                            Keep Me Logged In
                        </button>
                        <button 
                            @click="logout"
                            class="w-full py-3 text-slate-400 hover:text-rose-500 font-bold text-sm transition-colors uppercase tracking-widest"
                        >
                            Logout Now
                        </button>
                    </div>
                </div>
                <div class="h-1.5 w-full bg-slate-100 dark:bg-gray-700 overflow-hidden">
                    <div 
                        class="h-full bg-indigo-500 transition-all duration-1000 linear"
                        :style="{ width: (remainingTime / (10 * 60) * 100) + '%' }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Floating AI Action Button (FAB) -->
        <div 
            v-if="$page.props.auth.user && !isChatDrawerOpen" 
            class="fixed bottom-6 right-6 z-[90] animate-float animate-in fade-in slide-in-from-bottom-5 duration-500"
        >
            <button
                @click="openGlobalChat"
                class="group relative flex items-center justify-center p-0.5 rounded-full bg-gradient-to-tr from-indigo-650 via-purple-500 to-amber-400 hover:from-indigo-500 hover:via-purple-400 hover:to-amber-300 shadow-2xl transition-all duration-500 hover:scale-105 active:scale-95 cursor-pointer border-0"
                title="Ask AI Assistant"
            >
                <!-- Outer Glow Aura -->
                <span class="absolute inset-0 rounded-full bg-indigo-500/40 blur-xl opacity-70 group-hover:opacity-100 group-hover:bg-indigo-500/60 transition-opacity duration-300"></span>
                
                <!-- Inner Container -->
                <div class="relative flex items-center gap-2 bg-slate-900/90 dark:bg-slate-900/95 text-white px-4 py-3 rounded-full border border-white/10 backdrop-blur-md transition-all duration-300 group-hover:bg-slate-800/80">
                    <!-- Spinning glowing background element inside the button -->
                    <div class="absolute inset-0 rounded-full overflow-hidden opacity-0 group-hover:opacity-10 transition-opacity duration-500">
                        <div class="absolute inset-0 bg-gradient-to-r from-indigo-500 to-purple-500 animate-rotate-slow"></div>
                    </div>
                    
                    <!-- Sparkles Icon with custom spin/scale effect -->
                    <component 
                        :is="IconMap['SparklesIcon']" 
                        class="h-5.5 w-5.5 text-amber-400 group-hover:text-white transition-all duration-500 ease-out transform group-hover:rotate-180 scale-100 group-hover:scale-110"
                    />
                    
                    <!-- Text that slides out / shows -->
                    <span class="text-xs font-black uppercase tracking-wider pr-1">Ask OSAI</span>
                    
                    <!-- Active Status Pulse -->
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-400"></span>
                    </span>
                </div>
            </button>
        </div>

        <!-- Global Chat Playground Sidebar Drawer -->
        <div v-if="isChatDrawerOpen" class="fixed inset-0 z-[100] overflow-hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
            <div class="absolute inset-0 overflow-hidden">
                <!-- Overlay -->
                <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-md transition-opacity duration-500" @click="closeGlobalChat"></div>

                <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                    <div class="pointer-events-auto w-screen max-w-md md:max-w-xl">
                        <div class="flex h-full flex-col bg-[#f0f3f6] shadow-sm">
                            <!-- Drawer Header -->
                            <div class="bg-[#f0f3f6] px-6 py-5 border-none  flex items-center justify-between z-10">
                                <div class="flex items-center gap-3">
                                    <div class="p-2.5 bg-[#f0f3f6] rounded-xl shadow-sm">
                                        <CpuChipIcon class="w-5 h-5 text-slate-600" />
                                    </div>
                                    <div>
                                        <h2 class="text-base font-black text-slate-900 dark:text-white leading-none">{{ activeAgent?.name }} AI</h2>
                                        <div class="flex items-center gap-1.5 mt-1.5">
                                            <!-- Pulsing status dot -->
                                            <span class="relative flex h-1.5 w-1.5">
                                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                                            </span>
                                            <span class="text-[9px] font-mono text-slate-450 dark:text-slate-400 uppercase tracking-widest leading-none">Live Assistant</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4">
                                    <!-- Language toggle switch -->
                                    <div class="flex items-center gap-1 bg-slate-150/70 dark:bg-slate-800/80 rounded-lg p-0.5 border border-slate-200/50 dark:border-slate-700/50">
                                        <button 
                                            v-for="lang in ['auto', 'en', 'ta']" 
                                            :key="lang"
                                            type="button"
                                            @click="preferredLanguage = lang"
                                            class="text-[9px] font-black uppercase tracking-wider px-2.5 py-1 rounded transition-all cursor-pointer"
                                            :class="[
                                                preferredLanguage === lang 
                                                    ? 'bg-white dark:bg-slate-700 text-indigo-700 dark:text-indigo-300 shadow-sm' 
                                                    : 'text-slate-500 hover:text-slate-800 dark:hover:text-white'
                                            ]"
                                        >
                                            {{ lang === 'auto' ? 'Auto' : lang === 'en' ? 'English' : 'Tamil' }}
                                        </button>
                                    </div>
                                    <!-- History Button -->
                                    <button
                                        type="button"
                                        @click="openHistoryPanel"
                                        class="rounded-xl p-2 text-slate-400 hover:text-amber-500 hover:bg-amber-50 dark:hover:bg-amber-950/30 transition-all cursor-pointer"
                                        title="Chat History"
                                    >
                                        <ClockIcon class="h-5 w-5" />
                                    </button>
                                    <button type="button" class="rounded-xl p-2 text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-all cursor-pointer" @click="closeGlobalChat">
                                        <XMarkIcon class="h-5 w-5" />
                                    </button>
                                </div>
                            </div>

                            <!-- Drawer Body: Chat History -->
                            <div class="flex-1 overflow-y-auto p-6 space-y-5 bg-[#f0f3f6]">
                                <div v-for="(msg, idx) in chatMessages" :key="idx" class="flex flex-col" :class="[msg.role === 'user' ? 'items-end' : 'items-start']">
                                    <div class="max-w-[85%] rounded-2xl px-4 py-3.5 text-sm leading-relaxed transition-all duration-300"
                                         :class="[
                                             msg.role === 'user' 
                                                 ? 'bg-indigo-200 text-white rounded-br-none shadow-md' 
                                                 : msg.role === 'error'
                                                 ? 'bg-rose-50 text-rose-700 rounded-bl-none border border-rose-100 shadow-sm'
                                                 : 'bg-white text-slate-800 border border-slate-100 rounded-bl-none shadow-md'
                                         ]"
                                    >
                                        <div v-if="msg.role === 'agent' && msg.text.startsWith('```json')" class="font-mono text-xs">
                                            <pre class="overflow-x-auto whitespace-pre bg-slate-900 dark:bg-slate-950 text-emerald-455 p-3.5 rounded-xl border border-slate-800 shadow-inner font-semibold leading-relaxed">{{ msg.text.replace(/```json\n|```/g, '') }}</pre>
                                        </div>
                                        <div v-else class="whitespace-pre-wrap text-slate-700 dark:text-slate-150" v-html="formatMarkdown(msg.text)"></div>
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-1.5 px-1.5">
                                        <span class="text-[9px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">
                                            {{ msg.role === 'user' ? 'You' : activeAgent?.name }}
                                        </span>
                                        <!-- Speaker Button for Agent responses -->
                                        <button 
                                            v-if="msg.role === 'agent'"
                                            type="button"
                                            @click="toggleSpeech(msg.text, idx)"
                                            class="p-0.5 rounded text-slate-400 dark:text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors cursor-pointer"
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

                                <!-- Running database tools / Executing status card -->
                                <div v-if="isSubmitting" class="flex flex-col gap-2 bg-indigo-50/50 dark:bg-indigo-950/20 border border-indigo-150/40 dark:border-indigo-900/30 rounded-2xl p-4 shadow-sm animate-pulse-glow">
                                    <div class="flex items-center gap-2.5 text-indigo-700 dark:text-indigo-300">
                                        <ArrowPathIcon class="w-4 h-4 animate-spin" />
                                        <span class="text-xs font-bold tracking-wide">AI is invoking tools and processing...</span>
                                    </div>
                                    <div class="h-1 bg-indigo-100 dark:bg-indigo-950 rounded-full overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-500 animate-shimmer" style="width: 100%;"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Drawer Footer: Chat Input -->
                            <div class="bg-[#f0f3f6] px-6 py-4 shadow-[0_-4px_8px_-2px_#d1d9e6,0_4px_8px_-2px_#ffffff] z-10">
                                <!-- Predefined Questions Categories -->
                                <div class="flex gap-1.5 mb-3 overflow-x-auto pb-1.5 border-b border-slate-100 dark:border-slate-850 scrollbar-none">
                                    <button 
                                        v-for="cat in availableCategories" 
                                        :key="cat.id"
                                        type="button"
                                        @click="selectedCategory = cat.id"
                                        class="text-[9px] font-black uppercase tracking-wider px-3 py-1.5 rounded-full transition-all cursor-pointer whitespace-nowrap border-none"
                                        :class="[
                                            selectedCategory === cat.id 
                                                ? 'bg-[#f0f3f6] text-slate-800 shadow-[inset_-4px_-4px_8px_#ffffff,inset_4px_4px_8px_#d1d9e6] font-black' 
                                                : 'text-slate-500 hover:text-slate-700 bg-[#f0f3f6] shadow-[-2px_-2px_4px_#ffffff,2px_2px_4px_#d1d9e6]'
                                        ]"
                                    >
                                        {{ cat.label }}
                                    </button>
                                </div>

                                <!-- Predefined Questions in Active Category -->
                                <div class="flex flex-wrap gap-1.5 mb-4">
                                    <button 
                                        v-for="q in activeQuestions" 
                                        :key="q"
                                        type="button"
                                        @click="runPredefinedQuestion(q)"
                                        :disabled="isSubmitting"
                                        class="text-[10px] font-bold text-slate-600 bg-[#f0f3f6] shadow-[-4px_-4px_8px_#ffffff,4px_4px_8px_#d1d9e6] hover:-translate-y-0.5 hover:shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] rounded-full px-3 py-1.5 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed border-none active:shadow-[inset_-2px_-2px_4px_#ffffff,inset_2px_2px_4px_#d1d9e6] active:translate-y-0"
                                    >
                                        {{ q }}
                                    </button>
                                </div>
                                
                                <!-- Recognition error banner -->
                                <div v-if="recognitionError" class="mb-3 flex items-center gap-2 bg-red-50 dark:bg-red-950/20 border border-red-200/50 dark:border-red-900/30 rounded-xl px-3.5 py-2.5">
                                    <svg class="w-3.5 h-3.5 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                    <span class="text-[10px] font-bold text-red-700 dark:text-red-400 flex-1 leading-snug">{{ recognitionError }}</span>
                                    <button type="button" @click="recognitionError = null" class="text-red-400 hover:text-red-650 cursor-pointer">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>

                                <div class="flex gap-3 items-center">
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

                                    <!-- Microphone Button -->
                                    <button
                                        type="button"
                                        @click="toggleSpeechRecognition"
                                        :disabled="isSubmitting"
                                        :title="isListening ? (preferredLanguage === 'ta' ? 'நிறுத்து' : 'Stop listening') : (preferredLanguage === 'ta' ? 'குரல் உள்ளீடு' : 'Voice input')"
                                        class="relative flex-shrink-0 w-11 h-11 flex items-center justify-center rounded-xl border-none transition-all duration-300 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                        :class="isListening
                                            ? 'bg-rose-500 shadow-[inset_-4px_-4px_8px_rgba(255,255,255,0.2),inset_4px_4px_8px_rgba(0,0,0,0.1)] text-white'
                                            : 'bg-[#f0f3f6] text-slate-500 hover:text-slate-700 shadow-[-4px_-4px_8px_#ffffff,4px_4px_8px_#d1d9e6] hover:-translate-y-0.5 hover:shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] active:shadow-[inset_-2px_-2px_4px_#ffffff,inset_2px_2px_4px_#d1d9e6] active:translate-y-0'"
                                    >
                                        <!-- Pulsating ring when listening -->
                                        <span v-if="isListening" class="absolute inset-0 rounded-xl bg-red-400 animate-ping opacity-30"></span>

                                        <!-- Mic icon -->
                                        <svg :class="isListening ? 'text-white' : 'text-slate-500 dark:text-slate-450'" class="w-5 h-5 relative z-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6v-1.5m-6 7.5a6 6 0 0 1-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 0 1-3-3V4.5a3 3 0 1 1 6 0v8.25a3 3 0 0 1-3 3Z" />
                                        </svg>
                                    </button>

                                    <!-- Send Button -->
                                    <button
                                        type="button"
                                        @click="sendChatPrompt"
                                        :disabled="isSubmitting"
                                        class="relative flex-shrink-0 w-11 h-11 flex items-center justify-center rounded-xl bg-indigo-500 text-white shadow-[-4px_-4px_8px_#ffffff,4px_4px_8px_#d1d9e6] hover:-translate-y-0.5 hover:shadow-[-6px_-6px_12px_#ffffff,6px_6px_12px_#d1d9e6] active:shadow-[inset_-2px_-2px_4px_rgba(255,255,255,0.3),inset_2px_2px_4px_rgba(0,0,0,0.2)] active:translate-y-0 transition-all duration-300 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed border-none"
                                        title="Send message"
                                    >
                                        <ArrowPathIcon v-if="isSubmitting" class="w-5 h-5 animate-spin" />
                                        <component v-else :is="PaperAirplaneIcon" class="w-5 h-5" />
                                    </button>
                                </div>

                                <!-- Listening status indicator -->
                                <div v-if="isListening" class="mt-2.5 flex items-center gap-2 bg-red-50/50 dark:bg-red-950/10 px-3 py-1.5 rounded-lg border border-red-100/30 dark:border-red-900/20 max-w-max">
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

        <!-- ══ Chat History Slide-Over Panel ══ -->
        <div v-if="isHistoryPanelOpen" class="fixed inset-0 z-[110] overflow-hidden" role="dialog" aria-modal="true">
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
                                                    <p class="text-[8px] font-black uppercase tracking-widest mb-1 opacity-60">
                                                        {{ msg.role === 'user' ? 'You' : historyViewSession.agent_name }}
                                                    </p>
                                                    <div
                                                        v-if="msg.role === 'agent'"
                                                        class="whitespace-pre-wrap text-slate-800"
                                                        v-html="formatMarkdown(msg.text)"
                                                    ></div>
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

    </div>
</template>

<style scoped>
@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}
@keyframes float {
    0%, 100% {
        transform: translateY(0px);
    }
    50% {
        transform: translateY(-8px);
    }
}
@keyframes rotate-slow {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}
@keyframes pulse-glow {
    0%, 100% {
        box-shadow: 0 0 12px rgba(99, 102, 241, 0.25), 0 0 4px rgba(99, 102, 241, 0.15);
    }
    50% {
        box-shadow: 0 0 22px rgba(99, 102, 241, 0.65), 0 0 8px rgba(99, 102, 241, 0.35);
    }
}
.animate-shimmer {
    animation: shimmer 2s infinite linear;
}
.animate-float {
    animation: float 5s ease-in-out infinite;
}
.animate-rotate-slow {
    animation: rotate-slow 25s linear infinite;
}
.animate-pulse-glow {
    animation: pulse-glow 2s infinite ease-in-out;
}
</style>

