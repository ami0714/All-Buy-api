<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
     public function getUser(Request $request):JsonResponse
    {
        $userId = request()->user()->id;

        $user = DB::selectOne('SELECT * FROM users WHERE id = :id',['id'=>$userId]);
    


        return response()->json([
            'status' => true,
            'data' => $user
            
        ]);
    }


    public function editUser(Request $request){
        $userId = $request->user()->id;

        $validated = $request->validate([
            'username' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
        ]);

        if ($validated === []) {
            return response()->json([
                'status' => false,
                'message' => 'At least one profile field is required.',
            ], 422);
        }

        $setClauses = [];
        $bindings = [];

        foreach (['username', 'email', 'phone'] as $column) {
            if (array_key_exists($column, $validated)) {
                $setClauses[] = $column.' = ?';
                $bindings[] = $validated[$column];
            }
        }

        $setClauses[] = 'updated_at = CURRENT_TIMESTAMP';
        $bindings[] = $userId;

        DB::update(
            'UPDATE users SET '.implode(', ', $setClauses).' WHERE id = ?',
            $bindings
        );

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully.',
            
        ]);
    }
}
