<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AddressServices
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    public function addAddress($userId, $label, $recipientName, $phone, $addressLine1, $addressLine2, $city, $state, $postcode, $isDefault): bool
    {
        return DB::transaction(function () use ($userId, $label, $recipientName, $phone, $addressLine1, $addressLine2, $city, $state, $postcode, $isDefault): bool {
            if ($isDefault == true) {
                DB::update(
                    'UPDATE addresses SET is_default = 0 WHERE user_id = ? AND is_default = 1',
                    [$userId]
                );
            }

            return DB::insert(
                'INSERT INTO addresses (user_id, label, recipient_name, phone, address_line1, address_line2, city, state, postcode, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, $label, $recipientName, $phone, $addressLine1, $addressLine2, $city, $state, $postcode, (bool) $isDefault]
            );
        });
    }

    public function editAddress(int $userId, int $addressId, array $attributes): bool
    {
        return DB::transaction(function () use ($userId, $addressId, $attributes): bool {
            $address = DB::selectOne(
                'SELECT id FROM addresses WHERE user_id = ? AND id = ?',
                [$userId, $addressId]
            );

            if ($address === null) {
                return false;
            }

            if ((bool) ($attributes['is_default'] ?? false)) {
                DB::update(
                    'UPDATE addresses SET is_default = 0 WHERE user_id = ? AND id != ? AND is_default = 1',
                    [$userId, $addressId]
                );
            }

            $fieldMap = [
                'label' => 'label',
                'recipient_name' => 'recipient_name',
                'phone' => 'phone',
                'address_line1' => 'address_line1',
                'address_line2' => 'address_line2',
                'city' => 'city',
                'state' => 'state',
                'postcode' => 'postcode',
                'is_default' => 'is_default',
            ];
            $setClauses = [];
            $bindings = [];

            foreach ($fieldMap as $input => $column) {
                if (! array_key_exists($input, $attributes)) {
                    continue;
                }

                $setClauses[] = "{$column} = ?";
                $bindings[] = $input === 'is_default'
                    ? (int) $attributes[$input]
                    : $attributes[$input];
            }

            $bindings[] = $userId;
            $bindings[] = $addressId;

            DB::update(
                'UPDATE addresses SET '.implode(', ', $setClauses).' WHERE user_id = ? AND id = ?',
                $bindings
            );

            return true;
        });
    }

    public function getAddressById($userId,$addressId){
      
        if (!isset($userId) && !isset($addressId)) {
            return[
                'status' => false,
                'message' => 'tiada userid dan addressid'
            ];
        }

        $addressData = DB::selectOne('SELECT * FROM addresses WHERE user_id = ? AND id = ?',[$userId,$addressId]);

        if ($addressData) {
            return [
                'status' => true,
                'data' => $addressData
            ];
        }
     
    }
}
