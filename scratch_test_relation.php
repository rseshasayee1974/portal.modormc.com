<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$mix = App\Models\MixDesign::with('concrete_grade')->find(2);
echo "MIX DESIGN:\n";
print_r($mix->toArray());
