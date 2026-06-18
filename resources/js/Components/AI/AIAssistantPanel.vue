<template>
  <!-- Slide-over panel -->
  <Teleport to="body">
    <Transition name="panel-slide">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 flex"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-900/30 backdrop-blur-sm"></div>

        <!-- Panel -->
        <div class="relative ml-auto w-full max-w-[480px] h-full bg-white flex flex-col shadow-2xl">

          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-gradient-to-r from-indigo-50 to-violet-50">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-200">
                <SparklesIcon class="w-5 h-5 text-white" />
              </div>
              <div>
                <h2 class="text-sm font-black text-slate-800 tracking-tight">ModoAI Assistant</h2>
                <p class="text-[11px] text-slate-500">Internal Staff Assistant</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <!-- Clear chat -->
              <button
                @click="clearChat"
                class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
                title="New conversation"
              >
                <PlusCircleIcon class="w-4.5 h-4.5" />
              </button>
              <!-- History -->
              <button
                @click="showHistory = !showHistory"
                class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 transition-colors"
                title="Chat history"
              >
                <ClockIcon class="w-4.5 h-4.5" />
              </button>
              <button @click="close" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 transition-colors">
                <XMarkIcon class="w-4 h-4" />
              </button>
            </div>
          </div>

          <!-- Main Content: Chat or History -->
          <div class="flex-1 overflow-hidden flex flex-col">

            <!-- Chat History Sidebar -->
            <Transition name="fade">
              <div v-if="showHistory" class="absolute inset-0 bg-white z-10 flex flex-col">
                <div class="flex items-center justify-between px-5 py-3 border-b border-slate-100">
                  <h3 class="text-xs font-black uppercase tracking-widest text-slate-700">Chat History</h3>
                  <button @click="showHistory = false" class="text-slate-400 hover:text-slate-600">
                    <XMarkIcon class="w-4 h-4" />
                  </button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-2">
                  <div v-if="chatHistory.length === 0" class="text-center py-8 text-slate-400">
                    <ClockIcon class="w-8 h-8 mx-auto mb-2 opacity-40" />
                    <p class="text-xs">No previous conversations</p>
                  </div>
                  <button
                    v-for="session in chatHistory"
                    :key="session.id"
                    @click="loadSession(session)"
                    class="w-full text-left p-3 rounded-xl hover:bg-indigo-50 border border-slate-100 hover:border-indigo-200 transition-all group"
                  >
                    <p class="text-[11px] font-bold text-slate-700 group-hover:text-indigo-700 line-clamp-2">
                      {{ session.summary || 'Untitled conversation' }}
                    </p>
                    <div class="flex items-center gap-2 mt-1">
                      <span class="text-[10px] text-slate-400">{{ formatDate(session.created_at) }}</span>
                      <span class="text-[10px] text-slate-300">·</span>
                      <span class="text-[10px] text-slate-400">{{ session.message_count }} messages</span>
                    </div>
                  </button>
                </div>
              </div>
            </Transition>

            <!-- Messages -->
            <div ref="messagesContainer" class="flex-1 overflow-y-auto p-5 space-y-4">
              <!-- Welcome / empty state -->
              <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-full text-center py-8">
                <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-100 to-violet-100 flex items-center justify-center mb-4">
                  <SparklesIcon class="w-8 h-8 text-indigo-600" />
                </div>
                <h3 class="text-sm font-black text-slate-800 mb-1">What can I help with?</h3>
                <p class="text-xs text-slate-500 max-w-[260px] leading-relaxed">
                  I can search customers, orders, invoices, generate reports, and answer questions about your business.
                </p>

                <!-- Suggested prompts -->
                <div class="mt-5 grid grid-cols-2 gap-2 w-full max-w-[320px]">
                  <button
                    v-for="suggestion in suggestions"
                    :key="suggestion.text"
                    @click="sendSuggestion(suggestion.text)"
                    class="p-3 rounded-xl border border-slate-100 hover:border-indigo-200 hover:bg-indigo-50 transition-all text-left group"
                  >
                    <span class="text-lg block mb-1">{{ suggestion.emoji }}</span>
                    <p class="text-[11px] font-semibold text-slate-600 group-hover:text-indigo-700 leading-tight">{{ suggestion.text }}</p>
                  </button>
                </div>
              </div>

              <!-- Message bubbles -->
              <div v-for="msg in messages" :key="msg.id">
                <div class="flex gap-3" :class="msg.role === 'user' ? 'flex-row-reverse' : ''">
                  <!-- Avatar -->
                  <div
                    class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5"
                    :class="msg.role === 'user' ? 'bg-indigo-600' : 'bg-gradient-to-br from-indigo-500 to-violet-600'"
                  >
                    <UserIcon v-if="msg.role === 'user'" class="w-3.5 h-3.5 text-white" />
                    <SparklesIcon v-else class="w-3.5 h-3.5 text-white" />
                  </div>

                  <div class="max-w-[85%]" :class="msg.role === 'user' ? 'items-end flex flex-col' : ''">
                    <div
                      class="rounded-2xl px-4 py-3 text-xs leading-relaxed"
                      :class="msg.role === 'user'
                        ? 'bg-indigo-600 text-white rounded-tr-sm'
                        : 'bg-slate-100 text-slate-700 rounded-tl-sm'"
                    >
                      <div v-html="formatMarkdown(msg.content)"></div>
                    </div>

                    <!-- Provider badge for assistant -->
                    <div v-if="msg.role === 'assistant'" class="flex items-center gap-1 mt-1 px-1">
                      <span class="text-[9px] text-slate-400 uppercase tracking-wider">via {{ msg.provider }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Typing indicator -->
              <div v-if="isTyping" class="flex gap-3">
                <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center">
                  <SparklesIcon class="w-3.5 h-3.5 text-white" />
                </div>
                <div class="bg-slate-100 rounded-2xl rounded-tl-sm px-4 py-3">
                  <div class="flex gap-1">
                    <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                    <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                    <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Input Area -->
            <div class="border-t border-slate-100 p-4 bg-white">
              <div class="flex items-end gap-2">
                <textarea
                  v-model="inputMessage"
                  @keydown.enter.exact.prevent="sendMessage"
                  @keydown.shift.enter="() => {}"
                  placeholder="Ask anything... (Enter to send, Shift+Enter for newline)"
                  rows="2"
                  class="flex-1 resize-none rounded-xl border border-slate-200 px-3.5 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-transparent transition-all"
                  :disabled="isTyping"
                ></textarea>
                <button
                  @click="sendMessage"
                  :disabled="!inputMessage.trim() || isTyping"
                  class="w-10 h-10 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white flex items-center justify-center transition-all shadow-md shadow-indigo-200"
                >
                  <PaperAirplaneIcon class="w-4.5 h-4.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Trigger Slot -->
  <slot :open="open" />
</template>

<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue';
import axios from 'axios';
import {
  SparklesIcon, XMarkIcon, PaperAirplaneIcon, UserIcon,
  ClockIcon, PlusCircleIcon,
} from '@heroicons/vue/24/outline';

// ── State ──────────────────────────────────────────────────────────────────
const isOpen            = ref(false);
const isTyping          = ref(false);
const showHistory       = ref(false);
const inputMessage      = ref('');
const messages          = ref<any[]>([]);
const chatHistory       = ref<any[]>([]);
const currentHistoryId  = ref<number | null>(null);
const messagesContainer = ref<HTMLElement | null>(null);

const suggestions = [
  { emoji: '👥', text: 'Show top customers this month' },
  { emoji: '📦', text: 'Recent batch production summary' },
  { emoji: '💰', text: 'Outstanding invoice amounts' },
  { emoji: '📊', text: 'Generate a 30-day report' },
];

// ── Methods ────────────────────────────────────────────────────────────────

const open  = () => { isOpen.value = true; loadHistory(); };
const close = () => { isOpen.value = false; saveSession(); };

const sendMessage = async () => {
  const text = inputMessage.value.trim();
  if (!text || isTyping.value) return;

  messages.value.push({ id: Date.now(), role: 'user', content: text });
  inputMessage.value = '';
  isTyping.value     = true;
  scrollToBottom();

  try {
    const { data } = await axios.post('/api/ai/assistant', {
      message:    text,
      history_id: currentHistoryId.value,
      messages:   messages.value.map(m => ({ role: m.role, content: m.content })),
    });

    if (data.success) {
      messages.value.push({
        id:       Date.now() + 1,
        role:     'assistant',
        content:  data.reply,
        provider: data.provider,
      });
    }
  } catch (err: any) {
    messages.value.push({
      id:      Date.now() + 1,
      role:    'assistant',
      content: '⚠️ ' + (err?.response?.data?.error || 'An error occurred. Please try again.'),
    });
  } finally {
    isTyping.value = false;
    scrollToBottom();
  }
};

const sendSuggestion = (text: string) => {
  inputMessage.value = text;
  sendMessage();
};

const clearChat = () => {
  saveSession();
  messages.value        = [];
  currentHistoryId.value = null;
};

const loadHistory = async () => {
  try {
    const { data } = await axios.get('/api/ai/assistant/history');
    chatHistory.value = data.data || [];
  } catch {}
};

const loadSession = (session: any) => {
  const msgs = session.messages || [];
  messages.value = msgs.map((m: any, i: number) => ({
    id:      i,
    role:    m.role,
    content: m.content || m.text,
  }));
  currentHistoryId.value = session.id;
  showHistory.value      = false;
  scrollToBottom();
};

const saveSession = async () => {
  if (messages.value.length < 2) return;

  try {
    const { data } = await axios.post('/api/ai/assistant/save-history', {
      messages:   messages.value.map(m => ({ role: m.role, content: m.content })),
      history_id: currentHistoryId.value,
    });
    if (data.success) currentHistoryId.value = data.id;
  } catch {}
};

const formatMarkdown = (text: string): string => {
  return text
    .replace(/^### (.*)/gm, '<h3 class="text-[11px] font-black text-slate-800 mt-2 mb-1">$1</h3>')
    .replace(/^## (.*)/gm,  '<h2 class="text-xs font-black text-slate-800 mt-2 mb-1">$1</h2>')
    .replace(/^# (.*)/gm,   '<h1 class="text-xs font-black text-slate-900 mt-2 mb-1">$1</h1>')
    .replace(/\*\*(.*?)\*\*/g, '<strong class="font-bold">$1</strong>')
    .replace(/\*(.*?)\*/g,    '<em>$1</em>')
    .replace(/`(.*?)`/g,      '<code class="bg-slate-200 px-1 rounded text-[10px]">$1</code>')
    .replace(/^• (.*)/gm,    '<li class="ml-3">• $1</li>')
    .replace(/^- (.*)/gm,    '<li class="ml-3">• $1</li>')
    .replace(/\n/g,           '<br>');
};

const formatDate = (dateStr: string): string => {
  return new Date(dateStr).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
};

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
  });
};

// Expose open method for parent trigger
defineExpose({ open, close });
</script>

<style scoped>
.panel-slide-enter-active,
.panel-slide-leave-active {
  transition: all 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}
.panel-slide-enter-from .relative,
.panel-slide-leave-to .relative {
  transform: translateX(100%);
}
.panel-slide-enter-from,
.panel-slide-leave-to {
  opacity: 0;
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }
</style>
