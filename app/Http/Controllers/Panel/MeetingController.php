<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Mixins\RegistrationPackage\UserPackage;
use App\Models\Meeting;
use App\Models\MeetingTime;
use \Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Category;
use App\Models\Webinar;
use App\Models\Session;
use App\Models\Sale;

use Illuminate\Support\Facades\DB;
class MeetingController extends Controller
{
    public function setting(Request $request)
    {
        $user = auth()->user();

        $meeting = Meeting::where('creator_id', $user->id)
            ->with([
                'meetingTimes'
            ])
            ->first();

        if (empty($meeting)) {
            $meeting = Meeting::create([
                'creator_id' => $user->id,
                'created_at' => time()
            ]);
        }

        $meetingTimes = [];
        foreach ($meeting->meetingTimes->groupBy('day_label') as $day => $meetingTime) {

            $times = 0;
            foreach ($meetingTime as $time) {

                $meetingTimes[$day]["times"][] = $time;

                $explodetime = explode('-', $time->time);
                $times += strtotime($explodetime[1]) - strtotime($explodetime[0]);
            }
            $meetingTimes[$day]["hours_available"] = round($times / 3600, 2);

        }

        $data = [
            'pageTitle' => trans('meeting.meeting_setting_page_title'),
            'meeting' => $meeting,
            'meetingTimes' => $meetingTimes,
        ];

        return view(getTemplate() . '.panel.meeting.settings', $data);
    }

    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $data = $request->all();

        $groupMeeting = (!empty($data['group_meeting']) and $data['group_meeting'] == 'on');
        $inPerson = (!empty($data['in_person']) and $data['in_person'] == 'on');

        $rules = [
            'amount' => 'required',
            'discount' => 'nullable',
            'disabled' => 'nullable',
            'in_person_amount' => 'required_if:in_person,on',
            'online_group_min_student' => 'required_if:group_meeting,on',
            'online_group_max_student' => 'required_if:group_meeting,on',
            'online_group_amount' => 'required_if:group_meeting,on',
        ];

