<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Favorite;
use App\Models\Webinar;
use Illuminate\Support\Facades\Validator;
use App\Traits\ValidationErrorTrait;
use Illuminate\Support\Facades\Auth;
class FavoriteController extends Controller
{
    //
     use ValidationErrorTrait;
    
     public function toggle(Request $request)
    {
        
           $validator = Validator::make($request->all(), [
           
            'slug' => 'required'
             
        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        
        
        $userId = auth()->id();
        $slug=$request->slug;
        $webinar = Webinar::where('slug', $slug)
            ->where('status', 'active')
            ->first();

        if (!empty($webinar)) {

            $isFavorite = Favorite::where('webinar_id', $webinar->id)
                ->where('user_id', $userId)
                ->first();

            if (empty($isFavorite)) {
                
                Favorite::create([
                    'user_id' => $userId,
                    'webinar_id' => $webinar->id,
                    'created_at' => time()
                ]);
                 return response(['statusCode' => 200, 'message' => 'Create successful']);
            } else {
                 $isFavorite->delete();
               return response(['statusCode' => 200, 'message' => 'Delete successful']);
            }
        }

       
    }
    
    public function toggle_list(Request $request){
        
        
      $toggle_list=  Favorite::where('user_id',Auth::user()->id)->with('webinar')->get();
      if($toggle_list){
          return response(['statusCode' => 200, 'message' => 'Toggle list data','data'=>$toggle_list]);
      }else{
           return response(['statusCode' => 400, 'message' => 'No data found']);
      }
    }
}
