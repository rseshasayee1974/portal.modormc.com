<?php
/**
 * Standalone AI Agent Fallback and Key Rotation Tester
 * Supports both CLI and Web Browser execution.
 */

// 1. Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 2. Identify Environment
$isCli = (php_sapi_name() === 'cli');

// 3. Define the Test Function with Detailed Log Output
function runAgentTest(string $testPrompt, $isCli) {
    $originalDefaultProvider = config('ai.default');
    $chain = config('ai.chain', ['gemini', 'openai']);
    
    // Remove duplicates and ensure the original default provider (if valid) is tried first
    if (($key = array_search($originalDefaultProvider, $chain)) !== false) {
        unset($chain[$key]);
    }
    array_unshift($chain, $originalDefaultProvider);
    $chain = array_values(array_filter(array_unique($chain)));

    $outputLog = [];
    $log = function($msg, $type = 'info') use ($isCli, &$outputLog) {
        $timestamp = date('H:i:s');
        $outputLog[] = ['time' => $timestamp, 'type' => $type, 'message' => $msg];
        if ($isCli) {
            $colors = [
                'info' => "\033[36m",    // Cyan
                'success' => "\033[32m", // Green
                'warning' => "\033[33m", // Yellow
                'error' => "\033[31m",   // Red
                'reset' => "\033[0m"
            ];
            echo "{$colors['info']}[{$timestamp}]{$colors['reset']} " .
                 ($type !== 'info' ? "{$colors[$type]}" . strtoupper($type) . ":{$colors['reset']} " : "") .
                 "{$msg}\n";
        }
    };

    $log("Initializing AI Agent Test...");
    $log("Prompt: \"{$testPrompt}\"");
    $log("Fallback Chain Configured: " . implode(' -> ', $chain));
    $log("Default Provider: {$originalDefaultProvider}");

    // Instantiate Agent
    try {
        $agent = new \App\Ai\Agents\Onemodo();
        $log("Successfully instantiated App\\Ai\\Agents\\Onemodo agent.", 'success');
    } catch (\Exception $e) {
        $log("Failed to instantiate Onemodo agent: " . $e->getMessage(), 'error');
        return ['success' => false, 'logs' => $outputLog, 'error' => $e->getMessage()];
    }

    $originalProviderKeys = [];
    foreach ($chain as $provider) {
        $provider = trim($provider);
        $originalProviderKeys[$provider] = config("ai.providers.{$provider}.key");
    }

    $lastException = null;
    $success = false;
    $reply = null;
    $successfulProvider = null;
    $successfulKeyIndex = null;

    foreach ($chain as $index => $provider) {
        $provider = trim($provider);
        if (empty($provider)) continue;

        $keyString = $originalProviderKeys[$provider] ?? '';
        
        if (empty($keyString) && $provider !== 'ollama') {
            $log("Skipping provider [{$provider}] - No API key configured in config/ai.php or .env.", 'warning');
            continue;
        }

        // Split comma-separated multiple keys
        $keys = array_values(array_filter(array_map('trim', explode(',', $keyString))));
        if (empty($keys) && $provider !== 'ollama') {
            $log("Skipping provider [{$provider}] - Keys array is empty.", 'warning');
            continue;
        }

        if (empty($keys) && $provider === 'ollama') {
            $keys = [''];
        }

        $log("Starting attempts for provider [{$provider}] (" . count($keys) . " keys found)...");

        foreach ($keys as $keyIndex => $singleKey) {
            $maskedKey = $provider === 'ollama' ? 'None (Ollama)' : substr($singleKey, 0, 8) . '...' . substr($singleKey, -4);
            $log("Attempting [{$provider}] Key Index [{$keyIndex}] ({$maskedKey})");

            try {
                // Temporarily override config values
                config(['ai.default' => $provider]);
                if ($provider !== 'ollama') {
                    config(["ai.providers.{$provider}.key" => $singleKey]);
                }

                // Execute the prompt
                $response = $agent->prompt($testPrompt);

                $reply = $response instanceof \Laravel\Ai\Responses\StructuredAgentResponse 
                    ? json_encode($response->structured, JSON_PRETTY_PRINT) 
                    : $response->text;

                $success = true;
                $successfulProvider = $provider;
                $successfulKeyIndex = $keyIndex;
                $log("Success using [{$provider}] (Key Index: {$keyIndex})!", 'success');
                break 2; // Break out of both loops
            } catch (\Exception $e) {
                $lastException = $e;
                $log("Provider [{$provider}] key index [{$keyIndex}] failed. Error: " . $e->getMessage(), 'error');
            }
        }
    }

    // Restore all original configs
    config(['ai.default' => $originalDefaultProvider]);
    foreach ($originalProviderKeys as $p => $k) {
        config(["ai.providers.{$p}.key" => $k]);
    }

    return [
        'success' => $success,
        'logs' => $outputLog,
        'reply' => $reply,
        'provider' => $successfulProvider,
        'keyIndex' => $successfulKeyIndex,
        'error' => $success ? null : ($lastException ? $lastException->getMessage() : 'All providers failed.')
    ];
}

