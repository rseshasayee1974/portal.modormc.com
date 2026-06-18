<template>
  <div class="flex items-center gap-2 bg-slate-50 rounded-xl px-3 py-2 border border-slate-100">
    <!-- Play/Pause button -->
    <button
      @click="togglePlayback"
      :disabled="!src && !base64"
      class="w-7 h-7 rounded-lg flex items-center justify-center transition-colors"
      :class="isPlaying ? 'bg-indigo-600 text-white' : 'bg-slate-200 hover:bg-indigo-100 text-slate-600 hover:text-indigo-600'"
    >
      <PauseIcon v-if="isPlaying" class="w-3.5 h-3.5" />
      <PlayIcon v-else class="w-3.5 h-3.5" />
    </button>

    <!-- Progress bar -->
    <div class="flex-1 flex flex-col gap-0.5">
      <div
        class="relative h-1.5 bg-slate-200 rounded-full cursor-pointer overflow-hidden"
        @click="seekTo"
        ref="progressBar"
      >
        <div
          class="h-full bg-gradient-to-r from-indigo-500 to-violet-500 rounded-full transition-all duration-100"
          :style="{ width: `${progressPercent}%` }"
        ></div>
      </div>
      <div class="flex justify-between">
        <span class="text-[10px] text-slate-400 tabular-nums">{{ formatTime(currentTime) }}</span>
        <span class="text-[10px] text-slate-400 tabular-nums">{{ formatTime(duration) }}</span>
      </div>
    </div>

    <!-- Volume -->
    <button
      @click="toggleMute"
      class="text-slate-400 hover:text-indigo-600 transition-colors"
    >
      <SpeakerXMarkIcon v-if="isMuted" class="w-4 h-4" />
      <SpeakerWaveIcon v-else class="w-4 h-4" />
    </button>

    <!-- Hidden audio element -->
    <audio
      ref="audioEl"
      :src="computedSrc"
      @timeupdate="onTimeUpdate"
      @loadedmetadata="onLoadedMetadata"
      @ended="onEnded"
    ></audio>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue';
import { PlayIcon, PauseIcon, SpeakerWaveIcon, SpeakerXMarkIcon } from '@heroicons/vue/24/outline';

// ── Props ──────────────────────────────────────────────────────────────────
const props = defineProps<{
  src?: string;                 // URL to audio file
  base64?: string;              // base64-encoded audio
  contentType?: string;         // MIME type for base64 audio
  autoplay?: boolean;
}>();

// ── State ──────────────────────────────────────────────────────────────────
const audioEl       = ref<HTMLAudioElement | null>(null);
const progressBar   = ref<HTMLElement | null>(null);
const isPlaying     = ref(false);
const isMuted       = ref(false);
const currentTime   = ref(0);
const duration      = ref(0);

// ── Computed ───────────────────────────────────────────────────────────────

const computedSrc = computed(() => {
  if (props.src) return props.src;
  if (props.base64) {
    const mime = props.contentType ?? 'audio/wav';
    return `data:${mime};base64,${props.base64}`;
  }
  return undefined;
});

const progressPercent = computed(() => {
  if (!duration.value) return 0;
  return (currentTime.value / duration.value) * 100;
});

// ── Methods ────────────────────────────────────────────────────────────────

const togglePlayback = () => {
  if (!audioEl.value) return;

  if (isPlaying.value) {
    audioEl.value.pause();
    isPlaying.value = false;
  } else {
    audioEl.value.play();
    isPlaying.value = true;
  }
};

const toggleMute = () => {
  if (!audioEl.value) return;
  isMuted.value          = !isMuted.value;
  audioEl.value.muted    = isMuted.value;
};

const seekTo = (event: MouseEvent) => {
  if (!progressBar.value || !audioEl.value || !duration.value) return;
  const rect    = progressBar.value.getBoundingClientRect();
  const clickX  = event.clientX - rect.left;
  const percent = clickX / rect.width;
  audioEl.value.currentTime = percent * duration.value;
};

const onTimeUpdate = () => {
  currentTime.value = audioEl.value?.currentTime ?? 0;
};

const onLoadedMetadata = () => {
  duration.value = audioEl.value?.duration ?? 0;
  if (props.autoplay) togglePlayback();
};

const onEnded = () => {
  isPlaying.value   = false;
  currentTime.value = 0;
};

const formatTime = (seconds: number): string => {
  if (!seconds || isNaN(seconds)) return '0:00';
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
};

// ── Cleanup ────────────────────────────────────────────────────────────────
onUnmounted(() => {
  audioEl.value?.pause();
});
</script>
