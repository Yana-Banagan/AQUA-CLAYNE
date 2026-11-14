<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerContainer;
use App\Models\CustomerContainerRfidTag;
use App\Models\Order;
use App\Models\RfidDevice;
use App\Models\RfidRegistrationSession;
use App\Models\RfidTransaction;
use App\Models\User;
use App\Services\RfidDeviceService;
use App\Services\RfidRegistrationService;
use App\Services\RfidTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfidGallonTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $customerUser;
    private Customer $customer;
    private RfidDevice $device;
    private string $deviceToken = 'test_device_secret_token_12345';

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'name' => 'Station Manager',
            'email' => 'admin@aquaclayne.test',
        ]);

        $this->customerUser = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'name' => 'Juan Dela Cruz',
            'phone' => '09171234567',
        ]);

        $this->customer = Customer::create([
            'user_id' => $this->customerUser->id,
        ]);

        $this->device = RfidDevice::create([
            'device_code' => 'ESP32-TEST-01',
            'device_name' => 'Test RFID Scanner',
            'location' => 'Test Refill Bay',
            'device_token_hash' => hash('sha256', $this->deviceToken),
            'status' => 'online',
            'firmware_version' => '1.0.0',
            'last_heartbeat_at' => now(),
        ]);
    }

    public function test_device_authentication_valid_token_succeeds(): void
    {
        $response = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/heartbeat', [
                'firmware_version' => '1.0.1',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'device' => [
                'code' => 'ESP32-TEST-01',
                'status' => 'online',
            ],
        ]);

        $this->device->refresh();
        $this->assertEquals('1.0.1', $this->device->firmware_version);
        $this->assertNotNull($this->device->last_heartbeat_at);
    }

    public function test_device_authentication_missing_token_returns_401(): void
    {
        $response = $this->postJson('/api/iot/rfid/heartbeat');
        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => 'UNAUTHORIZED',
        ]);
    }

    public function test_device_authentication_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('X-Device-Token', 'wrong_token')
            ->postJson('/api/iot/rfid/heartbeat');

        $response->assertStatus(401);
        $response->assertJson([
            'success' => false,
            'error' => 'INVALID_CREDENTIAL',
        ]);
    }

    public function test_admin_can_create_customer_container_and_start_registration_session(): void
    {
        $container = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-001',
            'container_type' => '5-Gallon Standard Slim',
            'capacity' => 5.00,
            'status' => 'active',
        ]);

        // Start session as admin
        $response = $this->actingAs($this->admin)
            ->postJson('/admin/rfid/registration-sessions', [
                'customer_id' => $this->customer->id,
                'customer_container_id' => $container->id,
                'station_device_id' => $this->device->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'session' => [
                'container_code' => 'AC-JUA-001',
                'customer_name' => 'Juan Dela Cruz',
            ],
        ]);

        $sessionId = $response->json('session.id');
        $this->assertDatabaseHas('rfid_registration_sessions', [
            'id' => $sessionId,
            'customer_container_id' => $container->id,
            'status' => 'pending',
        ]);

        // ESP32 checks for active session
        $deviceCheck = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->getJson('/api/iot/rfid/registration-session/active');

        $deviceCheck->assertStatus(200);
        $deviceCheck->assertJson([
            'success' => true,
            'has_session' => true,
            'session' => [
                'session_id' => $sessionId,
                'container_code' => 'AC-JUA-001',
                'customer_name' => 'Juan Dela Cruz',
            ],
        ]);
    }

    public function test_customer_search_lists_all_eligible_customers_without_duplicates(): void
    {
        CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-ELIG-01',
            'container_type' => '5-Gallon Round',
            'status' => 'active',
        ]);
        CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-ELIG-02',
            'container_type' => '5-Gallon Slim',
            'status' => 'active',
        ]);

        $taggedContainer = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-TAGGED',
            'status' => 'active',
        ]);
        CustomerContainerRfidTag::create([
            'customer_container_id' => $taggedContainer->id,
            'uid' => '01020304',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $ineligibleUser = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $ineligibleCustomer = Customer::create(['user_id' => $ineligibleUser->id]);
        CustomerContainer::create([
            'customer_id' => $ineligibleCustomer->id,
            'container_code' => 'AC-NO-TAG-01',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/admin/rfid/search-customers');

        $response->assertOk()->assertJsonCount(1, 'customers');
        $response->assertJsonPath('customers.0.id', $this->customer->id);

        $filtered = $this->actingAs($this->admin)->getJson('/admin/rfid/search-customers?q=09171234567');
        $filtered->assertOk()->assertJsonCount(1, 'customers');

        $containerOwnerSearch = $this->actingAs($this->admin)
            ->getJson('/admin/rfid/search-customers?include_all=1&q=' . urlencode($ineligibleUser->email));
        $containerOwnerSearch->assertOk()->assertJsonPath('customers.0.id', $ineligibleCustomer->id);
    }

    public function test_customer_container_list_only_returns_eligible_untagged_containers(): void
    {
        $eligible = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-READY',
            'container_type' => '5-Gallon Standard Slim',
            'status' => 'active',
        ]);
        $tagged = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-USED',
            'status' => 'active',
        ]);
        CustomerContainerRfidTag::create([
            'customer_container_id' => $tagged->id,
            'uid' => 'A1B2C3D4',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/admin/rfid/customers/{$this->customer->id}/containers");

        $response->assertOk()->assertJsonCount(1, 'containers');
        $response->assertJsonPath('containers.0.id', $eligible->id);
        $response->assertJsonPath('containers.0.status', 'Ready for RFID registration');
        $this->assertArrayNotHasKey('active_tag_uid', $response->json('containers.0'));
    }

    public function test_registration_cannot_start_with_an_offline_scanner(): void
    {
        $container = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-OFFLINE',
            'status' => 'active',
        ]);
        $this->device->update([
            'status' => 'offline',
            'last_heartbeat_at' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson('/admin/rfid/registration-sessions', [
                'customer_id' => $this->customer->id,
                'customer_container_id' => $container->id,
                'station_device_id' => $this->device->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('station_device_id');
        $this->assertDatabaseCount('rfid_registration_sessions', 0);
    }

    public function test_scanner_cannot_receive_multiple_concurrent_registration_sessions(): void
    {
        $container1 = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-BUSY-01',
            'status' => 'active',
        ]);
        CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-BUSY-02',
            'status' => 'active',
        ]);

        app(RfidRegistrationService::class)->createSession(
            $container1->id,
            $this->customer->id,
            $this->device->id,
            $this->admin
        );

        $container2 = CustomerContainer::where('container_code', 'AC-JUA-BUSY-02')->firstOrFail();
        $response = $this->actingAs($this->admin)
            ->postJson('/admin/rfid/registration-sessions', [
                'customer_id' => $this->customer->id,
                'customer_container_id' => $container2->id,
                'station_device_id' => $this->device->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('station_device_id');
        $this->assertDatabaseCount('rfid_registration_sessions', 1);
    }

    public function test_new_hardware_device_is_offline_until_it_sends_a_heartbeat(): void
    {
        $result = app(RfidDeviceService::class)->registerDevice([
            'device_code' => 'ESP32-NEW-01',
            'device_name' => 'New RFID Scanner',
            'location' => 'Bay 2',
        ], $this->admin);

        $this->assertFalse($result['device']->isOnline());
        $this->assertNull($result['device']->last_heartbeat_at);
    }

    public function test_esp32_submits_rfid_scan_and_completes_registration(): void
    {
        $container = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-002',
            'container_type' => '5-Gallon Round',
            'status' => 'active',
        ]);

        $session = RfidRegistrationSession::create([
            'customer_container_id' => $container->id,
            'station_device_id' => $this->device->id,
            'created_by' => $this->admin->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(3),
        ]);

        $this->withHeader('X-Device-Token', $this->deviceToken)
            ->getJson('/api/iot/rfid/registration-session/active')
            ->assertJsonPath('session.session_id', $session->id);

        $uid = 'E280116060000204';

        // ESP32 sends scan
        $scanResponse = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/registration-scan', [
                'device_id' => $this->device->id,
                'session_id' => $session->id,
                'uid' => $uid,
            ]);

        $scanResponse->assertStatus(200);
        $scanResponse->assertJson([
            'success' => true,
            'message' => 'RFID tag registered successfully.',
            'tag' => [
                'uid' => $uid,
            ],
        ]);

        // Verify database records
        $this->assertDatabaseHas('customer_container_rfid_tags', [
            'customer_container_id' => $container->id,
            'uid' => $uid,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('rfid_registration_sessions', [
            'id' => $session->id,
            'status' => 'completed',
            'scanned_uid' => $uid,
        ]);

        $this->assertDatabaseHas('rfid_transactions', [
            'customer_container_id' => $container->id,
            'device_id' => $this->device->id,
            'transaction_type' => 'REGISTRATION',
            'scan_result' => 'accepted',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->admin->id,
            'action' => 'rfid.tag_registered',
        ]);
    }

    public function test_duplicate_rfid_uid_is_rejected(): void
    {
        $container1 = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-003',
            'status' => 'active',
        ]);

        $existingUid = 'A1B2C3D4';
        CustomerContainerRfidTag::create([
            'customer_container_id' => $container1->id,
            'uid' => $existingUid,
            'status' => 'replaced',
            'registered_at' => now(),
        ]);

        $container2 = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-004',
            'status' => 'active',
        ]);

        $session = RfidRegistrationSession::create([
            'customer_container_id' => $container2->id,
            'station_device_id' => $this->device->id,
            'created_by' => $this->admin->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(3),
        ]);

        $this->withHeader('X-Device-Token', $this->deviceToken)
            ->getJson('/api/iot/rfid/registration-session/active')
            ->assertJsonPath('session.session_id', $session->id);

        // Attempt to register the same UID
        $response = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/registration-scan', [
                'device_id' => $this->device->id,
                'session_id' => $session->id,
                'uid' => $existingUid,
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'DUPLICATE_RFID_TAG',
        ]);

        // A rejected UID is auditable, but the live session remains available for another tap.
        $session->refresh();
        $this->assertEquals('pending', $session->status);
        $this->withHeader('X-Device-Token', $this->deviceToken)
            ->getJson('/api/iot/rfid/registration-session/active')
            ->assertJsonPath('session.session_id', $session->id);
        $this->assertDatabaseMissing('customer_container_rfid_tags', [
            'customer_container_id' => $container2->id,
        ]);
        $this->assertDatabaseHas('rfid_transactions', [
            'customer_container_id' => $container2->id,
            'transaction_type' => 'REGISTRATION',
            'scan_result' => 'rejected',
        ]);

        $validResponse = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/registration-scan', [
                'device_id' => $this->device->id,
                'session_id' => $session->id,
                'uid' => 'C1C2C3C4',
            ]);

        $validResponse->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('customer_container_rfid_tags', [
            'customer_container_id' => $container2->id,
            'uid' => 'C1C2C3C4',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('rfid_registration_sessions', [
            'id' => $session->id,
            'status' => 'completed',
        ]);
    }

    public function test_registration_rejects_malformed_physical_uid(): void
    {
        $container = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-BAD-UID',
            'status' => 'active',
        ]);
        $session = RfidRegistrationSession::create([
            'customer_container_id' => $container->id,
            'station_device_id' => $this->device->id,
            'created_by' => $this->admin->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(3),
        ]);
        $this->withHeader('X-Device-Token', $this->deviceToken)
            ->getJson('/api/iot/rfid/registration-session/active');

        $response = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/registration-scan', [
                'device_id' => $this->device->id,
                'session_id' => $session->id,
                'uid' => 'not-a-physical-uid',
            ]);

        $response->assertStatus(422)->assertJson([
            'success' => false,
            'error' => 'INVALID_UID',
        ]);
        $this->assertDatabaseHas('rfid_registration_sessions', [
            'id' => $session->id,
            'status' => 'pending',
            'failure_code' => 'INVALID_UID',
        ]);
    }

    public function test_registration_session_is_bound_to_the_selected_authenticated_device(): void
    {
        $container = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-DEVICE-BOUND',
            'status' => 'active',
        ]);
        $session = RfidRegistrationSession::create([
            'customer_container_id' => $container->id,
            'station_device_id' => $this->device->id,
            'created_by' => $this->admin->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(3),
        ]);
        $this->withHeader('X-Device-Token', $this->deviceToken)
            ->getJson('/api/iot/rfid/registration-session/active')
            ->assertJsonPath('session.session_id', $session->id);

        $otherToken = 'second_test_device_secret';
        $otherDevice = RfidDevice::create([
            'device_code' => 'ESP32-TEST-02',
            'device_name' => 'Second Test Scanner',
            'location' => 'Bay 2',
            'device_token_hash' => hash('sha256', $otherToken),
            'status' => 'online',
            'last_heartbeat_at' => now(),
        ]);

        $this->withHeader('X-Device-Token', $otherToken)
            ->getJson('/api/iot/rfid/registration-session/active')
            ->assertJsonPath('has_session', false);

        $scan = $this->withHeader('X-Device-Token', $otherToken)
            ->postJson('/api/iot/rfid/registration-scan', [
                'device_id' => $otherDevice->id,
                'session_id' => $session->id,
                'uid' => 'AABBCCDD',
            ]);

        $scan->assertStatus(422)->assertJson([
            'success' => false,
            'error' => 'DEVICE_MISMATCH',
        ]);
        $this->assertDatabaseHas('rfid_registration_sessions', [
            'id' => $session->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('customer_container_rfid_tags', 0);
    }

    public function test_verification_scan_returns_current_customer_name_and_active_gallon_count(): void
    {
        $container = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-VERIFY-01',
            'container_type' => '5-Gallon Round',
            'status' => 'active',
        ]);
        CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-VERIFY-02',
            'container_type' => '5-Gallon Slim',
            'status' => 'active',
        ]);
        CustomerContainerRfidTag::create([
            'customer_container_id' => $container->id,
            'uid' => 'B1B2B3B4',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $payload = [
                'uid' => 'B1B2B3B4',
                'scan_type' => 'VERIFICATION',
                'scan_id' => 'd1b61244-23c6-4f30-9b4a-0b3e4c85cf08',
            ];
        $response = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/scan', $payload);

        $response->assertOk()->assertJson([
            'success' => true,
            'customer_name' => 'Juan Dela Cruz',
            'gallon_count' => 2,
        ]);
        $repeatResponse = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/scan', $payload);
        $repeatResponse->assertOk()->assertJsonPath('transaction_id', $response->json('transaction_id'));
        $this->assertDatabaseCount('rfid_transactions', 1);
        $this->assertDatabaseHas('realtime_events', [
            'entity_type' => 'rfid',
            'entity_id' => $response->json('transaction_id'),
            'event' => 'RFID_SCAN_RESULT',
            'status' => 'accepted',
        ]);

        $dashboardResult = $this->actingAs($this->admin)
            ->getJson("/admin/rfid/scans/{$response->json('transaction_id')}/result");
        $dashboardResult->assertOk()->assertJson([
            'scan_id' => $response->json('transaction_id'),
            'status' => 'SUCCESS',
            'customer_name' => 'Juan Dela Cruz',
            'gallon_count' => 2,
        ]);
        $this->assertArrayNotHasKey('uid', $dashboardResult->json());
        $this->assertArrayNotHasKey('email', $dashboardResult->json());
        $this->assertArrayNotHasKey('phone', $dashboardResult->json());
    }

    public function test_unregistered_verification_scan_publishes_a_privacy_safe_failure(): void
    {
        $response = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/scan', [
                'uid' => 'DEADBEEF',
                'scan_type' => 'VERIFICATION',
                'scan_id' => '35d664cc-25a0-4b17-964c-440ccaf001bb',
            ]);

        $response->assertStatus(422)->assertJson([
            'success' => false,
            'error' => 'TAG_NOT_FOUND',
            'message' => 'RFID TAG NOT REGISTERED',
        ]);
        $transactionId = $response->json('transaction_id');
        $repeatResponse = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/scan', [
                'uid' => 'DEADBEEF',
                'scan_type' => 'VERIFICATION',
                'scan_id' => '35d664cc-25a0-4b17-964c-440ccaf001bb',
            ]);
        $repeatResponse->assertStatus(422)->assertJsonPath('transaction_id', $transactionId);
        $this->assertDatabaseCount('rfid_transactions', 1);
        $this->assertDatabaseHas('realtime_events', [
            'entity_type' => 'rfid',
            'entity_id' => $transactionId,
            'event' => 'RFID_SCAN_RESULT',
            'status' => 'rejected',
        ]);

        $dashboardResult = $this->actingAs($this->admin)
            ->getJson("/admin/rfid/scans/{$transactionId}/result");
        $dashboardResult->assertOk()->assertJson([
            'status' => 'FAILED',
            'message' => 'RFID TAG NOT REGISTERED',
        ]);
        $this->assertArrayNotHasKey('uid', $dashboardResult->json());
    }

    public function test_expired_registration_session_is_rejected(): void
    {
        $container = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-005',
            'status' => 'active',
        ]);

        $session = RfidRegistrationSession::create([
            'customer_container_id' => $container->id,
            'station_device_id' => $this->device->id,
            'created_by' => $this->admin->id,
            'status' => 'pending',
            'expires_at' => now()->subMinute(), // Expired
        ]);

        $response = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/registration-scan', [
                'device_id' => $this->device->id,
                'session_id' => $session->id,
                'uid' => 'F1F2F3F4',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'error' => 'SESSION_EXPIRED',
        ]);

        $session->refresh();
        $this->assertEquals('expired', $session->status);
    }

    public function test_admin_can_replace_rfid_tag_atomically(): void
    {
        $container = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-006',
            'status' => 'active',
        ]);

        $oldTag = CustomerContainerRfidTag::create([
            'customer_container_id' => $container->id,
            'uid' => 'OLD_UID_123',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/rfid/tags/{$oldTag->id}/replace", [
                'new_uid' => 'NEW_UID_456',
                'reason' => 'Physical tag peeling off',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'RFID tag replaced successfully.',
        ]);

        $oldTag->refresh();
        $this->assertEquals('replaced', $oldTag->status);
        $this->assertNotNull($oldTag->deactivated_at);

        $this->assertDatabaseHas('customer_container_rfid_tags', [
            'customer_container_id' => $container->id,
            'uid' => 'NEW_UID_456',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('rfid_transactions', [
            'customer_container_id' => $container->id,
            'transaction_type' => 'TAG_REPLACEMENT',
            'scan_result' => 'accepted',
        ]);
    }

    public function test_admin_can_deactivate_rfid_tag(): void
    {
        $container = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-007',
            'status' => 'active',
        ]);

        $tag = CustomerContainerRfidTag::create([
            'customer_container_id' => $container->id,
            'uid' => 'DEACT_UID_789',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/admin/rfid/tags/{$tag->id}/deactivate", [
                'reason' => 'Container broken during home use',
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $tag->refresh();
        $this->assertEquals('inactive', $tag->status);
        $this->assertNotNull($tag->deactivated_at);
    }

    public function test_station_intake_scan_validates_customer_and_order(): void
    {
        $container = CustomerContainer::create([
            'customer_id' => $this->customer->id,
            'container_code' => 'AC-JUA-008',
            'status' => 'active',
        ]);

        $tag = CustomerContainerRfidTag::create([
            'customer_container_id' => $container->id,
            'uid' => 'INTAKE_UID_101',
            'status' => 'active',
            'registered_at' => now(),
        ]);

        // Customer's order awaiting intake
        $address = CustomerAddress::create([
            'customer_id' => $this->customer->id,
            'address_line' => '123 Main St',
            'barangay' => 'Gatid',
            'city' => 'Santa Cruz',
        ]);

        $validOrder = Order::create([
            'customer_id' => $this->customer->id,
            'address_id' => $address->id,
            'order_number' => '1001',
            'status' => 'picked_up',
            'delivery_method' => 'home_delivery',
            'expected_empty_gallons' => 2,
            'total_amount' => 100.00,
        ]);

        // Valid scan: container matches order customer
        $validScan = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/scan', [
                'uid' => 'INTAKE_UID_101',
                'scan_type' => 'STATION_INTAKE',
                'order_id' => $validOrder->id,
            ]);

        $validScan->assertStatus(200);
        $validScan->assertJson([
            'success' => true,
            'message' => 'RFID tag verified successfully.',
        ]);

        // Other customer's order
        $otherCustomerUser = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'name' => 'Maria Santos',
        ]);
        $otherCustomer = Customer::create(['user_id' => $otherCustomerUser->id]);
        $otherAddress = CustomerAddress::create([
            'customer_id' => $otherCustomer->id,
            'address_line' => '456 Elm St',
            'barangay' => 'Duhat',
            'city' => 'Santa Cruz',
        ]);

        $otherOrder = Order::create([
            'customer_id' => $otherCustomer->id,
            'address_id' => $otherAddress->id,
            'order_number' => '1002',
            'status' => 'picked_up',
            'delivery_method' => 'home_delivery',
            'expected_empty_gallons' => 2,
            'total_amount' => 100.00,
        ]);

        // Mismatched scan: container belongs to Juan, but order is for Maria
        $invalidScan = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/scan', [
                'uid' => 'INTAKE_UID_101',
                'scan_type' => 'STATION_INTAKE',
                'order_id' => $otherOrder->id,
            ]);

        $invalidScan->assertStatus(422);
        $invalidScan->assertJson([
            'success' => false,
            'error' => 'CUSTOMER_MISMATCH',
        ]);

        $this->assertDatabaseHas('rfid_transactions', [
            'customer_container_id' => $container->id,
            'transaction_type' => 'STATION_INTAKE',
            'scan_result' => 'rejected',
        ]);
    }

    public function test_admin_can_rotate_device_security_token(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/admin/rfid/devices/{$this->device->id}/rotate-token");

        $response->assertStatus(200);
        $newToken = $response->json('plain_token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($this->deviceToken, $newToken);

        // Old token should now be rejected
        $oldResponse = $this->withHeader('X-Device-Token', $this->deviceToken)
            ->postJson('/api/iot/rfid/heartbeat');
        $oldResponse->assertStatus(401);

        // New token should work
        $newResponse = $this->withHeader('X-Device-Token', $newToken)
            ->postJson('/api/iot/rfid/heartbeat');
        $newResponse->assertStatus(200);
    }
}

