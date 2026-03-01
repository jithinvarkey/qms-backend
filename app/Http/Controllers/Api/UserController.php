<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;

class UserController extends Controller {

    public function getQualityUsers() {

        try {
            
            $users = User::whereHas('department', function ($query) {
                    $query->where('name', 'Quality');
                })
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
                return response()->json($users);
        } catch (Exception $ex) {
          return response()->json(['message' => 'Some error'.$ex->getMessage()], 400);
        }
       
        
    }
}
