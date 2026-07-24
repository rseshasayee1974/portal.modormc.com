<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Menu;

$m = Menu::find(79);
if ($m) {
    echo "ID: {$m->id}, Title: {$m->title}, Alias: {$m->alias}, Link: {$m->link}, Perm: {$m->permission_name}, Published: {$m->published}\n";
} else {
    echo "Menu 79 not found!\n";
}