// 4. CLI Execution Mode
if ($isCli) {
    $prompt = $argv[1] ?? 'Hello, Onemodo agent! Who are you?';
    $result = runAgentTest($prompt, true);
    echo "\n==================================================\n";
    if ($result['success']) {
        echo "\033[32mTEST PASSED SUCCESSFULLY!\033[0m\n";
        echo "Resolved Provider: " . $result['provider'] . " (Key index: " . $result['keyIndex'] . ")\n";
        echo "Response:\n" . $result['reply'] . "\n";
    } else {
        echo "\033[31mTEST FAILED!\033[0m\n";
        echo "Error: " . $result['error'] . "\n";
    }
    echo "==================================================\n";
    exit($result['success'] ? 0 : 1);
}

// 5. Browser/Web Execution Mode
$prompt = $_POST['prompt'] ?? 'Hello, Onemodo! Briefly tell me what RMC details you can look up.';
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = runAgentTest($prompt, false);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Agent Fallback & Key Rotation Tester</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #020617 100%);
            --glass-bg: rgba(30, 41, 59, 0.45);
            --glass-border: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --primary-accent: #6366f1;
            --success-color: #10b981;
            --error-color: #ef4444;
            --warning-color: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--bg-gradient);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem 1rem;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 900px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            backdrop-filter: blur(16px);
            padding: 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        h1 {
            font-size: 2.25rem;
            font-weight: 700;
            background: linear-gradient(to right, #a5b4fc, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 1rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2.5rem;
        }

        label {
            font-weight: 500;
            color: #cbd5e1;
            font-size: 0.95rem;
        }

        textarea {
            width: 100%;
            min-height: 100px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: 14px;
            padding: 1rem;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 1rem;
            resize: vertical;
            transition: all 0.3s ease;
        }

        textarea:focus {
            outline: none;
            border-color: var(--primary-accent);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        button {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 14px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        button:active {
            transform: translateY(0);
        }

        .result-section {
            display: flex;
            flex-direction: column;
            gap: 2rem;
            border-top: 1px solid var(--glass-border);
            padding-top: 2rem;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 0.75rem;
        }

        .log-box {
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.25rem;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.9rem;
            max-height: 350px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .log-line {
            display: flex;
            gap: 0.75rem;
            line-height: 1.4;
        }

        .log-time {
            color: var(--text-secondary);
            flex-shrink: 0;
        }

        .log-badge {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            padding: 0.1rem 0.4rem;
            border-radius: 4px;
            flex-shrink: 0;
            display: inline-block;
        }

        .badge-info { background: rgba(99, 102, 241, 0.2); color: #a5b4fc; }
        .badge-success { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }
        .badge-warning { background: rgba(245, 158, 11, 0.2); color: #fde047; }
        .badge-error { background: rgba(239, 68, 68, 0.2); color: #fca5a5; }

        .log-text {
            color: #e2e8f0;
        }

        .response-box {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .status-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .status-dot.success { background: var(--success-color); box-shadow: 0 0 12px var(--success-color); }
        .status-dot.failed { background: var(--error-color); box-shadow: 0 0 12px var(--error-color); }

        .status-title {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .response-content {
            background: rgba(15, 23, 42, 0.4);
            border-radius: 10px;
            padding: 1rem;
            white-space: pre-wrap;
            font-size: 0.95rem;
            line-height: 1.6;
            color: #e2e8f0;
            border-left: 4px solid var(--primary-accent);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>AI Agent Tester</h1>
        <p class="subtitle">Validate multi-key rotation and provider fallbacks on portal.modormc.com</p>

        <form method="POST">
            <label for="prompt">Test Prompt</label>
            <textarea id="prompt" name="prompt" required><?php echo htmlspecialchars($prompt); ?></textarea>
            <button type="submit">Run Test Execution</button>
        </form>

        <?php if ($result !== null): ?>
            <div class="result-section">
                <div>
                    <h2 class="section-title">Execution Log Trace</h2>
                    <div class="log-box">
                        <?php foreach ($result['logs'] as $logItem): ?>
                            <div class="log-line">
                                <span class="log-time">[<?php echo htmlspecialchars($logItem['time']); ?>]</span>
                                <?php if ($logItem['type'] !== 'info'): ?>
                                    <span class="log-badge badge-<?php echo htmlspecialchars($logItem['type']); ?>">
                                        <?php echo htmlspecialchars($logItem['type']); ?>
                                    </span>
                                <?php endif; ?>
                                <span class="log-text"><?php echo htmlspecialchars($logItem['message']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="response-box">
                    <div class="status-header">
                        <div class="status-dot <?php echo $result['success'] ? 'success' : 'failed'; ?>"></div>
                        <div class="status-title">
                            <?php if ($result['success']): ?>
                                Success: Prompts resolved via <strong><?php echo htmlspecialchars($result['provider']); ?></strong> (Key Index: <?php echo htmlspecialchars($result['keyIndex']); ?>)
                            <?php else: ?>
                                Failed: All configured fallbacks and keys failed.
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <h2 class="section-title" style="font-size: 1.1rem; margin-top: 1rem;">Response Content</h2>
                    <div class="response-content"><?php 
                        if ($result['success']) {
                            echo htmlspecialchars($result['reply']);
                        } else {
                            echo '<span style="color: var(--error-color);">' . htmlspecialchars($result['error']) . '</span>';
                        }
                    ?></div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
