import { defineStore } from 'pinia';
import { ref, watch } from 'vue';
import axios from 'axios';

export interface ChatMessage {
    id?: number;
    role: 'user' | 'assistant';
    content: string;
    created_at?: string;
    provider?: string;
}

export const useAIChatStore = defineStore('aiChat', () => {
    const messages = ref<ChatMessage[]>([]);
    const sessionToken = ref<string | null>(localStorage.getItem('ai_chat_session_token'));
    const isTyping = ref<boolean>(false);
    const language = ref<string>('en');
    const isEscalated = ref<boolean>(false);
    const customerName = ref<string | null>(localStorage.getItem('ai_chat_customer_name'));
    const customerEmail = ref<string | null>(localStorage.getItem('ai_chat_customer_email'));

    // Watch for state changes to persist customer info
    watch(customerName, (val) => {
        if (val) localStorage.setItem('ai_chat_customer_name', val);
        else localStorage.removeItem('ai_chat_customer_name');
    });

    watch(customerEmail, (val) => {
        if (val) localStorage.setItem('ai_chat_customer_email', val);
        else localStorage.removeItem('ai_chat_customer_email');
    });

    async function initChat(name?: string, email?: string) {
        if (name) customerName.value = name;
        if (email) customerEmail.value = email;

        if (sessionToken.value) {
            await loadHistory();
        }
    }

    async function sendMessage(text: string) {
        if (!text.trim()) return;

        // Add user message locally
        const userMsg: ChatMessage = {
            role: 'user',
            content: text,
            created_at: new Date().toISOString()
        };
        messages.value.push(userMsg);

        isTyping.value = true;

        try {
            const response = await axios.post('/api/ai/chat', {
                message: text,
                session_token: sessionToken.value,
                language: language.value,
                customer_name: customerName.value,
                customer_email: customerEmail.value
            });

            if (response.data.success) {
                if (!sessionToken.value && response.data.session_token) {
                    sessionToken.value = response.data.session_token;
                    localStorage.setItem('ai_chat_session_token', response.data.session_token);
                }

                messages.value.push({
                    role: 'assistant',
                    content: response.data.reply,
                    provider: response.data.provider,
                    created_at: new Date().toISOString()
                });

                isEscalated.value = response.data.is_escalated;
            } else {
                throw new Error(response.data.error || 'Unknown error');
            }
        } catch (error: any) {
            console.error('Error sending AI message:', error);
            messages.value.push({
                role: 'assistant',
                content: error.response?.data?.error || 'Sorry, I encountered an error. Please try again.',
                created_at: new Date().toISOString()
            });
        } finally {
            isTyping.value = false;
        }
    }

    async function loadHistory() {
        if (!sessionToken.value) return;

        try {
            const response = await axios.get('/api/ai/history', {
                params: { session_token: sessionToken.value }
            });

            if (response.data.success) {
                messages.value = response.data.messages.map((m: any) => ({
                    id: m.id,
                    role: m.role,
                    content: m.content,
                    provider: m.provider,
                    created_at: m.created_at
                }));
                isEscalated.value = response.data.conversation.is_escalated;
                language.value = response.data.conversation.language || 'en';
            }
        } catch (error) {
            console.error('Error loading AI history:', error);
            // Session might be expired or invalid, clear it
            clearChat();
        }
    }

    async function escalateToHuman(reason?: string) {
        if (!sessionToken.value) return;

        try {
            const response = await axios.post('/api/ai/chat/escalate', {
                session_token: sessionToken.value,
                reason
            });

            if (response.data.success) {
                isEscalated.value = true;
                messages.value.push({
                    role: 'assistant',
                    content: 'Your conversation has been escalated to a support agent. They will follow up shortly.',
                    created_at: new Date().toISOString()
                });
            }
        } catch (error) {
            console.error('Error escalating conversation:', error);
        }
    }

    function clearChat() {
        messages.value = [];
        sessionToken.value = null;
        isEscalated.value = false;
        localStorage.removeItem('ai_chat_session_token');
    }

    return {
        messages,
        sessionToken,
        isTyping,
        language,
        isEscalated,
        customerName,
        customerEmail,
        initChat,
        sendMessage,
        loadHistory,
        escalateToHuman,
        clearChat
    };
});
