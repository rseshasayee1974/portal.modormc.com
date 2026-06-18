<template>
  <div class="flex items-center gap-2">
    <!-- Recording Button -->
    <button
      @click="toggleRecording"
      :disabled="isProcessing"
      :title="isRecording ? 'Stop recording' : 'Start voice input'"
      class="relative flex items-center justify-center rounded-xl transition-all duration-200"
      :class="[
        compact ? 'w-8 h-8' : 'w-10 h-10',
        isRecording
          ? 'bg-red-500 shadow-lg shadow-red-200/60 hover:bg-red-600'
          : 'bg-slate-100 hover:bg-indigo-50 hover:text-indigo-600',
        isProcessing ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
      ]"
    >
      <!-- Pulse ring when recording -->
      <span
        v-if="isRecording"
        class="absolute inset-0 rounded-xl bg-red-400 animate-ping opacity-50"
      ></span>

      <MicrophoneIcon
        v-if="!isProcessing"
        class="relative z-10 transition-colors"
        :class="[
          compact ? 'w-4 h-4' : 'w-5 h-5',
          isRecording ? 'text-white' : 'text-slate-500',
        ]"
      />
      <svg v-else class="animate-spin" :class="compact ? 'w-4 h-4' : 'w-5 h-5'" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
      </svg>
    </button>

    <!-- Duration + Status -->
    <div v-if="!compact" class="flex-1">
      <div v-if="isRecording" class="flex items-center gap-2">
        <div class="flex gap-0.5 items-end h-4">
          <span v-for="i in 5" :key="i" class="w-1 bg-red-400 rounded-full animate-soundwave" :style="`animation-delay: ${i * 100}ms`"></span>
        </div>
        <span class="text-xs font-bold text-red-600 tabular-nums">{{ formatDuration(recordingDuration) }}</span>
      </div>
      <div v-else-if="isProcessing" class="text-xs text-indigo-600 font-semibold">
        Transcribing...
      </div>
      <div v-else-if="transcript" class="text-xs text-slate-600 truncate max-w-[200px]">
        "{{ transcript }}"
      </div>
      <div v-else class="text-xs text-slate-400">
        Click to speak
      </div>
    </div>

    <!-- Clear transcript -->
    <button
      v-if="transcript && !compact"
      @click="clearTranscript"
      class="p-1 rounded-lg hover:bg-slate-100 transition-colors"
      title="Clear"
    >
      <XMarkIcon class="w-3.5 h-3.5 text-slate-400" />
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, onUnmounted } from 'vue';
import axios from 'axios';
import { MicrophoneIcon, XMarkIcon } from '@heroicons/vue/24/outline';

// ── Props ──────────────────────────────────────────────────────────────────
const props = withDefaults(defineProps<{
  language?: string;
  compact?: boolean;
}>(), {
  language: 'unknown',
  compact: false,
});

const emit = defineEmits<{
  (e: 'transcript', text: string): void;
  (e: 'error', message: string): void;
}>();

// ── State ──────────────────────────────────────────────────────────────────
const isRecording        = ref(false);
const isProcessing       = ref(false);
const transcript         = ref('');
const recordingDuration  = ref(0);

let mediaRecorder: MediaRecorder | null = null;
let audioChunks:   Blob[]              = [];
let durationTimer: ReturnType<typeof setInterval> | null = null;
let stream:        MediaStream | null  = null;

let audioContext: AudioContext | null = null;
let analyser: AnalyserNode | null = null;
let source: MediaStreamAudioSourceNode | null = null;
let silenceStart: number | null = null;
let vadInterval: ReturnType<typeof setInterval> | null = null;

// ── Methods ────────────────────────────────────────────────────────────────

const toggleRecording = async () => {
  if (isRecording.value) {
    stopRecording();
  } else {
    await startRecording();
  }
};

