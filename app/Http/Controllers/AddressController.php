<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\AddressServices;


class AddressController extends Controller
{
public function __construct(protected AddressServices $addressServices )
{}

  public function getAllAddress(Request $request): JsonResponse
  {
    try {
      $userId = $request->user()->id;
      $addresses = DB::select(
        'SELECT * FROM addresses WHERE user_id = ?',
        [$userId]
      );

      return response()->json([
        'status' => true,
        'data' => $addresses,
      ]);
    } catch (\Throwable $exception) {
      report($exception);

      return response()->json([
        'status' => false,
        'message' => 'Unable to retrieve addresses.',
      ], 500);
    }
    }


    public function addAddress(Request $request) :JsonResponse{

      $userId = request()->user()->id;
        
         $insertData = $this->addressServices->addAddress($userId,
         $request->input('label')
         ,$request->input('recipient_name')
         ,$request->input('phone')
         ,$request->input('address_line1')
         ,$request->input('address_line2')
         ,$request->input('city')
         ,$request->input('state')
         ,$request->input('postcode')
         ,$request->input('is_default'));

         if($insertData){
            return response()->json([
        'status' => true,
        'message' => 'berjaya tambah data',
      ], 200);
         }

         return response()->json([
        'status' => false,
        'message' => 'err tambah alamat',
      ], 500);
    }

    public function editAddress(Request $request, $addressId) :JsonResponse{

      $userId = request()->user()->id;
        
         $insertData = $this->addressServices->editAddress($userId,
         $addressId,
         $request->input('label')
         ,$request->input('recipient_name')
         ,$request->input('phone')
         ,$request->input('address_line1')
         ,$request->input('address_line2')
         ,$request->input('city')
         ,$request->input('state')
         ,$request->input('postcode')
         ,$request->input('is_default'));

         if($insertData){
            return response()->json([
        'status' => true,
        'message' => 'berjaya edit data',
      ], 200);
         }

         return response()->json([
        'status' => false,
        'message' => 'err edit alamat',
      ], 500);
    }


    public function getAddressById(Request $request, $addressId):JsonResponse{
        
         $userId = request()->user()->id;
        $getAddress = $this->addressServices->getAddressById($userId,$addressId);  
          
           if($getAddress){
            return response()->json($getAddress);
           }

            return response()->json([
        'status' => false,
        'message' => 'err dapatkan alamat',
      ], 500);
    } 
}
