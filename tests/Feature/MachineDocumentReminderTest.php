<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:25:01 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Mail\MachineDocumentExpiryMail;
use App\Models\{Machine, MachineDocument, NotificationEmail, Plant};
use App\Services\Logistics\MachineDocumentReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{Cache, DB, Mail};

class MachineDocumentReminderTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;
    private Machine $machine;
    private MachineDocumentReminderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 09:00:00', 'Asia/Kolkata'));
        config(['machine_notifications.enabled' => true, 'cache.default' => 'array']);
        Mail::fake();
        $this->plant = Plant::factory()->create(['is_active' => 1]);
        $this->plant->entity->update(['time_zone' => 'Asia/Kolkata', 'is_active' => 1, 'is_suspended' => 0]);
        $this->plant->unsetRelation('entity');
        $this->machine = Machine::factory()->create(['plant_id' => $this->plant->id, 'is_active' => true]);
        $this->service = app(MachineDocumentReminderService::class);
        $this->recipient('fleet@example.test');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function recipient(string $email, array $extra = []): NotificationEmail
    {
        return NotificationEmail::create(array_merge([
            'plant_id' => $this->plant->id, 'type' => 'Machine Document Expiry',
            'role_name' => 'Fleet Manager', 'email' => $email, 'status' => 1,
        ], $extra));
    }

    private function document(int $days, string $type = 'fc', ?Machine $machine = null): MachineDocument
    {
        return MachineDocument::create([
            'machine_id' => ($machine ?? $this->machine)->id, 'type' => $type,
            'expiry_date' => CarbonImmutable::now('Asia/Kolkata')->addDays($days)->toDateString(),
        ]);
    }

    public function test_all_machine_document_types_are_in_one_private_plant_digest(): void
    {
        foreach (['fc', 'permit', 'road_tax', 'insurance', 'puc', 'national_permit', 'other'] as $type) $this->document(7, $type);
        $result = $this->service->sendForPlant($this->plant);
        $this->assertSame(7, $result['documents_due']);
        $this->assertSame(1, $result['emails_sent']);
        Mail::assertSent(MachineDocumentExpiryMail::class, fn ($mail) => $mail->hasTo('fleet@example.test')
            && count($mail->documents) === 7 && $mail->plantName === $this->plant->name);
        $this->assertDatabaseCount('mm_machine_document_alerts', 7);
    }

    public function test_milestones_and_weekly_overdue_reminders_are_not_repeated(): void
    {
        $this->document(30);
        $base = CarbonImmutable::now('Asia/Kolkata');
        foreach ([0, 1, 15, 23, 29, 30, 31, 36, 37, 38, 43, 44] as $offset) {
            CarbonImmutable::setTestNow($base->addDays($offset));
            $this->service->sendForPlant($this->plant);
        }
        Mail::assertSent(MachineDocumentExpiryMail::class, 7);
        $this->assertDatabaseCount('mm_machine_document_alerts', 7);
        $this->assertSame(['before_30', 'before_15', 'before_7', 'before_1', 'expiry', 'overdue_1', 'overdue_2'],
            DB::table('mm_machine_document_alerts')->orderBy('id')->pluck('reminder_key')->all());
    }

    public function test_newly_recorded_already_expired_documents_are_reported_once(): void
    {
        $this->document(-3);
        $this->service->sendForPlant($this->plant);
        $this->service->sendForPlant($this->plant);
        Mail::assertSent(MachineDocumentExpiryMail::class, 1);
        Mail::assertSent(MachineDocumentExpiryMail::class, fn ($mail) => $mail->documents[0]['status'] === 'Expired 3 day(s) ago');
    }

    public function test_missed_threshold_is_caught_up_and_renewal_starts_new_reminders(): void
    {
        $doc = $this->document(20);
        $this->service->sendForPlant($this->plant);
        $this->service->sendForPlant($this->plant);
        Mail::assertSent(MachineDocumentExpiryMail::class, 1);
        $doc->update(['expiry_date' => CarbonImmutable::now('Asia/Kolkata')->addDays(30)->toDateString()]);
        $this->service->sendForPlant($this->plant);
        Mail::assertSent(MachineDocumentExpiryMail::class, 2);
        $this->assertDatabaseCount('mm_machine_document_alerts', 2);
    }

    public function test_far_future_missing_dates_and_deleted_or_inactive_machines_are_excluded(): void
    {
        $this->document(31);
        MachineDocument::create(['machine_id' => $this->machine->id, 'type' => 'permit', 'expiry_date' => null]);
        $this->document(7, 'insurance')->delete();
        $inactive = Machine::factory()->create(['plant_id' => $this->plant->id, 'is_active' => false]);
        $this->document(7, 'fc', $inactive);
        $deleted = Machine::factory()->create(['plant_id' => $this->plant->id, 'is_active' => true]);
        $this->document(7, 'permit', $deleted);
        $deleted->delete();
        $result = $this->service->sendForPlant($this->plant);
        $this->assertSame(0, $result['documents_due']);
        Mail::assertNothingSent();
    }

    public function test_only_active_valid_configured_recipients_receive_mail_and_duplicates_are_collapsed(): void
    {
        $this->document(7);
        $this->recipient('FLEET@example.test');
        $this->recipient('other@example.test', ['status' => 0]);
        $this->recipient('deleted@example.test')->delete();
        $this->recipient('quotation@example.test', ['type' => 'Quotation']);
        $this->recipient('invalid-address');
        $result = $this->service->sendForPlant($this->plant);
        $this->assertSame(1, $result['recipients']);
        Mail::assertSent(MachineDocumentExpiryMail::class, 1);
    }

    public function test_no_configured_recipient_does_not_fall_back_to_unrequested_addresses(): void
    {
        NotificationEmail::query()->delete();
        $this->document(7);
        $this->assertSame(0, $this->service->sendForPlant($this->plant)['emails_sent']);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('mm_machine_document_alerts', 0);
    }

    public function test_plants_and_recipients_remain_isolated_even_with_another_active_session(): void
    {
        $otherPlant = Plant::factory()->create(['is_active' => 1]);
        $otherPlant->entity->update(['is_active' => 1, 'is_suspended' => 0]);
        $otherMachine = Machine::factory()->create(['plant_id' => $otherPlant->id, 'is_active' => true]);
        $this->recipient('other-plant@example.test', ['plant_id' => $otherPlant->id]);
        $this->document(7);
        $this->document(7, 'fc', $otherMachine);
        session(['active_plant_id' => $otherPlant->id]);
        $this->service->sendForPlant($this->plant);
        Mail::assertSent(MachineDocumentExpiryMail::class, fn ($mail) => $mail->hasTo('fleet@example.test')
            && count($mail->documents) === 1 && $mail->documents[0]['registration'] === $this->machine->registration);
        Mail::assertNotSent(MachineDocumentExpiryMail::class, fn ($mail) => $mail->hasTo('other-plant@example.test'));
    }

    public function test_mail_failures_do_not_record_success_and_can_be_retried(): void
    {
        $this->document(7);
        $mailFake = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Simulated mail failure'));
        $result = $this->service->sendForPlant($this->plant);
        $this->assertSame(1, $result['errors']);
        $this->assertDatabaseCount('mm_machine_document_alerts', 0);
        Mail::swap($mailFake);
        $this->assertSame(1, $this->service->sendForPlant($this->plant)['emails_sent']);
        Mail::assertSent(MachineDocumentExpiryMail::class, 1);
    }

    public function test_scheduled_checks_use_entity_local_hour_and_expiry_date(): void
    {
        $this->plant->entity->update(['time_zone' => 'America/New_York']);
        $this->plant->unsetRelation('entity');
        $this->document(7);
        $this->assertTrue($this->service->sendForPlant($this->plant, scheduled: true)['skipped']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 13:00:00', 'UTC'));
        $result = $this->service->sendForPlant($this->plant, scheduled: true);
        $this->assertSame(1, $result['emails_sent']);
        Mail::assertSent(MachineDocumentExpiryMail::class, fn ($mail) => $mail->checkedDate === '01-Oct-2026');
    }

    public function test_plant_lock_prevents_overlapping_sends(): void
    {
        $this->document(7);
        $lock = Cache::lock('machine-document-reminders:plant:'.$this->plant->id, 60);
        $lock->get();
        try {
            $this->assertTrue($this->service->sendForPlant($this->plant)['skipped']);
            Mail::assertNothingSent();
        } finally {
            $lock->release();
        }
    }

    public function test_dry_run_command_does_not_send_or_write_delivery_records(): void
    {
        $this->document(7);
        $this->artisan('fleet:notify-document-expiry', ['--plant' => $this->plant->id, '--dry-run' => true])
            ->expectsOutputToContain('Dry run only')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertDatabaseCount('mm_machine_document_alerts', 0);
    }

    public function test_disabled_configuration_and_suspended_or_inactive_plants_are_skipped(): void
    {
        $this->document(7);
        config(['machine_notifications.enabled' => false]);
        $this->assertTrue($this->service->sendForPlant($this->plant)['skipped']);
        config(['machine_notifications.enabled' => true]);
        $this->plant->entity->update(['is_suspended' => 1]);
        $this->assertTrue($this->service->sendForPlant($this->plant)['skipped']);
        $this->plant->entity->update(['is_suspended' => 0]);
        $this->plant->update(['is_active' => 0]);
        $this->assertTrue($this->service->sendForPlant($this->plant)['skipped']);
        Mail::assertNothingSent();
    }

    public function test_mail_template_contains_machine_validity_and_status(): void
    {
        $this->document(7);
        $this->service->sendForPlant($this->plant);
        Mail::assertSent(MachineDocumentExpiryMail::class, function ($mail) {
            $html = $mail->render();
            $this->assertStringContainsString($this->machine->registration, $html);
            $this->assertStringContainsString('FC / Fitness Certificate', $html);
            $this->assertStringContainsString('Expires in 7 day(s)', $html);
            $this->assertStringContainsString('08-Oct-2026', $html);
            return true;
        });
    }
}
