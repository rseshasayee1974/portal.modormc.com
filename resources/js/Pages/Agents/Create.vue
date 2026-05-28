<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseInput from '@/Components/Base/BaseInput.vue';
import BaseSelect from '@/Components/Base/BaseSelect.vue';
import BaseButton from '@/Components/Base/BaseButton.vue';
import Swal2 from 'sweetalert2';
import axios from 'axios';
import {
    CpuChipIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
    PuzzlePieceIcon,
    CodeBracketIcon,
    CheckIcon,
    PlusIcon,
    TrashIcon,
    PlayIcon,
    DocumentTextIcon,
    BookmarkSquareIcon,
} from '@heroicons/vue/24/outline';

interface ToolInfo {
    name: string;
    class: string;
    description: string;
}

const props = defineProps<{
    availableTools: ToolInfo[];
}>();

const page = usePage();

const currentStep = ref(1);

const form = useForm({
    name: '',
    instructions: 'You are a helpful assistant.',
    type: 'plain',
    tools: [] as string[],
    schema: [] as { name: string; type: string; required: boolean; description: string; enum_raw: string }[],
});

// Step 4: Sandbox Playground testing state
const testPrompt = ref('');
const testResponse = ref('');
const isTesting = ref(false);
const testError = ref('');

const schemaFieldTypes = [
    { label: 'String', value: 'string' },
    { label: 'Number (Decimal)', value: 'number' },
    { label: 'Integer', value: 'integer' },
    { label: 'Boolean', value: 'boolean' },
    { label: 'Enum (Single Choice)', value: 'enum' },
];

const addSchemaField = () => {
    form.schema.push({
        name: '',
        type: 'string',
        required: true,
        description: '',
        enum_raw: '',
    });
};

const removeSchemaField = (index: number) => {
    form.schema.splice(index, 1);
};

const handleToolToggle = (toolClass: string) => {
    const idx = form.tools.indexOf(toolClass);
    if (idx > -1) {
        form.tools.splice(idx, 1);
    } else {
        form.tools.push(toolClass);
    }
};

const nextStep = () => {
    if (currentStep.value === 1) {
        if (!form.name.trim()) {
            Swal2.fire({ icon: 'warning', title: 'Agent Name is required', text: 'Please enter a name for your agent.' });
            return;
        }
        if (!/^[A-Za-z]+$/.test(form.name)) {
            Swal2.fire({ icon: 'warning', title: 'Invalid Agent Name', text: 'Agent name must contain letters only (e.g. HelpDesk).' });
            return;
        }
        if (!form.instructions.trim()) {
            Swal2.fire({ icon: 'warning', title: 'Instructions required', text: 'System instructions define the agent persona.' });
            return;
        }
    }

    if (currentStep.value === 3 && form.type === 'structured') {
        // Validate schema fields
        for (let i = 0; i < form.schema.length; i++) {
            const f = form.schema[i];
            if (!f.name.trim()) {
                Swal2.fire({ icon: 'warning', title: 'Field name required', text: `Field #${i + 1} is missing a name.` });
                return;
            }
            if (!/^[a-z0-9_]+$/.test(f.name)) {
                Swal2.fire({ icon: 'warning', title: 'Invalid field name', text: `Field name "${f.name}" must be alphanumeric & snake_case.` });
                return;
            }
            if (f.type === 'enum' && !f.enum_raw.trim()) {
                Swal2.fire({ icon: 'warning', title: 'Enum values required', text: `Please define comma-separated enum choices for field "${f.name}".` });
                return;
            }
        }
    }

    currentStep.value++;
};

const prevStep = () => {
    if (currentStep.value > 1) {
        currentStep.value--;
    }
};

