<script setup lang="ts">
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useForm, Link } from '@inertiajs/vue3';
import { 
    PaintBrushIcon, 
    CheckBadgeIcon, 
    EyeIcon, 
    AdjustmentsHorizontalIcon,
    DocumentDuplicateIcon
} from '@heroicons/vue/24/outline';
import BaseDataTable from '@/Components/Base/BaseDataTable.vue';
import Column from 'primevue/column';

const props = defineProps<{
    templates: any[];
    settings: Record<string, any>;
    modules: any[];
}>();

const activeTab = ref('modules'); // 'modules' or 'library'

const assignForm = useForm({
    module_key: '',
    print_template_id: null as number | null
});

const selectTemplateForModule = (moduleKey: string, templateId: number) => {
    assignForm.module_key = moduleKey;
    assignForm.print_template_id = templateId;
    assignForm.post(route('templates.assign'), {
        preserveScroll: true,
        onSuccess: () => {
            // Success notification if implemented
        }
    });
};

const getSelectedTemplateId = (moduleKey: string) => {
    return props.settings[moduleKey]?.print_template_id;
};

const getSelectedTemplateName = (moduleKey: string) => {
    const id = getSelectedTemplateId(moduleKey);
    return props.templates.find(t => t.id === id)?.name || '— Not Assigned —';
};
</script>

