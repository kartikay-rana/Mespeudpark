<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ValidationErrorTrait;
use Illuminate\Support\Facades\Validator;
use App\User;
use Illuminate\Support\Facades\DB;
use App\Models\Otp as optModel;
use Illuminate\Support\Facades\Auth;
use Hash;
use App\Models\Webinar;
use App\Models\WebinarChapter;
use App\Models\Favorite;
use App\Models\AdvertisingBanner;
use Illuminate\Support\Facades\Log;
use App\Models\QuizzesResult;
class RegistrationController extends Controller
{
     use ValidationErrorTrait;
     public function register_user(Request $request){
         
     
       $validator = Validator::make($request->all(), [
           // 'full_name' => 'required',
            'email' => 'required|unique:users,email',
            'password' => 'required',
             'mobile' => 'required|unique:users',
             'category_id'=>'required'
           
        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        
         if($request->file('user_image')){
            $file= $request->file('user_image');
            $filename= date('YmdHi').$file->getClientOriginalName();
            $file-> move(public_path('user_image'), $filename);
           
        }else{
            $filename="";
        }
        
        $save_user_detail=    new User();
        
         $save_user_detail->full_name=!empty($request->full_name)?$request->full_name:"";
         
         $save_user_detail->email=$request->email;
         $save_user_detail->mobile=$request->mobile;
         $save_user_detail->password=bcrypt($request->password);
         $save_user_detail->role_name='user';
          $save_user_detail->course_category_id=$request->category_id;
         $save_user_detail->role_id=1;
       
             
          $save_user_detail->about="cxzxc";
        $save_user_detail->offline_message="0";
         $save_user_detail->profile_image=$filename;
         $save_user_detail->created_at=time();
      
        $save_user_detail->save();
         $opt_data=rand(1000, 9999);
            
           $save_otp= DB::table('opts')->insert(['user_id'=>$save_user_detail->id,'otp'=>$opt_data]);
         
        if($save_user_detail){
             return response(['statusCode' => 200, 'message' => 'Successfully save','data'=>$opt_data]);
        }else{
             return response(['statusCode' => 400, 'message' => 'Please try again']);
            
        }
       
       

     }
     
     public function login(Request $request){
           $validator = Validator::make($request->all(), [
             'mobile' => 'required',
             'device_token' => 'required',
              'device_type' => 'required',
             
        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        $userexists= User::where('mobile',$request->mobile)->first();
        if( $userexists){
             $userexists->device_token=$request->device_token;
              $userexists->device_type=$request->device_type;
             $userexists->update();
            $opt_data=rand(1000, 9999);
            
           $save_otp= DB::table('opts')->insert(['user_id'=>$userexists->id,'otp'=>$opt_data]);
           
           
          
               return response(['statusCode' => 200, 'message' => 'Successfully save','data'=>$opt_data]);
                
        }else{
            return response(['statusCode' => 400, 'message' => 'Phone number does not exits']);
        }
     }
     
     public function verify_opt(Request $request){
           $validator = Validator::make($request->all(), [
             'mobile' => 'required',
            // 'otp'=>'required'
        ]);
             if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        
          $userexists= User::where('mobile',$request->mobile)->first();
       
            if( $userexists){
               $exist= DB::table('opts')->where('user_id', $userexists->id)->first();
            
               if($exist){
                   $token = $userexists->createToken('Authtoken')->accessToken;
                    $userexists->token=$token;
                   return response(['statusCode' => 200, 'message' => 'Successfully verify','data'=>  $userexists]); 
               }else{
                  return response(['statusCode' => 400, 'message' => 'Please enter correct opt']); 
               }
            }else{
                 return response(['statusCode' => 400, 'message' => 'Phone number does not exits']);
            }
         
     }
    public function social_login(Request $request){ 
        log::info('ghghg',$request->all());
        $validator = Validator::make($request->all(), [ 
      
        'email' => 'required', 
        'social_type'=>'required', 
        'social_id'=>'required', ]); 
        if ($validator->fails())
        { 
            $messages = implode(",", $this->errorMessages($validator->errors())); 
            return response(['statusCode' => 400, 'message' => $messages]);
            }
            
            if($request->social_type == "1"){
                log::info('hello social');
                $user_email_exists=User::where('email', $request->email)->first();
                if($user_email_exists){
                    $user_google_exists=User::where('google_id',$request->social_id)->first();
                    if( $user_google_exists){
                        
                           $token = $user_google_exists->createToken('Authtoken')->accessToken;
                     $user_google_exists->token=$token;
                   return response(['statusCode' => 200, 'message' => 'Successfully Login','data'=>  $user_google_exists]); 
                        
                    }else{
                      
                        
                        
                         $user_email_exists->google_id=$request->social_id;
                         $user_email_exists->update();
                          $token = $user_email_exists->createToken('Authtoken')->accessToken;
                     $user_email_exists->token=$token;
                     
                       return response(['statusCode' => 200, 'message' => 'Successfully Login','data'=>  $user_email_exists]); 
                    
                    }
                }else{
                    
                     $validator = Validator::make($request->all(), [ 
                //'full_name' => 'required', 
               
              'mobile' => 'required|unique:users',
               // 'user_image'=>'required', 
                'social_type'=>'required', 
                'device_token' => 'required', 
                  'category_id'=>'required',
                'device_type' => 'required', 
                'social_id'=>'required', ]); 
                if ($validator->fails())
                { 
                $messages = implode(",", $this->errorMessages($validator->errors())); 
                return response(['statusCode' => 400, 'message' => $messages]);
                }
                    
                       if($request->file('user_image')){
                    $file= $request->file('user_image');
                    $filename= date('YmdHi').$file->getClientOriginalName();
                    $file-> move(public_path('user_image'), $filename);
                   
                          }else{
                               $filename="";
                          }
                      $save_user_detail=    new User();
       
                 $save_user_detail->full_name=!empty($request->full_name)?$request->full_name:"";
                 $save_user_detail->email=$request->email;
                 $save_user_detail->mobile=$request->mobile;
                 $save_user_detail->google_id=($request->social_id);
                 $save_user_detail->role_name='user';
                    $save_user_detail->course_category_id=$request->category_id;
                 $save_user_detail->role_id=1;
               
                     
                  $save_user_detail->about="cxzxc";
                $save_user_detail->offline_message="0";
                 $save_user_detail->profile_image=$filename;
                 $save_user_detail->created_at=time();
              
                $save_user_detail->save();
                
                 $token = $save_user_detail->createToken('Authtoken')->accessToken;
                     $save_user_detail->token=$token;
                       return response(['statusCode' => 200, 'message' => 'Successfully Login','data'=>  $save_user_detail]); 
                
                
                }
                
                }else{ 
                    //facebook
                     $user_email_exists=User::where('email', $request->email)->first();
                if($user_email_exists){
                    $user_email_exists=User::where('facebook_id',$request->social_id)->first();
                    if( $user_email_exists){
                        
                           $token = $user_email_exists->createToken('Authtoken')->accessToken;
                     $user_email_exists->token=$token;
                   return response(['statusCode' => 200, 'message' => 'Successfully Login','data'=>  $user_email_exists]); 
                        
                    }else{
                         $user_email_exists->facebook_id=$request->social_id;
                         $user_email_exists->update();
                          $token = $user_email_exists->createToken('Authtoken')->accessToken;
                     $user_email_exists->token=$token;
                       return response(['statusCode' => 200, 'message' => 'Successfully Login','data'=>  $user_email_exists]); 
                    
                    }
                }else{
                    
                    
                    
                       $validator = Validator::make($request->all(), [ 
                'full_name' => 'required', 
               
               'mobile' => 'required|unique:users',
                'user_image'=>'required', 
                'social_type'=>'required', 
                'device_token' => 'required', 
                  'category_id'=>'required',
                'device_type' => 'required', 
                'social_id'=>'required', ]); 
                if ($validator->fails())
                { 
                $messages = implode(",", $this->errorMessages($validator->errors())); 
                return response(['statusCode' => 400, 'message' => $messages]);
                }
                
                    
                    
                       if($request->file('user_image')){
                    $file= $request->file('user_image');
                    $filename= date('YmdHi').$file->getClientOriginalName();
                    $file-> move(public_path('user_image'), $filename);
                   
                          }else{
                              $filename="";
                          }
                      $save_user_detail=    new User();
       
                 $save_user_detail->full_name=!empty($request->full_name)?$request->full_name:"";
                 $save_user_detail->email=$request->email;
                 $save_user_detail->mobile=$request->mobile;
                 $save_user_detail->facebook_id=($request->social_id);
                 $save_user_detail->role_name='user';
                
                 $save_user_detail->role_id=1;
               $save_user_detail->course_category_id=$request->category_id;
                     
                  $save_user_detail->about="cxzxc";
                $save_user_detail->offline_message="0";
                 $save_user_detail->profile_image=$filename;
                 $save_user_detail->created_at=time();
              
                $save_user_detail->save();
                
                 $token = $save_user_detail->createToken('Authtoken')->accessToken;
                     $save_user_detail->token=$token;
                       return response(['statusCode' => 200, 'message' => 'Successfully Login','data'=>  $save_user_detail]); 
                
                
                }
                }
                     }
                
        public function logout(){   
        if (Auth::check()) {
            Auth::user()->token()->revoke();
           
             return response(['statusCode' => 200, 'message' => 'logout_success']); 
        }else{
           
             return response(['statusCode' => 400, 'message' => 'Pleae try again']);
        }
        }
    
    
        public function get_profile(Request $request){
            $user_detail=User::where('id',Auth::user()->id)->first();
            
            if( $user_detail){
                  return response(['statusCode' => 200, 'message' => 'User details','data'=>$user_detail]); 
                
            }else{
                 return response(['statusCode' => 400, 'message' => 'Pleae try again']);
            }
        }
        public function update_profile(Request $request){
        $user = Auth::user();
    // dd($request->all())
        // dd($user->id);
         $profile_image = $request->profile_image;
         $full_name = $request->full_name;
          $phone_no = $request->phone_no;
       //  $full_name = $request->full_name;
       //  $save_user_detail->course_category_id=$request->category_id;
       
    //   if($request->file('profile_image') && $full_name){
    //         $file= $request->file('profile_image');
    //         $filename= date('YmdHi').$file->getClientOriginalName();
    //         $file-> move(public_path('profile_image'), $filename);  
    //     }
        
           
    
    //  dd($data);

         $category = User::find($user->id);
         $category->profile_image = $profile_image ?? $user->profile_image;
         $category->full_name  =  $full_name ?? $user->full_name;
         $category->mobile  =  $phone_no ?? $user->mobile;
         $category->save();
         if($category){
             return response(['statusCode' => 200, 'message' => 'profile  update successfully']);
         }else{
               return response(['statusCode' => 400, 'message' => 'profile not update']);
         }
    }
         public function  Signin(Request $request){
              $validator = Validator::make($request->all(), [ 
              
                'email' => 'required', 
                 'password' => 'required', 
                ]);
               
                if ($validator->fails())
                { 
                $messages = implode(",", $this->errorMessages($validator->errors())); 
                return response(['statusCode' => 400, 'message' => $messages]);
                }
        
         
        
            if (Auth::attempt([ 'email' =>$request->email,'password' => $request->password ])) {
                
               $exits_user =User::where('email',$request->email)->first();
                $token = $exits_user->createToken('Authtoken')->accessToken;
                         $exits_user->token=$token;
                           return response(['statusCode' => 200, 'message' => 'Successfully Login','data'=>  $exits_user]); 
              
            }else{
                  return response(['statusCode' => 400, 'message' => 'Wrong credential']);
            }
        
           }
    
          public function change_password(Request $request){
              
            $validator = Validator::make($request->all(), [ 
          
            'old_password' => 'required', 
             'new_password' => 'required', 
            ]);
           
            if ($validator->fails())
            { 
            $messages = implode(",", $this->errorMessages($validator->errors())); 
            return response(['statusCode' => 400, 'message' => $messages]);
            }
    

        //Change Password
        $user = Auth::user();
         
           
              if (!Hash::check($request->old_password, $user->password)) {
             
             return response(['statusCode' => 400, 'message' => 'Current password does not match!']);
            } else{
                    
            $user->password = bcrypt($request->new_password);
            $user->update();
            return response(['statusCode' => 200, 'message' => 'Password Successfully update']);
                }
                  
              }
              
              public function course_list(Request $request){
                 $course_list= Webinar::where( 'type', "course")->get();
                 if($course_list){
                      return response(['statusCode' => 200, 'message' => 'Course List','data'=>$course_list]);
                 }else{
                      return response(['statusCode' => 400, 'message' => 'No Data found']);
                 }
              }
              
              
              
              
              
              public function course_detail(Request $request){
                    $validator = Validator::make($request->all(), [ 
          
                    'sluge' => 'required', 
                   
                    ]);
                   
                    if ($validator->fails())
                    { 
                    $messages = implode(",", $this->errorMessages($validator->errors())); 
                    return response(['statusCode' => 400, 'message' => $messages]);
                    }
                    
                    
                  $slug=$request->sluge;
                   $user = null;
    
            if (auth()->check()) {
                $user = auth()->user();
            } else if (getFeaturesSettings('webinar_private_content_status')) {
                $data = [
                    'pageTitle' => trans('update.private_content'),
                    'pageRobot' => getPageRobotNoIndex(),
                ];
    
              //  return view('web.default.course.private_content', $data);
                 return response(['statusCode' => 200, 'message' => 'Course List','data'=>$data]);
            }
    
            if (!empty($user) and !$user->access_content) {
                $data = [
                    'pageTitle' => trans('update.not_access_to_content'),
                    'pageRobot' => getPageRobotNoIndex(),
                    'userNotAccess' => true
                ];
    
                // return view('web.default.course.private_content', $data);
                 return response(['statusCode' => 200, 'message' => 'Course List','data'=>$data]);
            }

        $course = Webinar::where('slug', $slug)
          
            ->where('status', 'active')
            ->first();

      
        if ($course) {
      
             return response(['statusCode' => 200, 'message' => 'Course Details','data'=>$course]);
        }else{
             return response(['statusCode' => 400, 'message' => 'No Data found']);
        }
              }
              
              
                private function checkQuizzesResults($user, $quizzes)
                {
                    $canDownloadCertificate = false;
            
                    foreach ($quizzes as $quiz) {
                        $quiz = $this->checkQuizResults($user, $quiz);
                    }
            
                    return $quizzes;
                }
                  private function checkQuizResults($user, $quiz)
    {
        $canDownloadCertificate = false;

        $canTryAgainQuiz = false;
        $userQuizDone = QuizzesResult::where('quiz_id', $quiz->id)
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        if (count($userQuizDone)) {
            $quiz->user_grade = $userQuizDone->first()->user_grade;
            $quiz->result_count = $userQuizDone->count();
            $quiz->result = $userQuizDone->first();

            $status_pass = false;
            foreach ($userQuizDone as $result) {
                if ($result->status == QuizzesResult::$passed) {
                    $status_pass = true;
                }
            }

            $quiz->result_status = $status_pass ? QuizzesResult::$passed : $userQuizDone->first()->status;

            if ($quiz->certificate and $quiz->result_status == QuizzesResult::$passed) {
                $canDownloadCertificate = true;
            }
        }

        if (!isset($quiz->attempt) or (count($userQuizDone) < $quiz->attempt and $quiz->result_status !== QuizzesResult::$passed)) {
            $canTryAgainQuiz = true;
        }

        $quiz->can_try = $canTryAgainQuiz;
        $quiz->can_download_certificate = $canDownloadCertificate;

        return $quiz;
    }
    
    public function ShareLink(Request $request){
    //   dd('hhh');
    $slug =$request->slug;
    // dd($slug);
      return  "<script>location.href='live://redirect.aimpariksha.com/AllCoursesPageDetails/$slug';</script>";
    }
  
}
