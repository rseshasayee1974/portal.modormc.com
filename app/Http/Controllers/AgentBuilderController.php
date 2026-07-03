<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\StructuredAnonymousAgent;
use App\Models\AgentChatHistory;

class AgentBuilderController extends Controller
{
    /**
     * List all existing generated Agents.
     */
    public function index()
    {
        $agentsPath = app_path('Ai/Agents');
        $agents = [];

        if (is_dir($agentsPath)) {
            $files = glob($agentsPath . '/*.php');
            foreach ($files as $file) {
                $baseName = basename($file, '.php');
                $className = 'App\\Ai\\Agents\\' . $baseName;

                if (class_exists($className)) {
                    try {
                        $reflection = new \ReflectionClass($className);
                        if ($reflection->isInstantiable()) {
                            $instance = new $className();
                            
                            $instructions = '';
                            if (method_exists($instance, 'instructions')) {
                                $instructions = (string) $instance->instructions();
                            }

                            $isStructured = $instance instanceof \Laravel\Ai\Contracts\HasStructuredOutput;

                            $tools = [];
                            if (method_exists($instance, 'tools')) {
                                foreach ($instance->tools() as $tool) {
                                    $tools[] = basename(str_replace('\\', '/', get_class($tool)));
                                }
                            }

                            $agents[] = [
                                'name'          => $baseName,
                                'class'         => $className,
                                'instructions'  => $instructions,
                                'is_structured' => $isStructured,
                                'tools'         => $tools,
                            ];
                        }
                    } catch (\Exception $e) {
                        // Keep robust, skip class loading errors
                    }
                }
            }
        }

        return Inertia::render('Agents/Index', [
            'agents' => $agents,
        ]);
    }

    /**
     * Show the step-by-step creation wizard.
     */
    public function create()
    {
        $toolsPath = app_path('Ai/Tools');
        $availableTools = [];

        if (is_dir($toolsPath)) {
            $files = glob($toolsPath . '/*.php');
            foreach ($files as $file) {
                $baseName = basename($file, '.php');
                $className = 'App\\Ai\\Tools\\' . $baseName;

                if (class_exists($className)) {
                    try {
                        $reflection = new \ReflectionClass($className);
                        if ($reflection->isInstantiable()) {
                            $instance = new $className();
                            $description = '';
                            if (method_exists($instance, 'description')) {
                                $description = (string) $instance->description();
                            }

                            $availableTools[] = [
                                'name'        => $baseName,
                                'class'       => $className,
                                'description' => $description,
                            ];
                        }
                    } catch (\Exception $e) {
                        // ignore errors
                    }
                }
            }
        }

        return Inertia::render('Agents/Create', [
            'availableTools' => $availableTools,
        ]);
    }

