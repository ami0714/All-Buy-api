<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserAndAddressUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_partially_update_profile_using_bound_raw_sql(): void
    {
        $user = $this->createUser('profile', 'original@example.com', '0123456789');

        $this->actingAs($user)
            ->patchJson('/api/user', [
                'username' => 'updated-profile',
                'email' => 'updated@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'updated-profile',
            'email' => 'updated@example.com',
            'phone' => '0123456789',
        ]);
    }

    public function test_user_can_partially_update_address_with_set_clauses_and_bound_values(): void
    {
        $user = $this->createUser('address');
        $oldDefaultAddressId = $this->createAddress($user->id, 'Home', 'Old City', true);
        $addressId = $this->createAddress($user->id, 'Office', 'Original City', false);

        $this->actingAs($user)
            ->patchJson("/api/user/address/edit/{$addressId}", [
                'city' => 'Updated City',
                'is_default' => true,
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('addresses', [
            'id' => $addressId,
            'label' => 'Office',
            'city' => 'Updated City',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('addresses', [
            'id' => $oldDefaultAddressId,
            'is_default' => false,
        ]);
    }

    private function createUser(string $suffix, string $email = '', ?string $phone = null): User
    {
        static $counter = 0;
        $counter++;
        $uid = str_pad((string) $counter, 8, '0', STR_PAD_LEFT);
        $username = "update-user-{$suffix}-{$counter}";
        $email = $email !== '' ? $email : "{$username}@example.com";

        DB::insert(
            'INSERT INTO users (uid, username, email, password, phone, role) VALUES (?, ?, ?, ?, ?, ?)',
            [$uid, $username, $email, 'password', $phone, 'user']
        );

        return User::query()->where('email', $email)->firstOrFail();
    }

    private function createAddress(int $userId, string $label, string $city, bool $isDefault): int
    {
        return DB::table('addresses')->insertGetId([
            'user_id' => $userId,
            'label' => $label,
            'recipient_name' => 'Test Recipient',
            'phone' => '0123456789',
            'address_line1' => '1 Example Street',
            'city' => $city,
            'state' => 'Selangor',
            'postcode' => '43000',
            'is_default' => $isDefault,
        ]);
    }
}
