<?php

namespace App\Services;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use JsonException;

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

    public function editAddress($userId, $addressId, $label, $recipientName, $phone, $addressLine1, $addressLine2, $city, $state, $postcode, $isDefault): bool
    {
        return DB::transaction(function () use ($userId, $addressId, $label, $recipientName, $phone, $addressLine1, $addressLine2, $city, $state, $postcode, $isDefault): bool {
            $address = DB::selectOne(
                'SELECT id FROM addresses WHERE user_id = ? AND id = ?',
                [$userId, $addressId]
            );

            if ($address === null) {
                return false;
            }

            if ($isDefault == true) {
                DB::update(
                    'UPDATE addresses SET is_default = 0 WHERE user_id = ? AND id != ? AND is_default = 1',
                    [$userId, $addressId]
                );
            }

            DB::update(
                'UPDATE addresses SET label = ?, recipient_name = ?, phone = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, postcode = ?, is_default = ? WHERE user_id = ? AND id = ?',
                [$label, $recipientName, $phone, $addressLine1, $addressLine2, $city, $state, $postcode, (bool) $isDefault, $userId, $addressId]
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