        if ($groupMeeting and $inPerson) {
            $rules = array_merge($rules, [
                'in_person_group_min_student' => 'required_if:in_person,on',
                'in_person_group_max_student' => 'required_if:in_person,on',
                'in_person_group_amount' => 'required_if:in_person,on',
            ]);
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            return response([
                'code' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $meeting = Meeting::where('id', $id)
            ->where('creator_id', $user->id)
            ->first();

        if (!empty($meeting)) {
            $meeting->update([
                'amount' => $data['amount'],
                'discount' => $data['discount'],
                'disabled' => !empty($data['disabled']) ? 1 : 0,
                'in_person' => $inPerson,
                'in_person_amount' => $inPerson ? $data['in_person_amount'] : null,
                'group_meeting' => $groupMeeting,
                'online_group_min_student' => $groupMeeting ? $data['online_group_min_student'] : null,
                'online_group_max_student' => $groupMeeting ? $data['online_group_max_student'] : null,
                'online_group_amount' => $groupMeeting ? $data['online_group_amount'] : null,
                'in_person_group_min_student' => ($groupMeeting and $inPerson) ? $data['in_person_group_min_student'] : null,
                'in_person_group_max_student' => ($groupMeeting and $inPerson) ? $data['in_person_group_max_student'] : null,
                'in_person_group_amount' => ($groupMeeting and $inPerson) ? $data['in_person_group_amount'] : null,
            ]);

            return response()->json([
                'code' => 200
            ], 200);
        }

        return response()->json([], 422);
    }

    public function saveTime(Request $request)
    {
        $user = auth()->user();
        $meeting = Meeting::where('creator_id', $user->id)->first();
        $data = $request->all();

        if (!empty($meeting)) {

            $userPackage = new UserPackage();
            $userMeetingCountLimited = $userPackage->checkPackageLimit('meeting_count');

            if ($userMeetingCountLimited) {
                return response()->json([
                    'registration_package_limited' => $userMeetingCountLimited
                ], 200);
            }

            $time = $data['time'];
            $day = $data['day'];
            $meetingType = $data['meeting_type'];
            $description = $data['description'] ?? null;

            $explodeTime = explode('-', $time);

            if (!empty($explodeTime[0]) and !empty($explodeTime[1])) {
                $start_time = date("H:i", strtotime($explodeTime[0]));
                $end_time = date("H:i", strtotime($explodeTime[1]));

                if (strtotime($end_time) >= strtotime($start_time)) {
                    $checkTime = MeetingTime::where('meeting_id', $meeting->id)
                        ->where('day_label', $data)
                        ->where('time', $time)
                        ->first();

                    if (empty($checkTime)) {
                        MeetingTime::create([
                            'meeting_id' => $meeting->id,
                            'meeting_type' => $meetingType,
                            'day_label' => $day,
                            'time' => $time,
                            'description' => $description,
                            'created_at' => time(),
                        ]);

                        return response()->json([
                            'code' => 200
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'error' => 'contradiction'
                    ], 422);
                }
            }
        }

        return response()->json([], 422);
    }

    public function deleteTime(Request $request)
    {
        $user = auth()->user();
        $meeting = Meeting::where('creator_id', $user->id)->first();
        $data = $request->all();
        $timeIds = $data['time_id'];

        if (!empty($meeting) and !empty($timeIds) and is_array($timeIds)) {

            $meetingTimes = MeetingTime::whereIn('id', $timeIds)
                ->where('meeting_id', $meeting->id)
                ->get();

            if (!empty($meetingTimes)) {
                foreach ($meetingTimes as $meetingTime) {
                    $meetingTime->delete();
                }

                return response()->json([], 200);
            }
        }

        return response()->json([], 422);
    }

    public function temporaryDisableMeetings(Request $request)
    {
        $user = auth()->user();
        $data = $request->all();

        $meeting = Meeting::where('creator_id', $user->id)
            ->first();

        if (!empty($meeting)) {
            $meeting->update([
                'disabled' => (!empty($data['disable']) and $data['disable'] == 'true') ? 1 : 0,
            ]);

            return response()->json([
                'code' => 200
            ], 200);
        }

        return response()->json([], 422);
    }
    public function create_new (Request $request){
        
             $user = auth()->user();
        $isOrganization = $user->isOrganization();

        if (!$user->isTeacher() and !$user->isOrganization()) {
            abort(404);
        }
        
        
     
         $locale = $request->get('locale', app()->getLocale());
       
      
          
    $userLanguages=getUserLanguagesLists();
  
    
       return view('web.default.panel.webinar.create_new',compact('userLanguages','locale'));
    
    }
    
    public function manage_meeting(Request $request){
        
  $user = auth()->user();
  
  
      $data_bigbl= \Bigbluebutton::all();



        if($user->role_id==1){
            $webinar_data=Sale::with('webinar.sessions.meeting_title')->where('buyer_id',$user->id)->orderBy('id', 'DESC')->paginate(10);
           // dd($webinar_data);
          
            
        }else{
           $webinar_data =Session::with('webinar')->with('meeting_title')->where('creator_id',$user->id)->orderBy('id', 'DESC')->paginate(10); 
        }



      return view('web.default.panel.webinar.manage_meeting', compact('webinar_data'));
  
    
    }
    
    public function getrecordinglist(Request $request,$id){
        $user = auth()->user();
       
      $all_recording=\Bigbluebutton::getRecordings([
            'meetingID' => $id,
          
        ]);
            if(count($all_recording)>0 && $user->role_id==1){
        
    
        foreach($all_recording as $all_recording_data){
          $exists=    DB::table('recording_lists')->where('recordID',$all_recording_data['recordID'] )->where('meetingID',$all_recording_data['meetingID'])->exists();
        
          if($exists==false){
              DB::table('recording_lists')->insert(['recordID'=>$all_recording_data['recordID'],'meetingID'=>$all_recording_data['meetingID'],'startTime'=>$all_recording_data['startTime'],'endTime'=>$all_recording_data['endTime'],'title'=>$all_recording_data['name'],'url'=>$all_recording_data['playback']['format']['url']] );
          }
            
            
        }
        }
     $recording_list=DB::table('recording_lists')->where('meetingID',$id)->get();
     
    
       if($user->role_id !=1){
            $session_data=    Session::with('webinar')->with('meeting_title')->where('creator_id',$user->id)->where('id',$id)->first();
       }else{
            $session_data=    Session::with('webinar')->with('meeting_title')->where('id',(int)$id)->first();
           
       }
  


        return  view('web.default.panel.webinar.recordinglist',compact('recording_list','session_data'));
    }
    
    public function update_title(Request $request){
      
         DB::table('recording_lists')->where('id',$request->id)->update(['title'=>$request->title]);
          
         return back();
    }
    
}
