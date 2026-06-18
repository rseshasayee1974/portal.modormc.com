import { defineStore } from 'pinia';
import { ref } from 'vue';
import axios from 'axios';

export const useVoiceStore = defineStore('aiVoice', () => {
    const isRecording = ref<boolean>(false);
    const isProcessing = ref<boolean>(false);
    const transcript = ref<string>('');
    const replyText = ref<string>('');
    const audioBase64 = ref<string | null>(null);
    const voiceLogs = ref<any[]>([]);
    const voiceSessionToken = ref<string | null>(localStorage.getItem('ai_voice_session_token'));

    async function speechToText(audioBlob: Blob, language: string = 'unknown') {
        isProcessing.value = true;
        const formData = new FormData();
        formData.append('audio', audioBlob, 'recording.wav');
        formData.append('language', language);

        try {
            const response = await axios.post('/api/ai/speech-to-text', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            if (response.data.success) {
                transcript.value = response.data.transcript;
                return response.data;
            }
            throw new Error(response.data.error || 'STT failed');
        } catch (error: any) {
            console.error('STT error:', error);
            throw error;
        } finally {
            isProcessing.value = false;
        }
    }

    async function textToSpeech(text: string, language: string = 'ta-IN') {
        isProcessing.value = true;
        try {
            const response = await axios.post('/api/ai/text-to-speech', {
                text,
                language
            });

            if (response.data.success) {
                audioBase64.value = response.data.audio_base64;
            }
            // Return full response so callers can check fallback_to_browser
            return response.data;
        } catch (error: any) {
            // When Sarvam is unconfigured (503) or failed (422), return a
            // fallback object so callers can use browser TTS instead of crashing.
            if (error?.response?.status === 503 || error?.response?.status === 422) {
                return error.response.data ?? { success: false, fallback_to_browser: true };
            }
            console.error('TTS error:', error);
            throw error;
        } finally {
            isProcessing.value = false;
        }
    }

    async function sendVoiceChat(audioBlob: Blob, language: string = 'unknown', ttsEnabled: boolean = true) {
        isProcessing.value = true;
        const formData = new FormData();
        formData.append('audio', audioBlob, 'recording.wav');
        formData.append('language', language);
        formData.append('tts_enabled', ttsEnabled ? '1' : '0');
        if (voiceSessionToken.value) {
            formData.append('session_token', voiceSessionToken.value);
        }

        try {
            const response = await axios.post('/api/ai/voice-chat', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });

            if (response.data.success) {
                transcript.value = response.data.transcript;
                replyText.value = response.data.reply;
                audioBase64.value = response.data.audio_base64;

                if (response.data.session_token) {
                    voiceSessionToken.value = response.data.session_token;
                    localStorage.setItem('ai_voice_session_token', response.data.session_token);
                }

                return response.data;
            }
            throw new Error(response.data.error || 'Voice chat failed');
        } catch (error: any) {
            console.error('Voice chat error:', error);
            throw error;
        } finally {
            isProcessing.value = false;
        }
    }

    async function loadVoiceHistory() {
        try {
            const response = await axios.get('/api/ai/voice-history');
            voiceLogs.value = response.data.data || [];
            return response.data;
        } catch (error) {
            console.error('Error loading voice history:', error);
        }
    }

    function clearVoiceState() {
        transcript.value = '';
        replyText.value = '';
        audioBase64.value = null;
    }

    function resetVoiceSession() {
        clearVoiceState();
        voiceSessionToken.value = null;
        localStorage.removeItem('ai_voice_session_token');
    }

    return {
        isRecording,
        isProcessing,
        transcript,
        replyText,
        audioBase64,
        voiceLogs,
        voiceSessionToken,
        speechToText,
        textToSpeech,
        sendVoiceChat,
        loadVoiceHistory,
        clearVoiceState,
        resetVoiceSession
    };
});