const testAgentConfiguration = async () => {
    if (!testPrompt.value.trim() || isTesting.value) return;

    isTesting.value = true;
    testResponse.value = '';
    testError.value = '';

    // Map schema fields for the test endpoint
    const mappedSchema = form.schema.map(f => ({
        name: f.name,
        type: f.type,
        required: f.required,
        description: f.description,
        enum_values: f.type === 'enum' ? f.enum_raw.split(',').map(s => s.trim()).filter(Boolean) : [],
    }));

    try {
        const response = await axios.post(route('settings.agents.test'), {
            prompt: testPrompt.value,
            instructions: form.instructions,
            type: form.type,
            tools: form.tools,
            schema: mappedSchema,
        });

        const data = response.data;

        if (data.success) {
            let reply = data.response;
            if (typeof reply === 'object') {
                reply = JSON.stringify(reply, null, 2);
            }
            testResponse.value = reply;
        } else {
            testError.value = data.error || 'Failed to execute prompt in playground.';
        }
    } catch (err: any) {
        testError.value = err.response?.data?.error || err.response?.data?.message || err.message || 'Request failed.';
    } finally {
        isTesting.value = false;
    }
};

// Generate PHP Code Preview dynamically on Step 5
const generatedCodePreview = computed(() => {
    const className = form.name ? form.name.charAt(0).toUpperCase() + form.name.slice(1) : 'MyAgent';
    const cleanInstructions = form.instructions.replace(/'/g, "\\'");
    
    let toolsListCode = '';
    form.tools.forEach(t => {
        const shortName = t.split('\\').pop();
        toolsListCode += `            new \\App\\Ai\\Tools\\${shortName}(),\n`;
    });
    toolsListCode = toolsListCode.trimEnd();

    if (form.type === 'structured') {
        let schemaFieldsCode = '';
        form.schema.forEach(f => {
            let chain = `$schema->${f.type}()`;
            if (f.required) chain += '->required()';
            if (f.description) chain += `->description('${f.description.replace(/'/g, "\\'")}')`;
            if (f.type === 'enum') {
                const arr = f.enum_raw.split(',').map(s => `'${s.trim().replace(/'/g, "\\'")}'`).join(', ');
                chain = `$schema->enum([${arr}])`;
                if (f.required) chain += '->required()';
                if (f.description) chain += `->description('${f.description.replace(/'/g, "\\'")}')`;
            }
            schemaFieldsCode += `            '${f.name}' => ${chain},\n`;
        });
        schemaFieldsCode = schemaFieldsCode.trimEnd();

        return `<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class ${className} implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return '${cleanInstructions}';
    }

    public function messages(): iterable
    {
        return [];
    }

    public function tools(): iterable
    {
        return [
${toolsListCode}
        ];
    }

    public function schema(JsonSchema $schema): array
    {
        return [
${schemaFieldsCode}
        ];
    }
}`;
    }

    return `<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class ${className} implements Agent, Conversational, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return '${cleanInstructions}';
    }

    public function messages(): iterable
    {
        return [];
    }

    public function tools(): iterable
    {
        return [
${toolsListCode}
        ];
    }
}`;
});

const submit = () => {
    // Map raw enum string back to lists for database post
    const payloadSchema = form.schema.map(f => ({
        name: f.name,
        type: f.type,
        required: f.required,
        description: f.description,
        enum_values: f.type === 'enum' ? f.enum_raw.split(',').map(s => s.trim()).filter(Boolean) : [],
    }));

    form.transform((data) => ({
        ...data,
        schema: payloadSchema,
    })).post(route('settings.agents.store'), {
        onSuccess: () => {
            Swal2.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'AI Agent class written successfully!',
                showConfirmButton: false,
                timer: 2000,
            });
        },
    });
};
</script>