    /**
     * Store and generate the PHP Agent Class file.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|alpha|max:100',
            'instructions' => 'required|string',
            'type'         => 'required|in:plain,structured',
            'tools'        => 'nullable|array',
            'schema'       => 'nullable|array', // List of fields
        ]);

        $name = Str::studly($request->input('name'));
        
        // Sanitize instructions for single quotes in templates
        $instructions = str_replace("'", "\\'", $request->input('instructions'));

        $selectedTools = $request->input('tools', []);
        $toolsCode = '';
        foreach ($selectedTools as $toolClass) {
            $toolsCode .= "            new \\" . ltrim($toolClass, '\\') . "(),\n";
        }
        $toolsCode = rtrim($toolsCode);

        $type = $request->input('type');
        $schemaCode = '';

        if ($type === 'structured') {
            $schemaFields = $request->input('schema', []);
            foreach ($schemaFields as $field) {
                $fieldName = $field['name'] ?? '';
                if (empty($fieldName)) continue;

                $fieldType = $field['type'] ?? 'string';
                $fieldDesc = str_replace("'", "\\'", $field['description'] ?? '');
                
                $typeChain = match ($fieldType) {
                    'number'  => '$schema->number()',
                    'integer' => '$schema->integer()',
                    'boolean' => '$schema->boolean()',
                    'enum'    => '$schema->string()->enum([' . implode(', ', array_map(fn($v) => "'" . str_replace("'", "\\'", trim($v)) . "'", $field['enum_values'] ?? [])) . '])',
                    default   => '$schema->string()',
                };

                if ($field['required'] ?? true) {
                    $typeChain .= '->required()';
                }

                if (!empty($fieldDesc)) {
                    $typeChain .= "->description('{$fieldDesc}')";
                }

                $schemaCode .= "            '{$fieldName}' => {$typeChain},\n";
            }
            $schemaCode = rtrim($schemaCode);
        }

        // Choose appropriate stub template
        if ($type === 'structured') {
            $template = <<<'PHP'
<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class {{ class }} implements Agent, Conversational, HasStructuredOutput, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return '{{ instructions }}';
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
{{ tools }}
        ];
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
{{ schema }}
        ];
    }
}
PHP;
        } else {
            $template = <<<'PHP'
<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Stringable;

class {{ class }} implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return '{{ instructions }}';
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return [];
    }

    /**
     * Get the tools available to the agent.
     *
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
{{ tools }}
        ];
    }
}
PHP;
        }

        // Hydrate variables
        $code = str_replace(
            ['{{ class }}', '{{ instructions }}', '{{ tools }}', '{{ schema }}'],
            [$name, $instructions, $toolsCode, $schemaCode],
            $template
        );

        $dir = app_path('Ai/Agents');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = $dir . '/' . $name . '.php';
        file_put_contents($filePath, $code);

        return redirect()->route('settings.agents.index')
            ->with('success', "Agent {$name} created successfully!");
    }

    /**
     * Test prompting an agent (existing or unsaved temporary config).
     */
    public function test(Request $request)
    {
        $request->validate([
            'prompt'       => 'nullable|string',
            'image'        => 'nullable|string',
            'agent_class'  => 'nullable|string',
            'instructions' => 'nullable|string',
            'type'         => 'nullable|string',
            'tools'        => 'nullable|array',
            'schema'       => 'nullable|array',
        ]);

        $prompt = $request->input('prompt');
        if (empty($prompt) && $request->filled('image')) {
            $prompt = "Analyze this image. If it shows a truck or transit mixer, please identify the truck number / registration plate number and specify if the view is from the front or the back.";
        }

        // Construct System Context for the AI
        $activePlantId = session('active_plant_id') ?: (auth()->user()?->default_plant_id ?: null);
        $plant = $activePlantId ? \App\Models\Plant::with(['addresses.addressType', 'addresses.state', 'contacts'])->find($activePlantId) : null;
        $entity = $plant ? \App\Models\Entity::with(['addresses.addressType', 'addresses.state', 'contacts'])->find($plant->entity_id) : null;
        $currentUser = auth()->user();
        $personnel = $currentUser ? \App\Models\Personnel::with(['department', 'designation', 'reportingManager'])->where('user_id', $currentUser->id)->first() : null;

        $contextLines = [];
        $contextLines[] = "=== SYSTEM CONTEXT (For Agent Use Only) ===";
        if ($currentUser) {
            $contextLines[] = "Current Logged-in User (System Login):";
            $contextLines[] = "  - ID: {$currentUser->id}";
            $contextLines[] = "  - Username: {$currentUser->username}";
            $contextLines[] = "  - Email: {$currentUser->email}";
            if ($currentUser->mobile) {
                $contextLines[] = "  - Mobile: {$currentUser->mobile}";
            }

            if ($personnel) {
                $contextLines[] = "Current Logged-in Personnel Details:";
                $contextLines[] = "  - ID: {$personnel->id}";
                $contextLines[] = "  - Employee Code: " . ($personnel->employee_code ?: 'N/A');
                $contextLines[] = "  - Name: " . trim("{$personnel->first_name} {$personnel->last_name}");
                if ($personnel->email) {
                    $contextLines[] = "  - Personal/Work Email: {$personnel->email}";
                }
                if ($personnel->mobile) {
                    $contextLines[] = "  - Personal/Work Mobile: {$personnel->mobile}";
                }
                if ($personnel->gender) {
                    $contextLines[] = "  - Gender: {$personnel->gender}";
                }
                if ($personnel->employment_type) {
                    $contextLines[] = "  - Employment Type: {$personnel->employment_type}";
                }
                if ($personnel->status) {
                    $contextLines[] = "  - Status: {$personnel->status}";
                }
                if ($personnel->joining_date) {
                    $contextLines[] = "  - Joining Date: " . $personnel->joining_date->format('Y-m-d');
                }
                if ($personnel->department) {
                    $contextLines[] = "  - Department: {$personnel->department->name}";
                }
                if ($personnel->designation) {
                    $contextLines[] = "  - Designation: {$personnel->designation->name}";
                }
                if ($personnel->reportingManager) {
                    $contextLines[] = "  - Reporting Manager: " . trim("{$personnel->reportingManager->first_name} {$personnel->reportingManager->last_name}") . " (Code: " . ($personnel->reportingManager->employee_code ?: 'N/A') . ")";
                }
            }
        }
        if ($plant) {
            $contextLines[] = "Current Active Plant:";
            $contextLines[] = "  - ID: {$plant->id}";
            $contextLines[] = "  - Code: {$plant->code}";
            $contextLines[] = "  - Name: {$plant->name}";
            if ($plant->email_address) $contextLines[] = "  - Email: {$plant->email_address}";
            if ($plant->mobile_number) $contextLines[] = "  - Mobile: {$plant->mobile_number}";
            if ($plant->gstin)         $contextLines[] = "  - GSTIN: {$plant->gstin}";
            if ($plant->plant_type)    $contextLines[] = "  - Type: {$plant->plant_type}";
            if ($plant->latitude && $plant->longitude) {
                $contextLines[] = "  - Coordinates: Lat={$plant->latitude}, Lng={$plant->longitude}";
            }
            $plantAddresses = $plant->addresses;
            if ($plantAddresses && $plantAddresses->count() > 0) {
                $contextLines[] = "  - Addresses:";
                foreach ($plantAddresses as $addr) {
                    $addrTypeName = $addr->addressType?->type ?? ($addr->is_primary ? 'Primary' : 'Address');
                    $stateVal = $addr->state?->state_name ?? ($addr->state_code ?? '');
                    $contextLines[] = "    - Type='{$addrTypeName}': " . trim("{$addr->line_1} {$addr->line_2} {$addr->city} {$stateVal} {$addr->zipcode}");
                }
            }
        }
        if ($entity) {
            $contextLines[] = "Current Active Entity (Company):";
            $contextLines[] = "  - ID: {$entity->id}";
            $contextLines[] = "  - Legal Name: {$entity->legal_name}";
            if ($entity->alias) $contextLines[] = "  - Alias: {$entity->alias}";
            if ($entity->email) $contextLines[] = "  - Email: {$entity->email}";
            if ($entity->url)   $contextLines[] = "  - Website: {$entity->url}";
            $entityAddresses = $entity->addresses;
            if ($entityAddresses && $entityAddresses->count() > 0) {
                $contextLines[] = "  - Addresses:";
                foreach ($entityAddresses as $addr) {
                    $addrTypeName = $addr->addressType?->type ?? ($addr->is_primary ? 'Primary' : 'Address');
                    $stateVal = $addr->state?->state_name ?? '';
                    $contextLines[] = "    - Type='{$addrTypeName}': " . trim("{$addr->line_1} {$addr->line_2} {$addr->city} {$stateVal} {$addr->zipcode}");
                }
            }
        }
        $contextLines[] = "=== END SYSTEM CONTEXT ===";
        $contextString = implode("\n", $contextLines);

        $fullPrompt = $contextString . "\n\nUser Question/Instruction:\n" . $prompt;

        try {
            $attachments = [];
            $image = $request->input('image');
            if (!empty($image) && preg_match('/^data:(image\/[a-zA-Z0-9+.-]+);base64,(.+)$/', $image, $matches)) {
                $mimeType = $matches[1];
                $base64Data = $matches[2];
                $attachments[] = \Laravel\Ai\Files\Image::fromBase64($base64Data, $mimeType);
            }

            // Case 1: Testing an existing saved Agent class
            $agentClass = $request->input('agent_class');
            if (!empty($agentClass) && class_exists($agentClass)) {
                $agent = new $agentClass();
                $result = $this->promptAgentSafely($agent, $fullPrompt, $attachments);
                $response = $result['response'];
                $provider = $result['provider'];
                
                $reply = $response instanceof \Laravel\Ai\Responses\StructuredAgentResponse 
                    ? $response->structured 
                    : $response->text;

                if ((is_array($reply) && count($reply) === 0) || ($reply === null) || (is_string($reply) && trim($reply) === '')) {
                    $reply = $response->text ?? 'No response returned.';
                }

                return response()->json([
                    'success'  => true,
                    'response' => $reply,
                    'provider' => $provider,
                ]);
            }

            // Case 2: Testing an unsaved agent configuration in real-time (Anonymous Agent)
            $instructions = $request->input('instructions', 'You are a helpful assistant.');
            $toolsClasses = $request->input('tools', []);
            $toolsInstances = [];

            foreach ($toolsClasses as $toolClass) {
                if (class_exists($toolClass)) {
                    $toolsInstances[] = new $toolClass();
                }
            }

            $type = $request->input('type', 'plain');

            if ($type === 'structured') {
                $schemaFields = $request->input('schema', []);

                $agent = new StructuredAnonymousAgent(
                    instructions: $instructions,
                    messages: [],
                    tools: $toolsInstances,
                    schema: function ($schema) use ($schemaFields) {
                        $arr = [];
                        foreach ($schemaFields as $field) {
                            $fieldName = $field['name'] ?? '';
                            if (empty($fieldName)) continue;

                            $fieldType = $field['type'] ?? 'string';
                            $fieldDesc = $field['description'] ?? '';
                            
                            $typeObj = match ($fieldType) {
                                'number'  => $schema->number(),
                                'integer' => $schema->integer(),
                                'boolean' => $schema->boolean(),
                                'enum'    => $schema->string()->enum($field['enum_values'] ?? []),
                                default   => $schema->string(),
                            };

                            if (!empty($fieldDesc)) {
                                $typeObj->description($fieldDesc);
                            }
                            if ($field['required'] ?? true) {
                                $typeObj->required();
                            }

                            $arr[$fieldName] = $typeObj;
                        }
                        return $arr;
                    }
                );
            } else {
                $agent = new AnonymousAgent(
                    instructions: $instructions,
                    messages: [],
                    tools: $toolsInstances
                );
            }

            $result = $this->promptAgentSafely($agent, $fullPrompt, $attachments);
            $response = $result['response'];
            $provider = $result['provider'];

            $reply = $response instanceof \Laravel\Ai\Responses\StructuredAgentResponse 
                ? $response->structured 
                : $response->text;

            if ((is_array($reply) && count($reply) === 0) || ($reply === null) || (is_string($reply) && trim($reply) === '')) {
                $reply = $response->text ?? 'No response returned.';
            }

            return response()->json([
                'success'  => true,
                'response' => $reply,
                'provider' => $provider,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Save a completed chat session to agent_chat_histories.
     */
    public function saveHistory(Request $request)
    {
        try {
            $request->validate([
                'agent_name'       => 'required|string|max:100',
                'agent_class'      => 'required|string|max:255',
                'session_language' => 'nullable|string|max:5',
                'messages'         => 'required|array|min:1',
                'messages.*.role'  => 'required|string|in:user,agent,error',
                'messages.*.text'  => 'nullable|string',
                'messages.*.provider' => 'nullable|string',
                'messages.*.image' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('AgentChatHistory Validation Failed', [
                'errors' => $e->errors(),
                'payload' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'errors'  => $e->errors(),
            ], 422);
        }

        try {
            $messages = $request->input('messages');
            $id       = $request->input('id');

            // Build a short plain-text summary from first user + first agent message
            $firstUser  = collect($messages)->firstWhere('role', 'user');
            $firstAgent = collect($messages)->firstWhere('role', 'agent');
            $summary = trim(
                ($firstUser  ? Str::limit($firstUser['text'], 120)  . ' | ' : '') .
                ($firstAgent ? Str::limit(strip_tags($firstAgent['text']), 160) : '')
            );

            $userId  = auth()->id();
            $plantId = session('active_plant_id') ?: (auth()->user()?->default_plant_id ?: null);

            if ($id) {
                $history = AgentChatHistory::find($id);
                if ($history) {
                    // Ensure the current user owns it
                    if ($history->user_id === $userId) {
                        $history->update([
                            'messages'      => $messages,
                            'message_count' => count($messages),
                            'summary'       => $summary ?: null,
                        ]);
                        return response()->json([
                            'success' => true,
                            'id'      => $history->id,
                        ]);
                    }
                }
            }

            $history = AgentChatHistory::create([
                'user_id'          => $userId,
                'plant_id'         => $plantId,
                'agent_name'       => $request->input('agent_name'),
                'agent_class'      => $request->input('agent_class'),
                'session_language' => $request->input('session_language', 'en'),
                'messages'         => $messages,
                'message_count'    => count($messages),
                'summary'          => $summary ?: null,
            ]);

            return response()->json([
                'success' => true,
                'id'      => $history->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('AgentChatHistory Save Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return paginated chat history list (JSON) for the history panel.
     */
    public function chatHistories(Request $request)
    {
        $user = auth()->user();
        $isSaasOwner = $user && $user->isSystemAdmin();

        $query = AgentChatHistory::query()
            ->select(['id', 'user_id', 'plant_id', 'agent_name', 'session_language', 'message_count', 'summary', 'created_at'])
            ->orderByDesc('created_at');

        // Regular users only see their own plant's history
        if (!$isSaasOwner) {
            $query->where('user_id', $user?->id);
            if ($plantId = session('active_plant_id')) {
                $query->where('plant_id', $plantId);
            }
        }

        if ($agent = $request->query('agent')) {
            $query->where('agent_name', $agent);
        }

        $histories = $query->paginate(20);

        return response()->json($histories);
    }

    /**
     * Return the full messages of a single history session.
     */
    public function showHistory(AgentChatHistory $history)
    {
        $user = auth()->user();
        // Non-owners can only view their own plant's sessions
        if (!($user && $user->isSystemAdmin())) {
            abort_if(
                $history->user_id !== $user?->id ||
                ($history->plant_id && $history->plant_id != session('active_plant_id')),
                403,
                'Access denied: this session belongs to a different plant.'
            );
        }
        return response()->json($history);
    }

    /**
     * Safely execute agent prompting with automatic provider fallback chain and multi-key rotation.
     */
    private function promptAgentSafely($agent, string $prompt, array $attachments = [])
    {
        $originalDefaultProvider = config('ai.default');
        $chain = config('ai.chain', ['gemini', 'openai']);
        
        // Remove duplicates and ensure the original default provider (if valid) is tried first
        if (($key = array_search($originalDefaultProvider, $chain)) !== false) {
            unset($chain[$key]);
        }
        array_unshift($chain, $originalDefaultProvider);
        $chain = array_values(array_filter(array_unique($chain)));

        // Store original keys for all providers in the chain so we can restore them later
        $originalProviderKeys = [];
        foreach ($chain as $provider) {
            $provider = trim($provider);
            $originalProviderKeys[$provider] = config("ai.providers.{$provider}.key");
        }

        $lastException = null;

        foreach ($chain as $index => $provider) {
            $provider = trim($provider);
            if (empty($provider)) continue;

            $keyString = $originalProviderKeys[$provider] ?? '';
            
            // For providers like ollama, key is not required
            if (empty($keyString) && $provider !== 'ollama') {
                continue;
            }

            // Split comma-separated multiple keys
            $keys = array_values(array_filter(array_map('trim', explode(',', $keyString))));
            if (empty($keys) && $provider !== 'ollama') {
                continue;
            }

            // If it is ollama, treat as having one empty key to trigger the loop
            if (empty($keys) && $provider === 'ollama') {
                $keys = [''];
            }

            foreach ($keys as $keyIndex => $singleKey) {
                try {
                    // Set active provider
                    config(['ai.default' => $provider]);
                    
                    // Set active key for this provider
                    if ($provider !== 'ollama') {
                        config(["ai.providers.{$provider}.key" => $singleKey]);
                    }

                    // Try executing the prompt
                    $response = $agent->prompt($prompt, $attachments);

                    // Restore all original configs on success
                    config(['ai.default' => $originalDefaultProvider]);
                    foreach ($originalProviderKeys as $p => $k) {
                        config(["ai.providers.{$p}.key" => $k]);
                    }

                    return [
                        'response' => $response,
                        'provider' => $provider,
                    ];

                } catch (\Exception $e) {
                    $lastException = $e;
                    $message = $e->getMessage();

                    \Log::warning("AI provider [{$provider}] key index [{$keyIndex}] failed. Fallback triggered.", [
                        'error' => $message,
                        'has_more_keys' => isset($keys[$keyIndex + 1]),
                        'next_provider' => !isset($keys[$keyIndex + 1]) ? ($chain[$index + 1] ?? 'None') : 'None (trying next key)'
                    ]);
                }
            }
        }

        // Restore all original configs on complete failure
        config(['ai.default' => $originalDefaultProvider]);
        foreach ($originalProviderKeys as $p => $k) {
            config(["ai.providers.{$p}.key" => $k]);
        }

        // If we reached here, all keys across all providers failed
        throw $lastException ?: new \Exception("All AI providers and keys in the fallback chain failed.");
    }
}
