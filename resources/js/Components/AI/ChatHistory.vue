<template>
  <div class="space-y-6">
    <!-- Filters Header -->
    <div class="flex flex-wrap items-center justify-between gap-4 bg-slate-50 dark:bg-slate-900/40 p-4 rounded-2xl border border-slate-100 dark:border-slate-800">
      <div class="flex flex-wrap items-center gap-3">
        <!-- Channel Select -->
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-black uppercase text-slate-400">Channel</label>
          <select
            v-model="filters.channel"
            class="text-xs bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @change="fetchConversations(1)"
          >
            <option value="">All Channels</option>
            <option value="chatbot">Chatbot</option>
            <option value="voice">Voice AI</option>
            <option value="assistant">Staff Assistant</option>
          </select>
        </div>

        <!-- Status Select -->
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-black uppercase text-slate-400">Status</label>
          <select
            v-model="filters.status"
            class="text-xs bg-white dark:bg-gray-800 border border-slate-200 dark:border-gray-700 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @change="fetchConversations(1)"
          >
            <option value="">All Statuses</option>
            <option value="active">Active</option>
            <option value="closed">Closed</option>
            <option value="escalated">Escalated</option>
          </select>
        </div>

        <!-- Escalated Checkbox -->
        <div class="flex items-center gap-2 mt-4">
          <input
            id="escalatedOnly"
            v-model="filters.escalated"
            type="checkbox"
            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
            @change="fetchConversations(1)"
          />
          <label for="escalatedOnly" class="text-xs font-semibold text-slate-600 dark:text-slate-300 cursor-pointer">
            Escalated Only
          </label>
        </div>
      </div>

      <button
        @click="fetchConversations(1)"
        class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors"
        title="Refresh"
      >
        <ArrowPathIcon :class="['w-5 h-5 text-slate-500', loading ? 'animate-spin' : '']" />
      </button>
    </div>

    <!-- Table of Conversations -->
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-slate-200 dark:border-gray-700 overflow-hidden shadow-sm">
      <div v-if="loading && !conversations.length" class="p-12 text-center">
        <ArrowPathIcon class="w-10 h-10 text-indigo-600 animate-spin mx-auto mb-3" />
        <p class="text-xs text-slate-500">Loading chat logs...</p>
      </div>

      <div v-else-if="!conversations.length" class="p-12 text-center">
        <ChatBubbleLeftRightIcon class="w-12 h-12 text-slate-300 mx-auto mb-3" />
        <p class="text-sm font-semibold text-slate-700 dark:text-slate-300">No Chat History Found</p>
        <p class="text-xs text-slate-400 mt-1">No AI conversations match your active filter settings.</p>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
          <thead>
            <tr class="bg-slate-50 dark:bg-slate-900/40 border-b border-slate-100 dark:border-slate-800">
              <th class="p-4 font-bold uppercase tracking-wider text-slate-400">User / Customer</th>
              <th class="p-4 font-bold uppercase tracking-wider text-slate-400">Channel</th>
              <th class="p-4 font-bold uppercase tracking-wider text-slate-400">Language</th>
              <th class="p-4 font-bold uppercase tracking-wider text-slate-400">Messages</th>
              <th class="p-4 font-bold uppercase tracking-wider text-slate-400">Status</th>
              <th class="p-4 font-bold uppercase tracking-wider text-slate-400">Last Interaction</th>
              <th class="p-4 text-right"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <tr
              v-for="session in conversations"
              :key="session.id"
              class="hover:bg-slate-50/60 dark:hover:bg-slate-800/10 transition-colors cursor-pointer"
              @click="openSession(session)"
            >
              <td class="p-4">
                <div class="font-semibold text-slate-800 dark:text-slate-200">
                  {{ session.customer_name || 'Guest User' }}
                </div>
                <div v-if="session.customer_email" class="text-[10px] text-slate-400 font-mono mt-0.5">
                  {{ session.customer_email }}
                </div>
              </td>
              <td class="p-4">
                <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider', getChannelClass(session.channel)]">
                  {{ session.channel }}
                </span>
              </td>
              <td class="p-4 font-mono text-slate-500 uppercase">{{ session.language }}</td>
              <td class="p-4 font-semibold text-slate-600 dark:text-slate-300">
                {{ session.message_count }}
              </td>
              <td class="p-4">
                <div class="flex items-center gap-1.5">
                  <span
                    :class="[
                      'w-2 h-2 rounded-full',
                      session.is_escalated ? 'bg-red-500 animate-pulse' : (session.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400')
                    ]"
                  ></span>
                  <span class="font-medium text-slate-700 dark:text-slate-300">
                    {{ session.is_escalated ? 'Escalated' : session.status }}
                  </span>
                </div>
              </td>
              <td class="p-4 text-slate-500 font-mono">{{ formatDate(session.updated_at) }}</td>
              <td class="p-4 text-right" @click.stop>
                <button
                  @click="openSession(session)"
                  class="px-3 py-1.5 bg-slate-100 hover:bg-indigo-50 text-slate-600 hover:text-indigo-600 dark:bg-slate-800 dark:text-slate-300 rounded-xl font-bold uppercase tracking-wider transition-colors"
                >
                  View Chat
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="flex items-center justify-between border-t border-slate-100 dark:border-slate-800 p-4">
        <span class="text-xs text-slate-400">
          Showing page {{ pagination.current_page }} of {{ pagination.last_page }}
        </span>
        <div class="flex gap-1">
          <button
            :disabled="pagination.current_page === 1"
            class="px-3 py-1 bg-slate-100 hover:bg-slate-200 disabled:opacity-40 rounded text-xs font-semibold"
            @click="fetchConversations(pagination.current_page - 1)"
          >
            Prev
          </button>
          <button
            :disabled="pagination.current_page === pagination.last_page"
            class="px-3 py-1 bg-slate-100 hover:bg-slate-200 disabled:opacity-40 rounded text-xs font-semibold"
            @click="fetchConversations(pagination.current_page + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Dialogue Detail Side Drawer / Modal -->
    <div
      v-if="selectedSession"
      class="fixed inset-0 z-50 flex justify-end bg-slate-900/60 backdrop-blur-sm transition-opacity"
      @click="selectedSession = null"
    >
      <div
        class="w-[500px] h-full bg-white dark:bg-gray-900 shadow-2xl flex flex-col transform transition-transform"
        @click.stop
      >
        <!-- Drawer Header -->
        <div class="flex items-center justify-between p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/40">
          <div>
            <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-widest">
              Chat Session Detailing
            </h3>
            <p class="text-[10px] text-slate-400 mt-1">
              Token: {{ selectedSession.session_token }}
            </p>
          </div>
          <button @click="selectedSession = null" class="p-1 rounded-lg hover:bg-slate-200/60">
            <XMarkIcon class="w-6 h-6 text-slate-500" />
          </button>
        </div>

        <!-- Chat Session Meta Details -->
        <div class="p-4 bg-slate-50/50 dark:bg-slate-900/20 border-b border-slate-100 dark:border-slate-800 text-xs grid grid-cols-2 gap-3">
          <div>
            <p class="text-[9px] font-black text-slate-400 uppercase">Customer Name</p>
            <p class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
              {{ selectedSession.customer_name || 'Guest User' }}
            </p>
          </div>
          <div>
            <p class="text-[9px] font-black text-slate-400 uppercase">Customer Email</p>
            <p class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5">
              {{ selectedSession.customer_email || 'N/A' }}
            </p>
          </div>
          <div>
            <p class="text-[9px] font-black text-slate-400 uppercase">Escalation Status</p>
            <p class="font-semibold mt-0.5" :class="selectedSession.is_escalated ? 'text-red-500' : 'text-slate-600'">
              {{ selectedSession.is_escalated ? '🚨 Escalated to Support' : 'None' }}
            </p>
          </div>
          <div>
            <p class="text-[9px] font-black text-slate-400 uppercase">Channel / Language</p>
            <p class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5 uppercase">
              {{ selectedSession.channel }} ({{ selectedSession.language }})
            </p>
          </div>
        </div>

        <!-- Message Flow -->
        <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50/30">
          <div v-if="loadingMessages" class="p-8 text-center">
            <ArrowPathIcon class="w-8 h-8 text-indigo-600 animate-spin mx-auto mb-2" />
            <p class="text-xs text-slate-500">Loading dialogue messages...</p>
          </div>

          <div
            v-else
            v-for="msg in messages"
            :key="msg.id"
            class="flex gap-3"
            :class="msg.role === 'user' ? 'flex-row-reverse' : ''"
          >
            <!-- Avatar -->
            <div
              class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 text-white shadow-sm font-black text-[10px]"
              :class="msg.role === 'user' ? 'bg-indigo-600' : 'bg-gradient-to-br from-indigo-500 to-violet-500'"
            >
              {{ msg.role === 'user' ? 'U' : 'AI' }}
            </div>

            <!-- Bubble -->
            <div class="flex flex-col max-w-[320px]">
              <div
                :class="[
                  'rounded-2xl px-4 py-2.5 shadow-sm text-xs leading-relaxed whitespace-pre-wrap',
                  msg.role === 'user'
                    ? 'bg-indigo-600 text-white rounded-tr-sm'
                    : 'bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 rounded-tl-sm border border-slate-100 dark:border-slate-700/50'
                ]"
              >
                {{ msg.content }}
              </div>
              <div class="mt-1 flex items-center gap-2 text-[9px] text-slate-400" :class="msg.role === 'user' ? 'justify-end' : ''">
                <span>{{ formatDate(msg.created_at) }}</span>
                <span v-if="msg.provider" class="font-mono text-slate-300">({{ msg.provider }})</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';
