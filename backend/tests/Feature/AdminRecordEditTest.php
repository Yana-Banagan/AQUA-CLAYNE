<?php

namespace Tests\Feature;

use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRecordEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    public function test_product_edit_updates_existing_record_and_image_without_recreating_inventory(): void
    {
        $productCount = Product::count();
        $product = Product::create([
            'name' => 'Purified Refill',
            'description' => 'Original description',
            'price' => 50,
            'image_url' => null,
            'is_active' => true,
        ]);
        $inventory = Inventory::create([
            'product_id' => $product->id,
            'quantity_on_hand' => 23,
            'reorder_level' => 5,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.products.create'))
            ->assertOk()
            ->assertSee('name="image_url"', false);

        $this->actingAs($this->admin)
            ->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Purified Refill')
            ->assertSee('name="image_url"', false);

        $this->put(route('admin.products.update', $product), [
            'name' => 'Purified Refill Plus',
            'description' => 'Updated description',
            'price' => '65.50',
            'image_url' => 'https://example.test/refill.png',
            'is_active' => '1',
        ])->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseCount('products', $productCount + 1);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Purified Refill Plus',
            'image_url' => 'https://example.test/refill.png',
        ]);
        $this->assertDatabaseHas('inventory', [
            'id' => $inventory->id,
            'product_id' => $product->id,
            'quantity_on_hand' => 23,
        ]);
        $this->get(route('admin.products.edit', $product))
            ->assertOk()
            ->assertSee('Purified Refill Plus');
    }

    public function test_invalid_product_edit_keeps_the_existing_values(): void
    {
        $productCount = Product::count();
        $product = Product::create([
            'name' => 'Purified Refill',
            'price' => 50,
            'is_active' => true,
        ]);

        $this->from(route('admin.products.edit', $product))
            ->actingAs($this->admin)
            ->put(route('admin.products.update', $product), [
                'name' => 'Changed Name',
                'price' => '-1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('price');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Purified Refill',
            'price' => '50.00',
        ]);
        $this->assertDatabaseCount('products', $productCount + 1);
    }

    public function test_customer_edit_preserves_customer_identity_and_related_record_ownership(): void
    {
        $customerUser = User::factory()->create([
            'role' => 'customer',
            'name' => 'Original Customer',
            'email' => 'customer@example.test',
        ]);
        $customer = Customer::create([
            'user_id' => $customerUser->id,
            'birthdate' => '1990-01-02',
        ]);
        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'address_line' => '12 Station Road',
            'is_default' => true,
        ]);
        $creditAccount = CreditAccount::create([
            'customer_id' => $customer->id,
            'balance' => 125,
            'credit_limit' => 500,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.customers.edit', $customer))
            ->assertOk()
            ->assertSee('Original Customer');

        $this->put(route('admin.customers.update', $customer), [
            'name' => 'Updated Customer',
            'email' => 'customer@example.test',
            'phone' => '09171234567',
            'status' => 'active',
            'birthdate' => '1991-02-03',
        ])->assertRedirect(route('admin.customers.show', $customer));

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'user_id' => $customerUser->id,
            'birthdate' => '1991-02-03 00:00:00',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $customerUser->id,
            'name' => 'Updated Customer',
            'role' => 'customer',
        ]);
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'customer_id' => $customer->id,
            'address_line' => '12 Station Road',
        ]);
        $this->assertDatabaseHas('credit_accounts', [
            'id' => $creditAccount->id,
            'customer_id' => $customer->id,
            'balance' => '125.00',
        ]);
        $this->get(route('admin.customers.edit', $customer))
            ->assertOk()
            ->assertSee('Updated Customer');
    }

    public function test_customer_json_edit_loads_and_updates_the_existing_profile_and_address(): void
    {
        $customerUser = User::factory()->create([
            'role' => 'customer',
            'name' => 'JSON Customer',
            'email' => 'json-customer@example.test',
        ]);
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'address_line' => 'Old Street',
            'barangay' => 'Old Barangay',
            'city' => 'Old City',
            'province' => 'Old Province',
            'zip_code' => '1000',
            'is_default' => true,
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.customers.edit', $customer))
            ->assertOk()
            ->assertJsonPath('name', 'JSON Customer')
            ->assertJsonPath('address.id', $address->id)
            ->assertJsonPath('address.barangay', 'Old Barangay');

        $this->putJson(route('admin.customers.update', $customer), [
            'name' => 'Updated JSON Customer',
            'email' => $customerUser->email,
            'phone' => '09171234567',
            'status' => 'active',
            'address_id' => $address->id,
            'address_line' => 'New Street',
            'barangay' => 'New Barangay',
            'city' => 'New City',
            'province' => 'New Province',
            'zip_code' => '2000',
        ])->assertOk()
            ->assertJsonPath('customer.name', 'Updated JSON Customer')
            ->assertJsonPath('customer.address', 'New Street, New Barangay');

        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $address->id,
            'customer_id' => $customer->id,
            'address_line' => 'New Street',
            'barangay' => 'New Barangay',
        ]);
    }

    public function test_customer_continue_to_edit_opens_the_edit_modal_from_the_customer_list(): void
    {
        $customerUser = User::factory()->create([
            'role' => 'customer',
            'name' => 'Modal Customer',
            'email' => 'modal-customer@example.test',
        ]);
        $customer = Customer::create(['user_id' => $customerUser->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee(route('admin.customers.index', ['edit' => $customer->id]), false)
            ->assertSee('Continue to Edit');

        $this->get(route('admin.customers.index', ['edit' => $customer->id]))
            ->assertOk()
            ->assertSee('id="customerEditModal"', false)
            ->assertSee('data-customer-edit="' . route('admin.customers.edit', $customer) . '"', false)
            ->assertSee('new URLSearchParams(window.location.search).get(\'edit\')', false);
    }

    public function test_customer_json_edit_rejects_an_address_owned_by_another_customer(): void
    {
        $firstUser = User::factory()->create(['role' => 'customer', 'name' => 'First']);
        $first = Customer::create(['user_id' => $firstUser->id]);
        $secondUser = User::factory()->create(['role' => 'customer', 'name' => 'Second']);
        $second = Customer::create(['user_id' => $secondUser->id]);
        $foreignAddress = CustomerAddress::create([
            'customer_id' => $second->id,
            'address_line' => 'Private Street',
        ]);

        $this->actingAs($this->admin)
            ->putJson(route('admin.customers.update', $first), [
                'name' => 'Attempted Change',
                'email' => $firstUser->email,
                'status' => 'active',
                'address_id' => $foreignAddress->id,
                'address_line' => 'Changed Street',
                'barangay' => 'Changed Barangay',
            ])->assertNotFound();

        $this->assertDatabaseHas('users', ['id' => $firstUser->id, 'name' => 'First']);
        $this->assertDatabaseHas('customer_addresses', [
            'id' => $foreignAddress->id,
            'customer_id' => $second->id,
            'address_line' => 'Private Street',
        ]);
    }

    public function test_customer_and_driver_edits_enforce_unique_contacts_and_staff_restrictions(): void
    {
        $customerUser = User::factory()->create([
            'role' => 'customer',
            'email' => 'customer-unique@example.test',
            'phone' => '09173333333',
        ]);
        $customer = Customer::create(['user_id' => $customerUser->id]);
        $otherUser = User::factory()->create([
            'role' => 'customer',
            'email' => 'other-customer@example.test',
            'phone' => '09174444444',
        ]);

        $this->actingAs($this->admin)
            ->putJson(route('admin.customers.update', $customer), [
                'name' => 'Customer',
                'email' => $otherUser->email,
                'phone' => $otherUser->phone,
                'status' => 'active',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);

        $staff = User::factory()->create(['role' => 'staff', 'status' => 'active']);
        $driverUser = User::factory()->create([
            'role' => 'driver',
            'email' => 'restricted-driver@example.test',
        ]);
        $driver = Driver::create(['user_id' => $driverUser->id]);

        $this->actingAs($staff)
            ->getJson(route('admin.drivers.edit', $driver))
            ->assertOk()
            ->assertJsonPath('can_manage_restricted_fields', false);

        $this->putJson(route('admin.drivers.update', $driver), [
            'name' => 'Restricted Driver',
            'email' => $driverUser->email,
            'status' => 'inactive',
            'license_number' => 'NOT-ALLOWED',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'license_number']);

        $this->assertDatabaseHas('users', [
            'id' => $driverUser->id,
            'name' => $driverUser->name,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'license_number' => null,
        ]);
    }

    public function test_driver_json_edit_loads_and_updates_profile_without_changing_password(): void
    {
        $driverUser = User::factory()->create([
            'role' => 'driver',
            'name' => 'JSON Driver',
            'email' => 'json-driver@example.test',
            'phone' => '09170000000',
        ]);
        $passwordHash = $driverUser->password_hash;
        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'license_number' => 'LIC-1',
            'vehicle_plate' => 'PLATE-1',
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.drivers.edit', $driver))
            ->assertOk()
            ->assertJsonPath('name', 'JSON Driver')
            ->assertJsonPath('license_number', 'LIC-1')
            ->assertJsonPath('can_manage_restricted_fields', true);

        $this->putJson(route('admin.drivers.update', $driver), [
            'name' => 'Updated JSON Driver',
            'email' => $driverUser->email,
            'phone' => '09171111111',
            'status' => 'active',
            'license_number' => 'LIC-2',
            'vehicle_plate' => 'PLATE-2',
        ])->assertOk()
            ->assertJsonPath('driver.name', 'Updated JSON Driver');

        $this->assertDatabaseCount('drivers', 1);
        $this->assertDatabaseHas('users', [
            'id' => $driverUser->id,
            'name' => 'Updated JSON Driver',
            'password_hash' => $passwordHash,
        ]);
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'license_number' => 'LIC-2',
            'vehicle_plate' => 'PLATE-2',
        ]);
    }

    public function test_admin_credentials_command_updates_only_the_explicit_admin_and_verifies_password(): void
    {
        $password = 'test-only-secure-password';
        $this->artisan('admin:update-credentials', [
            'current-email' => $this->admin->email,
            'new-email' => 'updated-admin@example.test',
        ])
            ->expectsQuestion('New administrator password (input hidden)', $password)
            ->expectsQuestion('Confirm new administrator password (input hidden)', $password)
            ->expectsOutputToContain('updated and verified')
            ->assertExitCode(0);

        $updated = User::findOrFail($this->admin->id);
        $this->assertSame('updated-admin@example.test', $updated->email);
        $this->assertSame('admin', $updated->role);
        $this->assertNotSame($password, $updated->password_hash);
        $this->assertTrue(Hash::check($password, $updated->password_hash));
    }

    public function test_admin_credentials_command_refuses_to_overwrite_an_unrelated_account(): void
    {
        $originalHash = $this->admin->password_hash;
        User::factory()->create([
            'role' => 'staff',
            'email' => 'unrelated-account@example.test',
        ]);

        $this->artisan('admin:update-credentials', [
            'current-email' => $this->admin->email,
            'new-email' => 'unrelated-account@example.test',
        ])
            ->expectsOutputToContain('already assigned to another account')
            ->assertExitCode(1);

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'email' => $this->admin->email,
            'password_hash' => $originalHash,
            'role' => 'admin',
        ]);
    }

    public function test_driver_edit_updates_user_and_driver_without_changing_password_or_creating_an_account(): void
    {
        $driverUser = User::factory()->create([
            'role' => 'driver',
            'name' => 'Original Driver',
            'email' => 'driver@example.test',
            'phone' => '09170000000',
        ]);
        $passwordHash = $driverUser->password_hash;
        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'license_number' => 'OLD-1',
            'vehicle_plate' => 'OLD-PLATE',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.drivers.index'))
            ->assertOk()
            ->assertSee('id="openRegisterDriverModal"', false);

        $this->actingAs($this->admin)
            ->get(route('admin.drivers.edit', $driver))
            ->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('Original Driver');

        $this->put(route('admin.drivers.update', $driver), [
            'name' => 'Updated Driver',
            'email' => 'driver@example.test',
            'phone' => '09171111111',
            'status' => 'inactive',
            'license_number' => 'NEW-1',
            'vehicle_plate' => 'NEW-PLATE',
        ])->assertRedirect(route('admin.drivers.index'));

        $this->assertDatabaseCount('drivers', 1);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'user_id' => $driverUser->id,
            'license_number' => 'NEW-1',
            'vehicle_plate' => 'NEW-PLATE',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $driverUser->id,
            'name' => 'Updated Driver',
            'status' => 'inactive',
            'password_hash' => $passwordHash,
        ]);
        $this->get(route('admin.drivers.edit', $driver))
            ->assertOk()
            ->assertSee('Updated Driver');
    }

    public function test_staff_edit_preserves_role_and_password_and_staff_cannot_manage_users(): void
    {
        $staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Original Staff',
        ]);
        $passwordHash = $staff->password_hash;

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee(route('admin.users.edit', $staff), false);

        $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $staff))
            ->assertOk()
            ->assertSee('Original Staff');

        $this->put(route('admin.users.update', $staff), [
            'name' => 'Updated Staff',
            'email' => $staff->email,
            'phone' => '09172222222',
            'status' => 'active',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseHas('users', [
            'id' => $staff->id,
            'name' => 'Updated Staff',
            'role' => 'staff',
            'password_hash' => $passwordHash,
        ]);
        $this->get(route('admin.users.edit', $staff))
            ->assertOk()
            ->assertSee('Updated Staff');

        $this->actingAs($staff)
            ->get(route('admin.drivers.index'))
            ->assertOk()
            ->assertDontSee('id="openRegisterDriverModal"', false);

        $this->actingAs($staff)
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('Users &amp; Roles', false);

        $this->actingAs($staff)
            ->put(route('admin.users.update', $this->admin), [
                'name' => 'Attempted Change',
                'email' => $this->admin->email,
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_invalid_faq_edit_reopens_the_edit_modal_with_validation_feedback(): void
    {
        $category = FaqCategory::create(['name' => 'Orders']);
        $faq = Faq::create([
            'category_id' => $category->id,
            'question' => 'How do I track my order?',
            'answer' => 'Open the order tracking page.',
        ]);

        $this->followingRedirects()
            ->from(route('admin.faq.index'))
            ->actingAs($this->admin)
            ->put(route('admin.faq.update', $faq), [
                'faq_id' => $faq->id,
                'category_id' => $category->id,
                'question' => '',
                'answer' => 'Updated answer',
            ])
            ->assertOk()
            ->assertSee('window.openEditFaqModal(', false)
            ->assertSee('faq_id', false)
            ->assertSee('The question field is required.');

        $this->assertDatabaseHas('faqs', [
            'id' => $faq->id,
            'question' => 'How do I track my order?',
        ]);
    }

    public function test_faq_edit_updates_the_existing_entry(): void
    {
        $category = FaqCategory::create(['name' => 'Orders']);
        $faq = Faq::create([
            'category_id' => $category->id,
            'question' => 'Original question',
            'answer' => 'Original answer',
        ]);

        $this->from(route('admin.faq.index'))
            ->actingAs($this->admin)
            ->put(route('admin.faq.update', $faq), [
                'category_id' => $category->id,
                'question' => 'Updated question',
                'answer' => 'Updated answer',
            ])
            ->assertRedirect(route('admin.faq.index'));

        $this->assertDatabaseCount('faqs', 1);
        $this->assertDatabaseHas('faqs', [
            'id' => $faq->id,
            'question' => 'Updated question',
            'answer' => 'Updated answer',
        ]);
    }
}
