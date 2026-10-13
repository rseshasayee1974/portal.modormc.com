<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 10:54:58 Asia/Calcutta (UTC+05:30)
*/

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Mobile\MobileAccess;
use Illuminate\Console\Command;

class ErpMcpToken extends Command
{
    protected $signature = 'mcp:erp-token {email : Registered user email} {--days=30 : Expiry in days (1 to 30)} {--revoke : Revoke this user\'s ERP MCP tokens}';
    protected $description = 'Issue or revoke a user-scoped ERP MCP bearer token; run only as a trusted server administrator.';

    public function handle(): int
    {
        $user = User::where('email', strtolower(trim($this->argument('email'))))->first();
        if (!$user) { $this->error('User not found.'); return self::FAILURE; }
        if ($this->option('revoke')) {
            $user->tokens()->where('name','like','erp-mcp:%')->delete();
            $this->info('ERP MCP tokens revoked.'); return self::SUCCESS;
        }
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT);
        if (!$days || $days < 1 || $days > 30 || !$user->is_active || ($user->lockout_until && $user->lockout_until->isFuture()) || !MobileAccess::plants($user)->exists()) {
            $this->error('User must be active, unlocked and assigned an active plant; expiry must be 1 to 30 days.'); return self::FAILURE;
        }
        $token = $user->createToken('erp-mcp:assistant', ['erp-mcp'], now()->addDays($days));
        $this->warn('Copy this token to the MCP client secret configuration. It is shown once.');
        $this->line($token->plainTextToken);
        return self::SUCCESS;
    }
}
