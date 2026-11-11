<?php

/*
 * Author: Cipher
 * Created: 2026-11-11 12:40:19 Asia/Calcutta (UTC+05:30)
 */

namespace App\Http\Controllers;

use App\Mail\SupportRequestReceived;
use App\Models\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SupportRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'issue_type' => ['required', 'in:bug,technical_issue,usage_difficulty,account,other'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'app_version' => ['nullable', 'string', 'max:80'],
            'device_details' => ['nullable', 'string', 'max:160'],
            'steps_to_reproduce' => ['nullable', 'string', 'max:3000'],
        ]);

        $supportRequest = SupportRequest::create([
            ...$validated,
            'status' => 'new',
        ]);

        try {
            Mail::to('noreply@modormc.com')->send(new SupportRequestReceived($supportRequest));
            $supportRequest->forceFill(['notification_sent_at' => now()])->save();
        } catch (\Throwable $exception) {
            Log::error('Unable to send public mobile app support request.', [
                'support_request_id' => $supportRequest->id,
                'email' => $validated['email'],
                'exception' => $exception,
            ]);

            $supportRequest->forceFill(['status' => 'notification_failed'])->save();

            return back()->with('support_request_submitted', true)
                ->with('support_request_notification_error', true);
        }

        return back()->with('support_request_submitted', true);
    }
}
