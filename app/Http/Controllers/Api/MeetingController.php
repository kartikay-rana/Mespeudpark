<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Mixins\RegistrationPackage\UserPackage;
use App\Models\Meeting;
use App\Models\MeetingTime;
use App\Traits\ValidationErrorTrait;
use Illuminate\Support\Facades\Validator;
use App\Models\Category;
use App\Models\Webinar;
use App\Models\Session;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
class MeetingController extends Controller
{
    //
      use ValidationErrorTrait;
     public function manage_meeting(Request $request){
      
         $user = auth()->user();
  
  
           $data_bigbl= \Bigbluebutton::all();

            $webinar_data=Sale::with('webinar.sessions.meeting_title')->where('buyer_id',$user->id)->orderBy('id', 'DESC')->get();
          
                //   dd($webinar_data);
       

        if($webinar_data){
             return response(['statusCode' => 200, 'message' => "Meeting data",'data'=>$webinar_data]);
        }else{
             return response(['statusCode' => 400, 'message' => "No data found"]);
        }



    
  
    
    }
    
    
     public function getrecordinglist(Request $request){
         
         $validator = Validator::make($request->all(), [
           // 'full_name' => 'required',
            'id' => 'required',
          
           
        ]);
        //dd($request->all());
        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        $id=$request->id;
        
        $user = auth()->user();
       
      $all_recording=\Bigbluebutton::getRecordings([
            'meetingID' => $id,
          
        ]);
      // dd($all_recording);
            if(count($all_recording)>0 && $user->role_id==1){
        
    
        foreach($all_recording as $all_recording_data){
          $exists=    DB::table('recording_lists')->where('recordID',$all_recording_data['recordID'] )->where('meetingID',$all_recording_data['meetingID'])->exists();
        
          if($exists==false){
              DB::table('recording_lists')->insert(['recordID'=>$all_recording_data['recordID'],'meetingID'=>$all_recording_data['meetingID'],'startTime'=>$all_recording_data['startTime'],'endTime'=>$all_recording_data['endTime'],'title'=>$all_recording_data['name'],'url'=>$all_recording_data['playback']['format']['url']] );
          }
            
            
        }
        }
     $recording_list=DB::table('recording_lists')->where('meetingID',$id)->get();
     
    
     
            $session_data=    Session::with(['webinar','meeting_title'])->where('id',(int)$id)->first();
           
          $data =[
              $session_data = $session_data,
             $session_data->recording_list =$recording_list,
               
              ];
        // return $data;
  if($session_data){
        return response(['statusCode' => 200, 'message' => 'Recording list','data'=>$data]);
  }else{
        return response(['statusCode' => 200, 'message' => 'No data found']);
  }

  
      
    }
    
    
       public function joinToBigBlueButton(Request $request)
    {
        
         $validator = Validator::make($request->all(), [
           // 'full_name' => 'required',
            'id' => 'required',
          
           
        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        $id=$request->id;
        $session = Session::where('id', $id)
            ->where('session_api', 'big_blue_button')
          //  ->where('status', Session::$Active)
            ->first();
     

        if (!empty($session)) {
            $user = auth()->user();

            if ($user->id == $session->creator_id) {
                             $metting_info_status=\Bigbluebutton::isMeetingRunning([
                    'meetingID' => $session->id,
                ]);
            //     
                 //dd($metting_info_status);
                
                if($metting_info_status==false){
                    
             
                      $createMeeting = \Bigbluebutton::initCreateMeeting([
                    'meetingID' => $session->id,
                    'meetingName' => $session->title,
                    'attendeePW' => $session->api_secret,
                    'moderatorPW' => $session->moderator_secret,
                    'record'=>true,
                    'logoutUrl' => 'https://aimpariksha.com/login',
                ]);
        
                $createMeeting->setDuration(1440);
                
        
             $check_end_metting=  \Bigbluebutton::create($createMeeting);
                }
                       
            
                
                
                $url = \Bigbluebutton::join([
                    'meetingID' => $session->id,
                    'userName' => $user->full_name,
                    'password' => $session->moderator_secret
                ]);
              
             $metting_info= \Bigbluebutton::getMeetingInfo([
              'meetingID' => $session->id,
                'moderatorPW' => $session->moderator_secret //moderator password set here
                ]);
                //dd($metting_info);
                
            //      $metting_info_status=\Bigbluebutton::isMeetingRunning([
            //         'meetingID' => $session->id,
            //     ]);
                
                
                
            //     dd($metting_info);
                if ($url) {
                    Session::where('id', $id)
                ->where('session_api', 'big_blue_button')
                ->update(['status'=>Session::$Active]);
                
                    return redirect($url);
                }
            } else {
                $checkSale = Sale::where('buyer_id', $user->id)
                    ->where('webinar_id', $session->webinar_id)
                    ->where('type', 'webinar')
                    ->whereNull('refund_at')
                    ->first();

                if (!empty($checkSale)) {
                     $metting_info_status=\Bigbluebutton::isMeetingRunning([
                    'meetingID' => $session->id,
                ]);
            //     
         
                
                if($metting_info_status==false){
                    
             
                      $createMeeting = \Bigbluebutton::initCreateMeeting([
                    'meetingID' => $session->id,
                    'meetingName' => $session->title,
                    'attendeePW' => $session->api_secret,
                    'moderatorPW' => $session->moderator_secret,
                    'record'=>true,
                    'logoutUrl' => 'https://aimpariksha.com/login',
                ]);
        
                $createMeeting->setDuration(1440);
                
        
             $check_end_metting=  \Bigbluebutton::create($createMeeting);
             
                }
                    

                    $url = \Bigbluebutton::join([
                        'meetingID' => $session->id,
                        'userName' => $user->full_name,
                        'password' => $session->api_secret
                    ]);

                    if ($url) {
                            Session::where('id', $id)
                ->where('session_api', 'big_blue_button')
                ->update(['status'=>Session::$Active]);
           
                       // return redirect($url);
                        
                         return response(['statusCode' => 200, 'message' => 'Recording list','url'=>$url]);
                    }
                }
            }
        }
         return response(['statusCode' => 400, 'message' => 'Session does not exist ']);

        
    }
    
}
