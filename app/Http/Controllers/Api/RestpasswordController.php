<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ValidationErrorTrait;
use Illuminate\Support\Facades\Validator;
use App\User;
use App\restpasswordotp;

class RestpasswordController extends Controller
{
    //
       use ValidationErrorTrait;
     public function send_otp(Request $request){
             $validator = Validator::make($request->all(), [
         
             'mobile' => 'required',
            
           
        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
          $userexists= User::where('mobile',$request->mobile)->first();
          if($userexists){
              
              $save_data=new restpasswordotp();
              $save_data->user_id=$userexists->id;
               $save_data->otp=rand(1000, 9999);
               $save_data->save();
               if($save_data){
                    return response(['statusCode' => 200, 'message' => 'otp','data'=>$save_data]); 
               }else{
                    return response(['statusCode' => 400, 'message' => 'Please try again']); 
               }
              
          }else{
              return response(['statusCode' => 400, 'message' => 'Phone number does not exits']); 
          }
     }
     
     
     public function set_new_password(Request $request){
           $validator = Validator::make($request->all(), [
         
             'otp' => 'required',
              'password' => 'required',
            
           
        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        
         $userexists= restpasswordotp::where('otp',$request->otp)->first();
         
         if($userexists){
              $userexists_data= User::where('id',$userexists->id)->first();
              $userexists_data->password=bcrypt($request->password);
             $userexists_data->save();
              return response(['statusCode' => 200, 'message' => 'Successfull change password']); 
         }else{
                return response(['statusCode' => 400, 'message' => 'otp do not matched']); 
         }
         
     }
}
