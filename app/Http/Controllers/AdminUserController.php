<?php

namespace App\Http\Controllers;


use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    
 public function getUserState(Request $request) :JsonResponse{

   if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }


        try {
          $userState = DB::selectOne("SELECT 
                          (SELECT COUNT(id) FROM users WHERE role = 'admin') AS admin,
                          (SELECT COUNT(id) FROM users WHERE role = 'user') AS user,
                          COUNT(id) AS total_user
                          FROM users
                           ");

                  return response()->json([
                    'status' => true,
                    'data' => $userState
                  ]);         
        } catch (\Throwable $th) {
           report($th);
            
            return response()->json([
                'status' => false,
                'message' => 'Unable to get userState for admin',
            ], 500);
        }


 }

 public function getAllUserList (Request $request) :JsonResponse {
    if ($request->user()?->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Forbidden.',
            ], 403);
        }

        
              
          
        

        try {
           $users = DB::select('SELECT * FROM users');

           
             return response()->json([
                    'status' => true,
                    'data' => $users
                  ]); 

        } catch (\Throwable $th) {
            report($th);
            
            return response()->json([
                'status' => false,
                'message' => 'Unable to get user data for admin',
            ], 500);
        }
 }


}
