<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\InventoryAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

class InventoryAuditLogTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Skip if running sqlite and fulltext indexes block database migrations
        if (isset($_ENV['DB_CONNECTION']) && $_ENV['DB_CONNECTION'] === 'sqlite') {
            $this->markTestSkipped('Database migrations contain SQLite-incompatible MySQL features (fulltext indexes).');
        }

        parent::setUp();
    }

    /**
     * Test that creating an audited model does NOT trigger a CREATE log.
     */
    public function test_creating_model_does_not_trigger_create_audit_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create([
            'title' => 'Initial Product Title',
            'sales_price' => 100.00,
        ]);

        $log = InventoryAuditLog::where('transaction_type', 'CREATE')
            ->where('reference_type', 'Product')
            ->where('reference_id', $product->id)
            ->first();

        $this->assertNull($log);
    }

    /**
     * Test that updating an audited model triggers an UPDATE log with only dirty fields.
     */
    public function test_updating_model_triggers_audit_log_with_only_changed_fields(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create([
            'title' => 'Old Title',
            'sales_price' => 100.00,
            'purchase_price' => 80.00,
        ]);

        // Clear previous CREATE log to isolate update check
        InventoryAuditLog::truncate();

        // Perform update
        $product->update([
            'title' => 'New Title',
            'sales_price' => 120.00,
            // purchase_price remains unchanged
        ]);

        $log = InventoryAuditLog::where('transaction_type', 'UPDATE')
            ->where('reference_type', 'Product')
            ->where('reference_id', $product->id)
            ->first();

        $this->assertNotNull($log);
        
        // Assert remarks prefix
        $this->assertStringStartsWith('Updated: ', $log->remarks);
        $this->assertStringContainsString('title: \'Old Title\' => \'New Title\'', $log->remarks);
        $this->assertStringContainsString('sales_price: \'100\' => \'120\'', $log->remarks);
        $this->assertStringNotContainsString('purchase_price', $log->remarks);

        // Assert log_from / log_to payloads only contain modified fields
        $logFrom = json_decode($log->log_from, true);
        $logTo = json_decode($log->log_to, true);

        $this->assertEquals(['title' => 'Old Title', 'sales_price' => '100'], $logFrom);
        $this->assertEquals(['title' => 'New Title', 'sales_price' => '120'], $logTo);
    }

    /**
     * Test that deleting an audited model triggers a DELETE log.
     */
    public function test_deleting_model_triggers_audit_log(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $product = Product::factory()->create([
            'title' => 'Product to Delete',
        ]);

        $product->delete();

        $log = InventoryAuditLog::where('transaction_type', 'DELETE')
            ->where('reference_type', 'Product')
            ->where('reference_id', $product->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringStartsWith('Deleted: Product:', $log->remarks);

        $logFrom = json_decode($log->log_from, true);
        $this->assertEquals('Product to Delete', $logFrom['title'] ?? null);
    }
}