<template>
    <AppLayout title="Create AI Agent">
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('settings.agents.index')" class="p-2 bg-white rounded-xl border border-slate-200 hover:bg-slate-50 transition-all cursor-pointer">
                    <ArrowLeftIcon class="w-5 h-5 text-slate-500" />
                </Link>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.24em] text-slate-400">Agent Construction Kit</p>
                    <h1 class="text-xl font-black tracking-tight text-slate-900">Create AI Agent</h1>
                </div>
            </div>
        </template>

        <div class="py-6 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Steps Indicator -->
            <div class="mb-8 relative flex justify-between items-center max-w-xl mx-auto">
                <div class="absolute left-0 right-0 h-0.5 bg-slate-200 top-1/2 -translate-y-1/2 -z-10"></div>
                <div class="absolute left-0 h-0.5 bg-indigo-500 top-1/2 -translate-y-1/2 -z-10 transition-all duration-300" 
                     :style="{ width: ((currentStep - 1) / (form.type === 'structured' ? 4 : 3)) * 100 + '%' }">
                </div>
                
                <div v-for="stepNum in (form.type === 'structured' ? 5 : 4)" :key="stepNum" class="flex flex-col items-center">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs shadow transition-all duration-300 border-2"
                         :class="[
                             currentStep === stepNum 
                                 ? 'bg-indigo-650 text-white border-indigo-650' 
                                 : currentStep > stepNum
                                 ? 'bg-emerald-500 text-white border-emerald-500'
                                 : 'bg-white text-slate-400 border-slate-200'
                         ]"
                    >
                        <CheckIcon v-if="currentStep > stepNum" class="w-4 h-4" />
                        <span v-else>{{ stepNum }}</span>
                    </div>
                </div>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="p-6 md:p-8">

                    <!-- STEP 1: PERSONA -->
                    <div v-if="currentStep === 1" class="space-y-6">
                        <div>
                            <h3 class="text-base font-black text-slate-800 uppercase tracking-wide">Step 1: Agent Persona</h3>
                            <p class="text-xs text-slate-400 mt-1">Configure class name, system instructions, and choose output formats.</p>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <BaseInput
                                v-model="form.name"
                                label="Agent Class Name"
                                placeholder="e.g. SalesAssistant"
                                hint="PascalCase. Must be letters only. Generates class App\Ai\Agents\[Name].php"
                                required
                            />
                            
                            <BaseSelect
                                v-model="form.type"
                                :options="[
                                    { label: 'Plain Text Conversation', value: 'plain' },
                                    { label: 'Structured JSON Schema', value: 'structured' }
                                ]"
                                option-label="label"
                                option-value="value"
                                label="Output Format"
                                placeholder="Choose format"
                                required
                            />
                        </div>

                        <div>
                            <label class="block text-xs font-black uppercase text-slate-400 tracking-wider mb-2">System Instructions / Persona</label>
                            <textarea
                                v-model="form.instructions"
                                rows="5"
                                class="w-full text-sm border-slate-200 rounded-xl focus:border-indigo-400 focus:ring focus:ring-indigo-50"
                                placeholder="Define the rules, limitations, and tone the agent should use..."
                            ></textarea>
                            <span class="text-[10px] text-slate-400 font-bold block mt-1">System prompt injected automatically on LLM invocation.</span>
                        </div>
                    </div>

                    <!-- STEP 2: TOOLS BINDING -->
                    <div v-if="currentStep === 2" class="space-y-6">
                        <div>
                            <h3 class="text-base font-black text-slate-800 uppercase tracking-wide">Step 2: Bind Custom Tools</h3>
                            <p class="text-xs text-slate-400 mt-1">Select functions your agent can trigger dynamically based on context.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <div v-for="tool in availableTools" 
                                 :key="tool.class" 
                                 class="border rounded-2xl p-4 flex items-start gap-4 transition-all duration-150 cursor-pointer"
                                 :class="[
                                     form.tools.includes(tool.class)
                                         ? 'border-indigo-500 bg-indigo-50/20'
                                         : 'border-slate-200 hover:border-slate-350'
                                 ]"
                                 @click="handleToolToggle(tool.class)"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.tools.includes(tool.class)"
                                    class="h-4.5 w-4.5 rounded border-slate-300 text-indigo-650 focus:ring-indigo-500 mt-0.5 cursor-pointer"
                                />
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-slate-800 text-sm">{{ tool.name }}</h4>
                                        <span class="text-[9px] font-mono text-slate-400">{{ tool.class }}</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-1">{{ tool.description || 'No description provided.' }}</p>
                                </div>
                            </div>

                            <div v-if="!availableTools.length" class="text-center py-10 bg-slate-50 rounded-2xl border border-dashed border-slate-200 text-slate-400">
                                <PuzzlePieceIcon class="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                <p class="text-xs font-bold">No custom tools found. Run <code>php artisan make:tool</code> first.</p>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3: STRUCTURED OUTPUT EDITOR (Structured only) -->
                    <div v-if="currentStep === 3 && form.type === 'structured'" class="space-y-6">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-base font-black text-slate-800 uppercase tracking-wide">Step 3: Define Output Schema</h3>
                                <p class="text-xs text-slate-400 mt-1">Specify JSON properties, types, and descriptions required from the AI response.</p>
                            </div>
                            <BaseButton
                                label="Add Field"
                                :icon="PlusIcon"
                                severity="secondary"
                                size="small"
                                class="!py-1.5 !px-3"
                                @click="addSchemaField"
                            />
                        </div>

                        <div class="space-y-4 max-h-[400px] overflow-y-auto pr-1">
                            <div v-for="(field, index) in form.schema" :key="index" class="bg-slate-50 p-4 border border-slate-200 rounded-2xl flex flex-col gap-4 relative">
                                <button type="button" 
                                        class="absolute top-4 right-4 p-1.5 text-slate-400 hover:text-red-500 hover:bg-white rounded-lg border border-transparent hover:border-slate-100 transition-all cursor-pointer shadow-sm"
                                        @click="removeSchemaField(index)"
                                >
                                    <TrashIcon class="w-4 h-4" />
                                </button>

                                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                                    <div class="md:col-span-4">
                                        <BaseInput
                                            v-model="field.name"
                                            label="Property Name"
                                            placeholder="e.g. sentiment_score"
                                            hint="Use snake_case"
                                            required
                                        />
                                    </div>
                                    <div class="md:col-span-4">
                                        <BaseSelect
                                            v-model="field.type"
                                            :options="schemaFieldTypes"
                                            option-label="label"
                                            option-value="value"
                                            label="Data Type"
                                            placeholder="Choose type"
                                            required
                                        />
                                    </div>
                                    <div class="md:col-span-4 flex items-center pt-5">
                                        <label class="inline-flex items-center cursor-pointer gap-2">
                                            <input
                                                type="checkbox"
                                                v-model="field.required"
                                                class="h-4 w-4 rounded border-slate-300 text-indigo-650 focus:ring-indigo-500 cursor-pointer"
                                            />
                                            <span class="text-xs font-bold text-slate-600">Required Field</span>
                                        </label>
                                    </div>
                                </div>

                                <div v-if="field.type === 'enum'" class="w-full">
                                    <BaseInput
                                        v-model="field.enum_raw"
                                        label="Enum Options"
                                        placeholder="positive, neutral, negative"
                                        hint="Enter comma-separated choices for the list"
                                        required
                                    />
                                </div>

                                <div class="w-full">
                                    <BaseInput
                                        v-model="field.description"
                                        label="Description / Context"
                                        placeholder="e.g. Score indicating customer sentiment value between 0 and 1."
                                    />
                                </div>
                            </div>

                            <div v-if="!form.schema.length" class="text-center py-10 bg-slate-50 rounded-2xl border border-dashed border-slate-200 text-slate-400">
                                <CodeBracketIcon class="w-8 h-8 mx-auto mb-2 text-slate-300" />
                                <p class="text-xs font-bold">No fields defined. Click "Add Field" above to setup your JSON schema.</p>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 4: INTERACTIVE PLAYGROUND (Sandbox) -->
                    <div v-if="currentStep === (form.type === 'structured' ? 4 : 3)" class="space-y-6">
                        <div>
                            <h3 class="text-base font-black text-slate-800 uppercase tracking-wide">Step {{ currentStep }}: Live Playground Sandbox</h3>
                            <p class="text-xs text-slate-400 mt-1">Test the current configuration. Prompts are run as an anonymous agent using your system instructions and tools.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Input -->
                            <div class="flex flex-col gap-4">
                                <div>
                                    <label class="block text-xs font-black uppercase text-slate-400 tracking-wider mb-2">Test Prompt</label>
                                    <textarea
                                        v-model="testPrompt"
                                        rows="4"
                                        class="w-full text-sm border-slate-200 rounded-xl focus:border-indigo-400 focus:ring focus:ring-indigo-50"
                                        placeholder="Type a query to verify agent reasoning and tool usage..."
                                    ></textarea>
                                </div>

                                <BaseButton
                                    label="Execute Test Run"
                                    :icon="PlayIcon"
                                    severity="primary"
                                    class="w-full"
                                    :loading="isTesting"
                                    @click="testAgentConfiguration"
                                />
                            </div>

                            <!-- Output -->
                            <div class="flex flex-col">
                                <label class="block text-xs font-black uppercase text-slate-400 tracking-wider mb-2">Agent Evaluation Response</label>
                                <div class="flex-1 bg-slate-950 border border-slate-900 rounded-xl p-4 min-h-[140px] text-xs font-mono overflow-auto max-h-72">
                                    <span v-if="isTesting" class="text-slate-400 animate-pulse">Running tools and generating response...</span>
                                    <pre v-else-if="testResponse" class="text-slate-100 whitespace-pre">{{ testResponse }}</pre>
                                    <span v-else-if="testError" class="text-red-400">{{ testError }}</span>
                                    <span v-else class="text-slate-500 italic">Run execution to see output logs.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 5: PREVIEW CODE & FINISH -->
                    <div v-if="currentStep === (form.type === 'structured' ? 5 : 4)" class="space-y-6">
                        <div>
                            <h3 class="text-base font-black text-slate-800 uppercase tracking-wide">Step {{ currentStep }}: Class Code Preview</h3>
                            <p class="text-xs text-slate-400 mt-1">Review the dynamically generated class file code. Click finish to save.</p>
                        </div>

                        <div class="flex flex-col border border-slate-200 rounded-2xl overflow-hidden shadow-sm">
                            <div class="bg-slate-100 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <DocumentTextIcon class="w-4.5 h-4.5 text-slate-500" />
                                    <span class="text-xs font-mono font-bold text-slate-700">app/Ai/Agents/{{ form.name || 'MyAgent' }}.php</span>
                                </div>
                                <span class="text-[9px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-100 rounded px-1.5 py-0.5">READY</span>
                            </div>
                            <pre class="bg-slate-950 p-5 text-slate-200 text-xs font-mono max-h-96 overflow-auto leading-relaxed">{{ generatedCodePreview }}</pre>
                        </div>
                    </div>

                </div>

                <!-- Footer Actions -->
                <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex justify-between">
                    <BaseButton
                        v-if="currentStep > 1"
                        label="Back"
                        :icon="ArrowLeftIcon"
                        severity="secondary"
                        @click="prevStep"
                    />
                    <div v-else></div>

                    <BaseButton
                        v-if="currentStep < (form.type === 'structured' ? 5 : 4)"
                        label="Next"
                        :icon="ArrowRightIcon"
                        icon-pos="right"
                        severity="primary"
                        @click="nextStep"
                    />

                    <BaseButton
                        v-else
                        label="Create & Save Agent"
                        :icon="BookmarkSquareIcon"
                        severity="success"
                        :loading="form.processing"
                        @click="submit"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