const startRecording = async () => {
  try {
    stream       = await navigator.mediaDevices.getUserMedia({ audio: true });
    audioChunks  = [];
    mediaRecorder = new MediaRecorder(stream, { mimeType: getBestMimeType() });

    mediaRecorder.ondataavailable = (e) => {
      if (e.data.size > 0) audioChunks.push(e.data);
    };

    mediaRecorder.onstop = handleRecordingStop;
    mediaRecorder.start(200);

    isRecording.value      = true;
    recordingDuration.value = 0;
    durationTimer          = setInterval(() => recordingDuration.value++, 1000);

    // Setup audio analysis for silence detection
    try {
      const AudioContextClass = window.AudioContext || (window as any).webkitAudioContext;
      if (AudioContextClass) {
        audioContext = new AudioContextClass();
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 512;
        source = audioContext.createMediaStreamSource(stream);
        source.connect(analyser);

        const bufferLength = analyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        
        silenceStart = null;
        
        vadInterval = setInterval(() => {
          if (!analyser) return;
          analyser.getByteTimeDomainData(dataArray);
          
          let sum = 0;
          for (let i = 0; i < bufferLength; i++) {
            const val = (dataArray[i] - 128) / 128;
            sum += val * val;
          }
          const rms = Math.sqrt(sum / bufferLength);
          
          // 0.015 threshold for silence
          const silenceThreshold = 0.015;
          
          if (rms < silenceThreshold) {
            if (silenceStart === null) {
              silenceStart = Date.now();
            } else if (Date.now() - silenceStart >= 2000) {
              // Silence for 2 seconds! Stop recording
              stopRecording();
            }
          } else {
            // Reset silence timer if sound is detected
            silenceStart = null;
          }
        }, 100);
      }
    } catch (audioErr) {
      console.warn('Could not initialize AudioContext for silence detection:', audioErr);
    }

  } catch (err: any) {
    emit('error', 'Microphone access denied. Please allow microphone permissions.');
    console.error('Microphone error:', err);
  }
};

const stopRecording = () => {
  if (mediaRecorder?.state === 'recording') {
    mediaRecorder.stop();
  }
  stream?.getTracks().forEach(t => t.stop());

  if (durationTimer) {
    clearInterval(durationTimer);
    durationTimer = null;
  }

  // Cleanup silence detection
  if (vadInterval) {
    clearInterval(vadInterval);
    vadInterval = null;
  }
  if (source) {
    source.disconnect();
    source = null;
  }
  if (audioContext) {
    if (audioContext.state !== 'closed') {
      audioContext.close();
    }
    audioContext = null;
  }
  analyser = null;
  silenceStart = null;

  isRecording.value = false;
};

const handleRecordingStop = async () => {
  if (audioChunks.length === 0) return;

  isProcessing.value = true;

  try {
    const mimeType  = getBestMimeType();
    const extension = mimeType.includes('webm') ? 'webm' : mimeType.includes('ogg') ? 'ogg' : 'wav';
    const audioBlob = new Blob(audioChunks, { type: mimeType });

    const formData = new FormData();
    formData.append('audio', audioBlob, `recording.${extension}`);
    formData.append('language', props.language);

    const { data } = await axios.post('/api/ai/speech-to-text', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    if (data.success && data.transcript) {
      transcript.value = data.transcript;
      emit('transcript', data.transcript);
    } else {
      emit('error', 'Could not transcribe audio. Please try again.');
    }
  } catch (err: any) {
    const msg = err?.response?.data?.error || 'Transcription failed.';
    emit('error', msg);
    console.error('STT error:', err);
  } finally {
    isProcessing.value = false;
    audioChunks = [];
  }
};

const clearTranscript = () => {
  transcript.value = '';
};

const getBestMimeType = (): string => {
  const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/ogg'];
  return types.find(t => MediaRecorder.isTypeSupported(t)) ?? 'audio/webm';
};

const formatDuration = (seconds: number): string => {
  const m = Math.floor(seconds / 60).toString().padStart(2, '0');
  const s = (seconds % 60).toString().padStart(2, '0');
  return `${m}:${s}`;
};

// ── Cleanup ────────────────────────────────────────────────────────────────
onUnmounted(() => {
  if (isRecording.value) stopRecording();
});
</script>

<style scoped>
@keyframes soundwave {
  0%, 100% { height: 4px; }
  50%       { height: 16px; }
}
.animate-soundwave {
  animation: soundwave 0.8s ease-in-out infinite;
}
</style>
