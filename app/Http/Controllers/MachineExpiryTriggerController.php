<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 10:59:11 Asia/Calcutta (UTC+05:30)
*/
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class MachineExpiryTriggerController extends Controller
{
    public function __invoke(Request $request)
    {
        $expected = (string) config('machine_notifications.trigger_token', '');
        if ($expected === '') return response()->json(['message' => 'Expiry notification trigger is not configured.'], 503)->header('Cache-Control', 'no-store');
        $provided = $request->bearerToken() ?? $request->query('MiniMeals');
        if (!is_string($provided) || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Invalid notification token.'], 403)->header('Cache-Control', 'no-store');
        }
        $validator = validator($request->only('plant', 'dry_run', 'scheduled'), [
            'plant' => ['nullable', 'integer', 'min:1'],
            'dry_run' => ['nullable', 'boolean'],
            'scheduled' => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422)->header('Cache-Control', 'no-store');
        $options = ['--dry-run' => $request->boolean('dry_run'), '--scheduled' => $request->boolean('scheduled')];
        if ($request->filled('plant')) $options['--plant'] = (int) $request->input('plant');
        $exitCode = Artisan::call('fleet:notify-document-expiry', $options);
        return response()->json([
            'success' => $exitCode === 0, 'dry_run' => $request->boolean('dry_run'),
            'output' => trim(Artisan::output()),
        ], $exitCode === 0 ? 200 : 500)->header('Cache-Control', 'no-store');
    }
}
