<?php

/*
 * Author: Cipher
 * Created: 2026-11-10 10:11:37 Asia/Calcutta (UTC+05:30)
 */

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AccountDeletionRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_email' => ['required', 'email:rfc', 'max:255'],
            'account_identifier' => ['nullable', 'string', 'max:255'],
            'additional_details' => ['nullable', 'string', 'max:2000'],
            'confirm_deletion' => ['accepted'],
        ]);

        DB::table('account_deletion_requests')->insert([
            'app_name' => 'MODORMC',
            'account_email' => $validated['account_email'],
            'account_identifier' => $validated['account_identifier'] ?? null,
            'additional_details' => $validated['additional_details'] ?? null,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            Mail::raw(
                "A MODORMC account deletion request was submitted.\n\nAccount email: {$validated['account_email']}\nAccount identifier: ".($validated['account_identifier'] ?? 'Not provided')."\n\nAdditional details:\n".($validated['additional_details'] ?? 'None'),
                function ($message): void {
                    $message->to('sayee@onemodo.com')->subject('MODORMC account deletion request');
                }
            );
        } catch (\Throwable $exception) {
            Log::error('Unable to email MODORMC account deletion request.', [
                'account_email' => $validated['account_email'],
                'exception' => $exception,
            ]);
        }

        return back()->with('deletion_request_submitted', true);
    }
}
