<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PrintTemplateSetting;
use Illuminate\Support\Facades\DB;

header('Content-Type: text/plain');

$settings = PrintTemplateSetting::with('template')->get();
foreach ($settings as $setting) {
    echo "Plant: {$setting->plant_id} | Module: {$setting->module_key} | Template: " . ($setting->template->key ?? 'N/A') . "\n";
}