import {
  ArrowPathIcon,
  ChatBubbleLeftRightIcon,
  XMarkIcon
} from '@heroicons/vue/24/outline';

const conversations = ref<any[]>([]);
const selectedSession = ref<any | null>(null);
const messages = ref<any[]>([]);
const loading = ref<boolean>(false);
const loadingMessages = ref<boolean>(false);

const filters = ref({
  channel: '',
  status: '',
  escalated: false
});

const pagination = ref({
  current_page: 1,
  last_page: 1
});

const fetchConversations = async (page = 1) => {
  loading.value = true;
  try {
    const response = await axios.get('/api/ai/admin/conversations', {
      params: {
        page,
        channel: filters.value.channel || undefined,
        status: filters.value.status || undefined,
        escalated: filters.value.escalated ? '1' : undefined
      }
    });

    conversations.value = response.data.data || [];
    pagination.value = {
      current_page: response.data.current_page || 1,
      last_page: response.data.last_page || 1
    };
  } catch (err) {
    console.error('Failed to load conversations:', err);
  } finally {
    loading.value = false;
  }
};

const openSession = async (session: any) => {
  selectedSession.value = session;
  loadingMessages.value = true;
  messages.value = [];

  try {
    const response = await axios.get('/api/ai/history', {
      params: { session_token: session.session_token }
    });

    if (response.data.success) {
      messages.value = response.data.messages || [];
    }
  } catch (err) {
    console.error('Failed to load dialogue detail:', err);
  } finally {
    loadingMessages.value = false;
  }
};

const getChannelClass = (channel: string) => {
  switch (channel) {
    case 'chatbot': return 'bg-blue-50 text-blue-600 border border-blue-100';
    case 'voice': return 'bg-amber-50 text-amber-600 border border-amber-100';
    case 'assistant': return 'bg-purple-50 text-purple-600 border border-purple-100';
    default: return 'bg-slate-50 text-slate-600';
  }
};

const formatDate = (val: string) => {
  if (!val) return 'N/A';
  return new Date(val).toLocaleString('en-IN', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit'
  });
};

onMounted(() => {
  fetchConversations();
});
</script>
