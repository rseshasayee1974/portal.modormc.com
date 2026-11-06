<?php
/*
Author: ragul-onemodo
Created: 2026-11-05 15:15:00 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\SalesOrders;

use App\Mail\SalesOrderCreatedMail;
use App\Models\NotificationEmail;
use App\Models\SalesOrder;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SalesOrderEmails
{
    private const TABLE = 'mm_sales_order_email_deliveries';

    private const RETRY_MINUTES = [1, 5, 15, 60];

    public function record(SalesOrder $order): void
    {
        $db = $order->getConnection();
        if (!$order->plant_id || !$db->getSchemaBuilder()->hasTable(self::TABLE)) {
            return;
        }

        $now = now('UTC');
        $rows = $this->recipients($db, $order->plant_id)->map(fn (string $email) => [
            'sales_order_id' => $order->getKey(),
            'recipient' => $email,
            'available_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            $db->table(self::TABLE)->upsert($rows, ['sales_order_id', 'recipient'], ['sales_order_id']);
            $orderId = $order->getKey();
            $db->afterCommit(function () use ($db, $orderId) {
                try {
                    $ids = $db->table(self::TABLE)->where('sales_order_id', $orderId)
                        ->where('status', 'pending')->pluck('id');
                    foreach ($ids as $id) {
                        $db->transaction(fn () => $this->send($db, $id));
                    }
                } catch (\Throwable $exception) {
                    // The order is committed; a delivery error must not make creation appear to fail.
                    Log::error('Immediate sales order email delivery failed.', [
                        'sales_order_id' => $orderId, 'exception_type' => $exception::class,
                    ]);
                }
            });
        }
    }

    public function resend(SalesOrder $order): array
    {
        $db = $order->getConnection();
        if (!$db->getSchemaBuilder()->hasTable(self::TABLE)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Email delivery tracking is not configured. Run the sales order email migration.']);
        }
        $recipients = $this->recipients($db, $order->plant_id);
        if ($recipients->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Configure an active Sales Order notification email for this plant.']);
        }
        $db->transaction(function () use ($db, $order, $recipients) {
            SalesOrder::on($db->getName())->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $db->table(self::TABLE)->where('sales_order_id', $order->id)->whereIn('recipient', $recipients)
                ->whereIn('status', ['pending', 'failed', 'cancelled'])
                ->update(['status' => 'pending', 'attempts' => 0, 'available_at' => now('UTC'), 'sent_at' => null, 'updated_at' => now('UTC')]);
            // Add newly configured recipients and send after the transaction commits.
            $this->record($order);
        });
        $statuses = $db->table(self::TABLE)->where('sales_order_id', $order->id)
            ->whereIn('recipient', $recipients)->pluck('status');
        return ['sent' => $statuses->filter(fn ($status) => $status === 'sent')->count(),
            'failed' => $statuses->filter(fn ($status) => $status === 'failed')->count(),
            'pending' => $statuses->filter(fn ($status) => $status === 'pending')->count()];
    }

    public function sendPending(): void
    {
        $db = DB::connection();
        if (!$db->getSchemaBuilder()->hasTable(self::TABLE)) {
            return;
        }

        $stopAt = microtime(true) + 45;
        $ids = $db->table(self::TABLE)->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', count(self::RETRY_MINUTES) + 1)
            ->where('available_at', '<=', now('UTC'))->orderBy('available_at')->orderBy('id')->limit(100)->pluck('id');

        foreach ($ids as $id) {
            if (microtime(true) >= $stopAt) {
                break;
            }
            $db->transaction(fn () => $this->send($db, $id));
        }
    }

    private function send(Connection $db, int $id): void
    {
        $deliveries = $db->table(self::TABLE)->where('id', $id);
        $delivery = (clone $deliveries)->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', count(self::RETRY_MINUTES) + 1)
            ->where('available_at', '<=', now('UTC'))->lockForUpdate()->first();
        if (!$delivery) {
            return;
        }

        $order = SalesOrder::on($db->getName())->withoutGlobalScope('plant_id')->find($delivery->sales_order_id);
        if (!$order || !$this->recipients($db, $order->plant_id)->containsStrict($delivery->recipient)) {
            $deliveries->update(['status' => 'cancelled', 'updated_at' => now('UTC')]);
            return;
        }

        $attempts = $delivery->attempts + 1;
        try {
            $forPlant = fn ($query) => $query->where('plant_id', $order->plant_id);
            $order->load(['plant', 'customer' => $forPlant, 'site' => $forPlant, 'mixDesign' => $forPlant]);
            if (!Mail::to($delivery->recipient)->send(new SalesOrderCreatedMail($order))) {
                throw new \RuntimeException('Mail transport did not accept the message.');
            }
        } catch (\Throwable $exception) {
            $delay = self::RETRY_MINUTES[$attempts - 1] ?? null;
            $deliveries->update([
                'attempts' => $attempts,
                'status' => 'failed',
                'sent_at' => null,
                'available_at' => now('UTC')->addMinutes($delay ?? 0),
                'updated_at' => now('UTC'),
            ]);
            Log::log($delay === null ? 'error' : 'warning', 'Sales order email delivery failed.', [
                'delivery_id' => $id, 'sales_order_id' => $delivery->sales_order_id,
                'attempts' => $attempts, 'exception_type' => $exception::class,
            ]);
            return;
        }

        $deliveries->update(['status' => 'sent', 'attempts' => $attempts, 'sent_at' => now('UTC'), 'updated_at' => now('UTC')]);
    }

    private function recipients(Connection $db, int $plantId): Collection
    {
        return (new NotificationEmail)->setConnection($db->getName())->newQuery()
            ->where('plant_id', $plantId)->where('status', 1)
            ->whereRaw('LOWER(TRIM(type)) = ?', [strtolower(NotificationEmail::SALES_ORDER)])
            ->get(['email'])
            ->map(fn ($row) => strtolower(trim($row->email)))
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->values();
    }
}
