<template>
  <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
    <!-- Chat Window -->
    <Transition name="chat-slide">
      <div
        v-if="isOpen"
        class="w-[380px] max-h-[600px] flex flex-col rounded-2xl shadow-2xl overflow-hidden border border-slate-200/60 bg-white"
        style="backdrop-filter: blur(20px);"
      >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-indigo-600 to-violet-600">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
              <SparklesIcon class="w-4 h-4 text-white" />
            </div>
            <div>
              <p class="text-xs font-black text-white uppercase tracking-widest">ModoAI Support</p>
              <div class="flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <p class="text-[10px] text-white/80">Online — answers in seconds</p>
              </div>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <!-- Language Selector -->
            <select
              v-model="selectedLanguage"
              class="text-[10px] bg-white/10 border border-white/20 text-white rounded-lg px-2 py-1 cursor-pointer"
            >
              <option v-for="lang in languages" :key="lang.code" :value="lang.code">{{ lang.label }}</option>
            </select>
            <button @click="isOpen = false" class="text-white/60 hover:text-white transition-colors">
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
        </div>

        <!-- Messages -->
        <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-50/50" style="min-height: 300px; max-height: 400px;">
          <!-- Welcome message -->
          <div v-if="messages.length === 0" class="flex gap-3">
            <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 flex items-center justify-center flex-shrink-0 mt-0.5">
              <SparklesIcon class="w-3.5 h-3.5 text-white" />
            </div>
            <div class="bg-white rounded-2xl rounded-tl-sm px-4 py-2.5 shadow-sm max-w-[280px]">
              <p class="text-xs text-slate-700 leading-relaxed">
                👋 Hello! I'm ModoAI, your virtual assistant. How can I help you today?
              </p>
              <!-- Quick replies -->
              <div class="mt-2 flex flex-wrap gap-1.5">
                <button
                  v-for="quick in quickReplies"
                  :key="quick"
                  @click="sendQuickReply(quick)"
                  class="text-[10px] bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-100 rounded-lg px-2 py-1 transition-colors font-semibold"
                >
                  {{ quick }}
                </button>
              </div>
            </div>
          </div>

          <!-- Message bubbles -->
          <div v-for="msg in messages" :key="msg.id" class="flex flex-col gap-1.5">
            <div class="flex gap-2" :class="msg.role === 'user' ? 'flex-row-reverse' : ''">
              <!-- Avatar -->
              <div
                class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
                :class="msg.role === 'user' ? 'bg-indigo-600' : 'bg-gradient-to-br from-indigo-500 to-violet-500'"
              >
                <UserIcon v-if="msg.role === 'user'" class="w-3 h-3 text-white" />
                <SparklesIcon v-else class="w-3 h-3 text-white" />
              </div>

              <div :class="['max-w-[260px] rounded-2xl px-3.5 py-2.5 shadow-sm', msg.role === 'user' ? 'bg-indigo-600 text-white rounded-tr-sm' : 'bg-white text-slate-700 rounded-tl-sm']">
                <!-- Text content -->
                <p class="text-xs leading-relaxed whitespace-pre-wrap" v-html="formatMessage(parseMessageMetadata(msg.content).content)"></p>

                <!-- Inline SVG Chart -->
                <div v-if="msg.role === 'assistant' && parseMessageMetadata(msg.content).chart" class="mt-2.5 bg-slate-50 border border-slate-200/60 rounded-xl p-2.5 shadow-sm text-slate-800">
                  <p class="text-[10px] font-extrabold text-slate-600 mb-1.5 uppercase tracking-wider">{{ parseMessageMetadata(msg.content).chart.title }}</p>
                  
                  <!-- Bar Chart -->
                  <div v-if="parseMessageMetadata(msg.content).chart.type === 'bar'" class="h-28 flex items-end justify-between gap-1 pt-4 pb-1 px-1 border-b border-slate-200">
                    <div v-for="(val, idx) in parseMessageMetadata(msg.content).chart.data" :key="idx" class="flex-1 flex flex-col items-center group relative">
                      <!-- Tooltip -->
                      <span class="absolute -top-6 bg-slate-800 text-white text-[8px] px-1 py-0.5 rounded opacity-0 group-hover:opacity-100 transition-opacity font-semibold z-10 whitespace-nowrap">
                        {{ val }}
                      </span>
                      <!-- Bar -->
                      <div 
                        class="w-full rounded-t-sm bg-gradient-to-t from-indigo-500 to-violet-500 hover:from-indigo-600 hover:to-violet-600 transition-all duration-300"
                        :style="{ height: getBarHeight(val, parseMessageMetadata(msg.content).chart.data) + 'px' }"
                      ></div>
                      <!-- Label -->
                      <span class="text-[7px] text-slate-400 mt-1 truncate max-w-[32px] text-center">{{ parseMessageMetadata(msg.content).chart.labels[idx] }}</span>
                    </div>
                  </div>

                  <!-- Pie Chart -->
                  <div v-else-if="parseMessageMetadata(msg.content).chart.type === 'pie'" class="flex items-center gap-3 py-1">
                    <!-- Pie SVG -->
                    <svg class="w-16 h-16 -rotate-90 flex-shrink-0" viewBox="0 0 32 32">
                      <circle 
                        v-for="(slice, idx) in getPieSlices(parseMessageMetadata(msg.content).chart.data)" 
                        :key="idx"
                        r="16" cx="16" cy="16" 
                        fill="transparent" 
                        :stroke="getPieColor(idx)" 
                        stroke-width="32" 
                        :stroke-dasharray="slice.dashArray" 
                        :stroke-dashoffset="slice.dashOffset"
                        class="hover:opacity-85 transition-opacity cursor-pointer"
                      >
                        <title>{{ parseMessageMetadata(msg.content).chart.labels[idx] }}: {{ parseMessageMetadata(msg.content).chart.data[idx] }}</title>
                      </circle>
                    </svg>
                    <!-- Legend -->
                    <div class="flex-1 flex flex-col gap-0.5 min-w-0">
                      <div v-for="(val, idx) in parseMessageMetadata(msg.content).chart.data" :key="idx" class="flex items-center gap-1 text-[8px] text-slate-500 min-w-0">
                        <span class="w-2 h-2 rounded-sm flex-shrink-0" :style="{ backgroundColor: getPieColor(idx) }"></span>
                        <span class="font-medium truncate">{{ parseMessageMetadata(msg.content).chart.labels[idx] }}</span>
                        <span class="font-bold ml-auto text-slate-700 flex-shrink-0">{{ val }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Line Chart -->
                  <div v-else-if="parseMessageMetadata(msg.content).chart.type === 'line'" class="h-28 pt-4 relative">
                    <svg class="w-full h-20" viewBox="0 0 100 40" preserveAspectRatio="none">
                      <!-- Gradient Fill -->
                      <defs>
                        <linearGradient :id="'lineGrad-' + msg.id" x1="0" y1="0" x2="0" y2="1">
                          <stop offset="0%" stop-color="#6366f1" stop-opacity="0.35" />
                          <stop offset="100%" stop-color="#6366f1" stop-opacity="0.0" />
                        </linearGradient>
                      </defs>
                      <path 
                        :d="getLineAreaPath(parseMessageMetadata(msg.content).chart.data)" 
                        :fill="'url(#lineGrad-' + msg.id + ')'" 
                      />
                      <path 
                        :d="getLinePath(parseMessageMetadata(msg.content).chart.data)" 
                        fill="none" 
                        stroke="#6366f1" 
                        stroke-width="1.5" 
                      />
                      <circle 
                        v-for="(p, idx) in getLinePoints(parseMessageMetadata(msg.content).chart.data)" 
                        :key="idx"
                        :cx="p.x" :cy="p.y" r="1.2" 
                        fill="#ffffff" 
                        stroke="#6366f1" 
                        stroke-width="0.8" 
                      >
                        <title>{{ parseMessageMetadata(msg.content).chart.labels[idx] }}: {{ parseMessageMetadata(msg.content).chart.data[idx] }}</title>
                      </circle>
                    </svg>
                    <!-- X-Labels -->
                    <div class="flex justify-between px-1 mt-1 text-[7px] text-slate-400">
                      <span v-for="(lbl, idx) in parseMessageMetadata(msg.content).chart.labels" :key="idx" class="truncate max-w-[32px]">{{ lbl }}</span>
                    </div>
                  </div>
                </div>

                <!-- TTS button for assistant messages -->
                <div v-if="msg.role === 'assistant'" class="mt-1.5 flex items-center justify-between">
                  <span class="text-[9px] text-slate-400">{{ msg.provider }}</span>
                  <button
                    @click="speakMessage(msg)"
                    class="p-1 rounded-lg hover:bg-slate-100 transition-colors flex items-center justify-center"
                    :title="currentlySpeakingMessageId === msg.id ? 'Stop listening' : 'Listen to this message'"
                  >
                    <PauseIcon v-if="currentlySpeakingMessageId === msg.id" class="w-3 h-3 text-indigo-600 animate-pulse" />
                    <SpeakerWaveIcon v-else class="w-3 h-3 text-slate-400 hover:text-indigo-600" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Follow-up Suggestions (Chips) -->
            <div
              v-if="msg.role === 'assistant' && parseMessageMetadata(msg.content).suggestions.length > 0"
              class="flex flex-wrap gap-1.5 ml-8 mt-1 max-w-[260px]"
            >
              <button
                v-for="suggestion in parseMessageMetadata(msg.content).suggestions"
                :key="suggestion"
                @click="sendQuickReply(suggestion)"
                class="text-[10px] bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-100 rounded-lg px-2 py-1 transition-colors font-semibold text-left"
              >
                {{ suggestion }}
              </button>
            </div>
          </div>

          <!-- Typing indicator -->
          <div v-if="isTyping" class="flex gap-2">
            <div class="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-500 to-violet-500 flex items-center justify-center flex-shrink-0">
              <SparklesIcon class="w-3 h-3 text-white" />
            </div>
            <div class="bg-white rounded-2xl rounded-tl-sm px-4 py-3 shadow-sm">
              <div class="flex gap-1">
                <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Input Area -->
        <div class="border-t border-slate-100 bg-white p-3">
          <!-- Voice Recorder (if Sarvam available) -->
          <div v-if="voiceEnabled" class="mb-2">
            <VoiceRecorder @transcript="onVoiceTranscript" :language="selectedLanguage" compact />
          </div>

          <div class="flex items-end gap-2">
            <textarea
              v-model="inputMessage"
              @keydown.enter.exact.prevent="sendMessage"
              placeholder="Type your message..."
              rows="1"
              class="flex-1 resize-none rounded-xl border border-slate-200 px-3 py-2 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-transparent transition-all"
              :class="{ 'opacity-50': isTyping }"
              :disabled="isTyping"
              style="max-height: 100px;"
            ></textarea>
            <button
              @click="sendMessage"
              :disabled="!inputMessage.trim() || isTyping"
              class="w-9 h-9 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white flex items-center justify-center transition-all shadow-md shadow-indigo-200"
            >
              <PaperAirplaneIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Escalation + footer -->
          <div class="mt-2 flex items-center justify-between">
            <button
              @click="requestEscalation"
              class="text-[9px] text-slate-400 hover:text-indigo-600 transition-colors flex items-center gap-1"
            >
              <PhoneIcon class="w-3 h-3" />
              Speak to a human
            </button>
            <p class="text-[9px] text-slate-300">Powered by ModoAI</p>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Floating Trigger Button -->
    <button
      @click="isOpen = !isOpen"
      class="w-14 h-14 rounded-full bg-gradient-to-br from-indigo-600 to-violet-600 shadow-xl shadow-indigo-300/50 hover:shadow-indigo-400/60 flex items-center justify-center transition-all hover:scale-105 active:scale-95"
    >
      <Transition name="icon-switch" mode="out-in">
        <XMarkIcon v-if="isOpen" key="close" class="w-6 h-6 text-white" />
        <ChatBubbleLeftRightIcon v-else key="open" class="w-6 h-6 text-white" />
      </Transition>

      <!-- Unread badge -->
      <span
        v-if="unreadCount > 0 && !isOpen"
        class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full text-[10px] font-black text-white flex items-center justify-center"
      >{{ unreadCount }}</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, nextTick, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import {
  SparklesIcon, XMarkIcon, PaperAirplaneIcon, SpeakerWaveIcon,
  UserIcon, ChatBubbleLeftRightIcon, PhoneIcon, PauseIcon,
} from '@heroicons/vue/24/outline';
import VoiceRecorder from './VoiceRecorder.vue';
import AudioPlayer from './AudioPlayer.vue';

// ── Props ──────────────────────────────────────────────────────────────────
const props = withDefaults(defineProps<{
  voiceEnabled?: boolean;
  entityId?: number | null;
  plantId?: number | null;
}>(), {
  voiceEnabled: false,
  entityId: null,
  plantId: null,
});

// ── State ──────────────────────────────────────────────────────────────────
const isOpen          = ref(false);
const isTyping        = ref(false);
const inputMessage    = ref('');
const messages        = ref<any[]>([]);
const sessionToken    = ref<string | null>(null);
const messagesContainer = ref<HTMLElement | null>(null);
const unreadCount     = ref(0);
const selectedLanguage = ref('ta-IN');
const currentlySpeakingMessageId = ref<number | null>(null);
const currentPlayingAudio        = ref<HTMLAudioElement | null>(null);

const languages = [
  { code: 'en',    label: '🇬🇧 EN' },
  { code: 'hi-IN', label: '🇮🇳 HI' },
  { code: 'ta-IN', label: 'TA' },
  { code: 'te-IN', label: 'TE' },
  { code: 'ml-IN', label: 'ML' },
  { code: 'kn-IN', label: 'KN' },
];

const quickReplies = ref<string[]>([
  'Track my order',
  'Invoice query',
  'Contact support',
  'Product info',
]);

// ── Methods ────────────────────────────────────────────────────────────────

const sendMessage = async () => {
  const text = inputMessage.value.trim();
  if (!text || isTyping.value) return;

  // Optimistically add user message
  messages.value.push({
    id:      Date.now(),
    role:    'user',
    content: text,
  });
  inputMessage.value = '';
  isTyping.value     = true;
  scrollToBottom();

  try {
    const { data } = await axios.post('/api/ai/chat', {
      message:       text,
      session_token: sessionToken.value,
      language:      selectedLanguage.value,
      entity_id:     props.entityId,
      plant_id:      props.plantId,
    });

    if (data.success) {
      sessionToken.value = data.session_token;

      messages.value.push({
        id:       data.message_id,
        role:     'assistant',
        content:  data.reply,
        provider: data.provider,
      });

      if (!isOpen.value) {
        unreadCount.value++;
      }
    }
  } catch (err) {
    messages.value.push({
      id:      Date.now(),
      role:    'assistant',
      content: '⚠️ I encountered an error. Please try again.',
    });
  } finally {
    isTyping.value = false;
    scrollToBottom();
  }
};

const sendQuickReply = (text: string) => {
  inputMessage.value = text;
  sendMessage();
};

const onVoiceTranscript = (transcript: string) => {
  inputMessage.value = transcript;
  sendMessage();
};

const speakMessage = async (msg: any) => {
  // Toggle off if currently speaking this message
  if (currentlySpeakingMessageId.value === msg.id) {
    if (currentPlayingAudio.value) {
      currentPlayingAudio.value.pause();
      currentPlayingAudio.value = null;
    }
    if (typeof window !== 'undefined' && window.speechSynthesis) {
      window.speechSynthesis.cancel();
    }
    currentlySpeakingMessageId.value = null;
    return;
  }

  // Stop any other active playback
  if (currentPlayingAudio.value) {
    currentPlayingAudio.value.pause();
    currentPlayingAudio.value = null;
  }
  if (typeof window !== 'undefined' && window.speechSynthesis) {
    window.speechSynthesis.cancel();
  }
  currentlySpeakingMessageId.value = null;

  const text = parseMessageMetadata(msg.content).content.replace(/[*#_`]/g, '').trim();
  const lang = selectedLanguage.value === 'en' ? 'en-IN' : (selectedLanguage.value || 'ta-IN');

  const browserSpeak = (txt: string, l: string) => {
    if (typeof window === 'undefined' || !window.speechSynthesis) {
      currentlySpeakingMessageId.value = null;
      return;
    }
    const utter = new SpeechSynthesisUtterance(txt);
    utter.lang = l;
    const loadVoice = () => {
      const voices = window.speechSynthesis.getVoices();
      const match = voices.find((v: SpeechSynthesisVoice) => v.lang === l || v.lang.startsWith(l.split('-')[0]));
      if (match) utter.voice = match;
    };
    loadVoice();
    if (window.speechSynthesis.getVoices().length === 0) {
      window.speechSynthesis.onvoiceschanged = loadVoice;
    }
    utter.rate = 0.95;

    utter.onend = () => {
      if (currentlySpeakingMessageId.value === msg.id) {
        currentlySpeakingMessageId.value = null;
      }
    };
    utter.onerror = () => {
      if (currentlySpeakingMessageId.value === msg.id) {
        currentlySpeakingMessageId.value = null;
      }
    };

    window.speechSynthesis.speak(utter);
  };

  currentlySpeakingMessageId.value = msg.id;

  try {
    const { data } = await axios.post('/api/ai/text-to-speech', { text, language: lang });

    if (data.success && data.audio_base64 && currentlySpeakingMessageId.value === msg.id) {
      const audio = new Audio(`data:${data.content_type};base64,${data.audio_base64}`);
      currentPlayingAudio.value = audio;
      audio.play();

      audio.onended = () => {
        if (currentlySpeakingMessageId.value === msg.id) {
          currentlySpeakingMessageId.value = null;
          currentPlayingAudio.value = null;
        }
      };
      audio.onerror = () => {
        if (currentlySpeakingMessageId.value === msg.id) {
          currentlySpeakingMessageId.value = null;
          currentPlayingAudio.value = null;
        }
      };
    } else if (data.fallback_to_browser && currentlySpeakingMessageId.value === msg.id) {
      browserSpeak(text, lang);
    } else {
      currentlySpeakingMessageId.value = null;
    }
  } catch (err: any) {
    const shouldFallback = err?.response?.data?.fallback_to_browser === true
      || err?.response?.status === 503
      || err?.response?.status === 422;
    if (shouldFallback && currentlySpeakingMessageId.value === msg.id) {
      browserSpeak(text, lang);
    } else {
      console.error('TTS failed', err);
      currentlySpeakingMessageId.value = null;
    }
  }
};

onUnmounted(() => {
  if (currentPlayingAudio.value) {
    currentPlayingAudio.value.pause();
    currentPlayingAudio.value = null;
  }
  if (typeof window !== 'undefined' && window.speechSynthesis) {
    window.speechSynthesis.cancel();
  }
});

const requestEscalation = async () => {
  if (!sessionToken.value) {
    messages.value.push({
      id:      Date.now(),
      role:    'assistant',
      content: 'Please start a conversation first, then I can connect you to a human agent.',
    });
    return;
  }

  try {
    await axios.post('/api/ai/chat/escalate', {
      session_token: sessionToken.value,
      reason:        'Customer requested human agent',
    });

    messages.value.push({
      id:      Date.now(),
      role:    'assistant',
      content: '✅ I\'ve notified our support team. A human agent will reach out to you shortly. Thank you for your patience!',
    });
  } catch {
    messages.value.push({
      id:      Date.now(),
      role:    'assistant',
      content: '⚠️ Could not process escalation. Please call us directly.',
    });
  }
};

const formatMessage = (text: string): string => {
  // Convert **bold** and basic markdown to HTML
  return text
    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
    .replace(/\*(.*?)\*/g, '<em>$1</em>')
    .replace(/\n/g, '<br>');
};

const parseMessageMetadata = (content: string) => {
  let cleanContent = content || '';
  let suggestions: string[] = [];
  let chart: any = null;

  // 1. Parse suggestions
  const suggMatch = cleanContent.match(/\[Suggestions:\s*(.*?)\]/i);
  if (suggMatch) {
    const suggestionsText = suggMatch[1];
    suggestions = suggestionsText.split('|').map(s => s.trim()).filter(Boolean);
    cleanContent = cleanContent.replace(/\[Suggestions:\s*.*?\]/i, '').trim();
  }

  // 2. Parse chart
  const chartMatch = cleanContent.match(/\[Chart:\s*(.*?)\]/i);
  if (chartMatch) {
    const chartParamsText = chartMatch[1];
    const params: any = {};
    chartParamsText.split('|').forEach(part => {
      const [key, value] = part.split('=').map(s => s.trim());
      if (key && value) {
        params[key.toLowerCase()] = value;
      }
    });

    const type = params.type || 'bar';
    const title = params.title || 'Chart';
    const labels = params.labels ? params.labels.split(',').map((s: string) => s.trim()) : [];
    const data = params.data ? params.data.split(',').map((s: string) => parseFloat(s.trim())) : [];
    const label = params.label || 'Value';

    chart = { type, title, labels, data, label };
    cleanContent = cleanContent.replace(/\[Chart:\s*.*?\]/i, '').trim();
  }

  return { content: cleanContent, suggestions, chart };
};

const getBarHeight = (val: number, allData: number[]) => {
  const max = Math.max(...allData, 1);
  return (val / max) * 60; // Max height 60px
};

const getPieSlices = (data: number[]) => {
  const total = data.reduce((sum, val) => sum + val, 0) || 1;
  let accumulatedPercent = 0;
  return data.map(val => {
    const percent = (val / total) * 100;
    const dashArray = `${percent} ${100 - percent}`;
    const dashOffset = -accumulatedPercent;
    accumulatedPercent += percent;
    return { dashArray, dashOffset };
  });
};

const getPieColor = (idx: number) => {
  const colors = ['#6366f1', '#a855f7', '#f59e0b', '#10b981', '#ef4444', '#06b6d4'];
  return colors[idx % colors.length];
};

const getLinePoints = (data: number[]) => {
  if (data.length === 0) return [];
  const max = Math.max(...data, 1);
  const min = Math.min(...data, 0);
  const range = max - min || 1;
  const stepX = 100 / (data.length - 1 || 1);
  return data.map((val, idx) => {
    const x = idx * stepX;
    const y = 35 - ((val - min) / range) * 30; // 5px padding top/bottom
    return { x, y };
  });
};

const getLinePath = (data: number[]) => {
  const points = getLinePoints(data);
  if (points.length === 0) return '';
  return points.reduce((path, p, idx) => {
    return path + (idx === 0 ? `M ${p.x} ${p.y}` : ` L ${p.x} ${p.y}`);
  }, '');
};

const getLineAreaPath = (data: number[]) => {
  const points = getLinePoints(data);
  if (points.length === 0) return '';
  let path = points.reduce((path, p, idx) => {
    return path + (idx === 0 ? `M ${p.x} ${p.y}` : ` L ${p.x} ${p.y}`);
  }, '');
  path += ` L ${points[points.length - 1].x} 40 L ${points[0].x} 40 Z`;
  return path;
};

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
  });
};

// Reset unread count when opened
const handleOpen = () => {
  isOpen.value = true;
  unreadCount.value = 0;
};

const fetchFrequentQuestions = async () => {
  try {
    const { data } = await axios.get('/api/ai/chat/frequent-questions');
    if (data.success && data.questions && data.questions.length > 0) {
      quickReplies.value = data.questions;
    }
  } catch (err) {
    console.error('Failed to fetch frequent questions', err);
  }
};

onMounted(() => {
  fetchFrequentQuestions();
});
</script>

<style scoped>
.chat-slide-enter-active,
.chat-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.chat-slide-enter-from,
.chat-slide-leave-to {
  opacity: 0;
  transform: translateY(20px) scale(0.95);
}

.icon-switch-enter-active,
.icon-switch-leave-active {
  transition: all 0.15s ease;
}
.icon-switch-enter-from,
.icon-switch-leave-to {
  opacity: 0;
  transform: rotate(90deg) scale(0.8);
}
</style>