<template>
    <AppLayout title="Template Engine">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-50 rounded-2xl">
                        <PaintBrushIcon class="w-6 h-6 text-indigo-600" />
                    </div>
                    <div>
                        <h2 class="text-xl font-black text-slate-800 uppercase tracking-tight">Template Engine</h2>
                        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Configure document aesthetics & layouts</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Navigation Tabs -->
            <div class="flex items-center gap-1 bg-slate-100 rounded-xl p-1 mb-8 w-fit border border-slate-200 shadow-sm">
                <button 
                    @click="activeTab = 'modules'"
                    :class="['px-6 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all', activeTab === 'modules' ? 'bg-white text-indigo-600 shadow-sm shadow-indigo-100' : 'text-slate-500 hover:bg-slate-200/50']"
                >
                    <div class="flex items-center gap-2">
                        <AdjustmentsHorizontalIcon class="w-3.5 h-3.5" />
                        Module Mapping
                    </div>
                </button>
                <button 
                    @click="activeTab = 'library'"
                    :class="['px-6 py-2.5 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all', activeTab === 'library' ? 'bg-white text-indigo-600 shadow-sm shadow-indigo-100' : 'text-slate-500 hover:bg-slate-200/50']"
                >
                    <div class="flex items-center gap-2">
                        <DocumentDuplicateIcon class="w-3.5 h-3.5" />
                        Template Library
                    </div>
                </button>
            </div>

            <!-- Module Mapping Section -->
            <div v-if="activeTab === 'modules'" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div v-for="module in modules" :key="module.key" class="module-card">
                        <div class="module-card__header">
                            <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">{{ module.name }}</h3>
                            <div class="p-2 bg-indigo-50 border border-indigo-100 text-indigo-600 rounded-lg text-[10px] font-black uppercase tracking-widest">
                                {{ getSelectedTemplateName(module.key) }}
                            </div>
                        </div>

                        <div class="module-card__body">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-4 px-1">Select Active Design:</p>
                            <div class="grid grid-cols-1 gap-2">
                                <button 
                                    v-for="template in templates" 
                                    :key="template.id"
                                    @click="selectTemplateForModule(module.key, template.id)"
                                    :class="['template-option', getSelectedTemplateId(module.key) === template.id ? 'template-option--active' : '']"
                                >
                                    <div class="flex items-center justify-between w-full">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center text-slate-400 border border-slate-100 group-hover:bg-indigo-50">
                                                <PaintBrushIcon class="w-4 h-4" />
                                            </div>
                                            <span class="text-[11px] font-black uppercase tracking-tight">{{ template.name }}</span>
                                        </div>
                                        <CheckBadgeIcon v-if="getSelectedTemplateId(module.key) === template.id" class="w-5 h-5 text-indigo-500" />
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Library Section -->
            <div v-if="activeTab === 'library'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <div v-for="template in templates" :key="template.id" class="library-card">
                    <div class="library-card__preview">
                        <!-- Simulated Preview or Thumbnail -->
                        <div class="preview-sim shadow-xl">
                            <div class="w-full h-4 bg-indigo-50 rounded-t-lg mb-4"></div>
                            <div class="space-y-3 px-4 pb-8">
                                <div class="w-2/3 h-2 bg-slate-100 rounded"></div>
                                <div class="w-1/2 h-2 bg-slate-50 rounded"></div>
                                <div class="mt-8 border-t border-slate-100 pt-4 flex justify-between">
                                    <div class="w-1/4 h-8 bg-slate-50 rounded"></div>
                                    <div class="w-1/4 h-8 bg-slate-50 rounded"></div>
                                </div>
                            </div>
                        </div>
                        <div class="library-card__overlay">
                            <Link :href="route('templates.preview', template.id)" class="preview-btn">
                                <EyeIcon class="w-4 h-4" />
                                <span>Live Visual Preview</span>
                            </Link>
                        </div>
                    </div>
                    <div class="library-card__info p-6 mt-4">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="text-sm font-black text-slate-800 uppercase tracking-tight">{{ template.name }}</h4>
                            <span :class="['text-[9px] font-black px-2 py-0.5 rounded border uppercase tracking-widest', {
                                'text-indigo-600 bg-indigo-50 border-indigo-100': template.category === 'invoice',
                                'text-emerald-600 bg-emerald-50 border-emerald-100': template.category === 'inventory',
                                'text-amber-600 bg-amber-50 border-amber-100': template.category === 'statement',
                                'text-orange-600 bg-orange-50 border-orange-100': template.category === 'gst',
                                'text-slate-600 bg-slate-50 border-slate-200': template.category === 'general',
                            }]">
                                {{ template.category }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-medium leading-relaxed">
                            {{ template.config?.description ?? (template.is_system ? 'System default design compatible with all standard modules.' : 'Custom uploaded style targeting specific visual requirements.') }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.module-card {
    background: white;
    border-radius: 20px;
    border: 1px solid #f1f5f9;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.module-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05);
    border-color: #e2e8f0;
}

.module-card__header {
    padding: 20px;
    background: linear-gradient(to bottom right, #f8fafc, #ffffff);
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.module-card__body {
    padding: 20px;
}

.template-option {
    width: 100%;
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid #f1f5f9;
    background: white;
    display: flex;
    align-items: center;
    transition: all 0.2s;
}

.template-option:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.template-option--active {
    background: #eef2ff;
    border-color: #c7d2fe;
    color: #4f46e5;
}

/* Library Styling */
.library-card {
    position: relative;
    border-radius: 24px;
    transition: all 0.4s;
}

.library-card__preview {
    position: relative;
    height: 300px;
    padding: 20px;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 24px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    perspective: 1000px;
}

.preview-sim {
    width: 220px;
    height: 280px;
    background: white;
    border-radius: 8px;
    transform: rotateX(10deg) rotateY(-5deg);
    transition: transform 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.library-card:hover .preview-sim {
    transform: rotateX(0deg) rotateY(0deg) scale(1.05);
}

.library-card__overlay {
    position: absolute;
    inset: 0;
    background: rgba(99, 102, 241, 0.85);
    backdrop-filter: blur(4px);
    opacity: 0;
    visibility: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
    border-radius: 24px;
}

.library-card:hover .library-card__overlay {
    opacity: 1;
    visibility: visible;
}

.preview-btn {
    padding: 12px 24px;
    background: white;
    color: #4f46e5;
    border-radius: 30px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    display: flex;
    items-center: center;
    gap: 8px;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s;
}

.preview-btn:hover {
    transform: scale(1.05);
}
</style>
