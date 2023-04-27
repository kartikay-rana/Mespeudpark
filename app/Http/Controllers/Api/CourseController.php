<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ValidationErrorTrait;
use App\Models\AdvertisingBanner;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\File;
use App\Models\QuizzesResult;
use App\Models\RewardAccounting;
use App\Models\Sale;
use App\Models\TextLesson;
use App\Models\CourseLearning;
use App\Models\Session;
use App\Models\WebinarChapter;
use App\Models\WebinarReport;
use App\Models\Webinar;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    //check_buy
    use ValidationErrorTrait;


    public function course(Request $request)
    {
        $validator = Validator::make($request->all(), [

            'slug' => 'required',
        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        // dd($user = auth()->user()->id);
        $user = null;
        $slug = $request->slug;
        $justReturnData = false;

        if (auth()->check()) {
            $user = auth()->user();
        } else if (getFeaturesSettings('webinar_private_content_status')) {
            $data = [
                'pageTitle' => trans('update.private_content'),
                'pageRobot' => getPageRobotNoIndex(),
            ];


            return response(['statusCode' => 200, 'message' => 'course detail', 'data' => $data]);
        }

        if (!empty($user) and !$user->access_content) {
            $data = [
                'pageTitle' => trans('update.not_access_to_content'),
                'pageRobot' => getPageRobotNoIndex(),
                'userNotAccess' => true
            ];


            return response(['statusCode' => 200, 'message' => 'course detail', 'data' => $data]);
        }
        
         $webinar = Webinar::where('slug', $slug)->first();
        if($webinar == null){
            return response(['statusCode' => 404, 'message' => 'course detail not found']);
        }

        $course = Webinar::where('slug', $slug)
            ->with([
                'quizzes' => function ($query) {
                    $query->where('status', 'active')
                        ->with(['quizResults', 'quizQuestions']);
                },
                'tags',
                'prerequisites' => function ($query) {
                    $query->with(['prerequisiteWebinar' => function ($query) {
                        $query->with(['teacher' => function ($qu) {
                            $qu->select('id', 'full_name', 'avatar');
                        }]);
                    }]);
                    $query->orderBy('order', 'asc');
                },
                'faqs' => function ($query) {
                    $query->orderBy('order', 'asc');
                },
                'webinarExtraDescription' => function ($query) {
                    $query->orderBy('order', 'asc');
                },
                'chapters' => function ($query) use ($user) {
                    //dd($query->first());
                    $query->where('status', WebinarChapter::$chapterActive);
                    $query->orderBy('order', 'asc');

                    $query->with([
                        'chapterItems' => function ($query) {
                            // dd($query->first());
                            $query->orderBy('order', 'asc');
                            // $query->with('textLessons');
                        }
                    ]);
                    // dd($query->first());
                },

                'files' => function ($query) use ($user) {
                    $query->join('webinar_chapters', 'webinar_chapters.id', '=', 'files.chapter_id')
                        ->select('files.*', DB::raw('webinar_chapters.order as chapterOrder'))
                        ->where('files.status', WebinarChapter::$chapterActive)
                        ->orderBy('chapterOrder', 'asc')
                        ->orderBy('files.order', 'asc')
                        ->with([
                            'learningStatus' => function ($query) use ($user) {
                                $query->where('user_id', !empty($user) ? $user->id : null);
                            }
                        ]);
                },
                'textLessons' => function ($query) use ($user) {
                    $query->where('status', WebinarChapter::$chapterActive)
                        ->withCount(['attachments'])
                        ->orderBy('order', 'asc')
                        ->with([
                            'learningStatus' => function ($query) use ($user) {
                                $query->where('user_id', !empty($user) ? $user->id : null);
                            }
                        ]);
                },
                'sessions' => function ($query) use ($user) {
                    $query->where('status', WebinarChapter::$chapterActive)
                        ->orderBy('order', 'asc')
                        ->with([
                            'learningStatus' => function ($query) use ($user) {
                                $query->where('user_id', !empty($user) ? $user->id : null);
                            }
                        ]);
                },
                'assignments' => function ($query) {
                    $query->where('status', WebinarChapter::$chapterActive);
                },
                'tickets' => function ($query) {
                    $query->orderBy('order', 'asc');
                },
                'filterOptions',
                'category',
                'teacher',
                'reviews' => function ($query) {
                    $query->where('status', 'active');
                    $query->with([
                        'comments' => function ($query) {
                            $query->where('status', 'active');
                        },
                        'creator' => function ($qu) {
                            $qu->select('id', 'full_name', 'avatar');
                        }
                    ]);
                },
                'comments' => function ($query) {
                    $query->where('status', 'active');
                    $query->whereNull('reply_id');
                    $query->with([
                        'user' => function ($query) {
                            $query->select('id', 'full_name', 'role_name', 'role_id', 'avatar');
                        },
                        'replies' => function ($query) {
                            $query->where('status', 'active');
                            $query->with([
                                'user' => function ($query) {
                                    $query->select('id', 'full_name', 'role_name', 'role_id', 'avatar');
                                }
                            ]);
                        }
                    ]);
                    $query->orderBy('created_at', 'desc');
                },
            ])
            ->withCount([
                'sales' => function ($query) {
                    $query->whereNull('refund_at');
                },
                'noticeboards'
            ])
            ->where('status', 'active')
            ->first();

        if (empty($course)) {
            return $justReturnData ? false : back();
        }

        $isPrivate = $course->private;
        if (!empty($user) and ($user->id == $course->creator_id or $user->organ_id == $course->creator_id or $user->isAdmin())) {
            $isPrivate = false;
        }

        if ($isPrivate) {
            return $justReturnData ? false : back();
        }

        $isFavorite = false;

        if (!empty($user)) {
            $isFavorite = Favorite::where('webinar_id', $course->id)
                ->where('user_id', $user->id)
                ->first();
        }

        $hasBought = $course->checkUserHasBought($user);

        $webinarContentCount = 0;
        if (!empty($course->sessions)) {
            $webinarContentCount += $course->sessions->count();
        }
        if (!empty($course->files)) {
            $webinarContentCount += $course->files->count();
        }
        if (!empty($course->textLessons)) {
            $webinarContentCount += $course->textLessons->count();
        }
        if (!empty($course->quizzes)) {
            $webinarContentCount += $course->quizzes->count();
        }
        if (!empty($course->assignments)) {
            $webinarContentCount += $course->assignments->count();
        }

        $advertisingBanners = AdvertisingBanner::where('published', true)
            ->whereIn('position', ['course', 'course_sidebar'])
            ->get();

        $sessionsWithoutChapter = $course->sessions->whereNull('chapter_id');

        $filesWithoutChapter = $course->files->whereNull('chapter_id');

        $textLessonsWithoutChapter = $course->textLessons->whereNull('chapter_id');

        $quizzes = $course->quizzes->whereNull('chapter_id');
       

        if ($user) {
            $quizzes = $this->checkQuizzesResults($user, $quizzes);


            if (!empty($course->chapters) and count($course->chapters)) {
                foreach ($course->chapters as $chapter) {
                    if (!empty($chapter->chapterItems) and count($chapter->chapterItems)) {
                        // dd($chapter->chapterItems);
                        foreach ($chapter->chapterItems as $chapterItem) {
                           //dd($chapterItem->type);
                           if($chapterItem->type == 'quiz'){
                            if (!empty($chapterItem->quiz)) {
                                $chapterItem->quiz = $this->checkQuizResults($user, $chapterItem->quiz);
                            }
                           }


                           if($chapterItem->type == 'file'){
                            $chapterItem->fileData = $this->CheckFile($chapterItem->item_id);
                           }
                           if($chapterItem->type == 'session'){
                            $chapterItem->session = $this->checkSession($chapterItem->item_id);
                           }

                        if($chapterItem->type == 'text_lesson'){
                            $chapterItem->text_lesson = $this->text_lesson($chapterItem->item_id);
                           }
                        }
                    }
                }
            }



            if (!empty($course->quizzes) and count($course->quizzes)) {
                $course->quizzes = $this->checkQuizzesResults($user, $course->quizzes);
            }
        }


        $pageRobot = getPageRobot('course_show'); // index

        $data_list = [
            'pageTitle' => $course->title,
            'pageDescription' => $course->seo_description,
            'pageRobot' => $pageRobot,
            'course' => $course,
            'isFavorite' => $isFavorite,
            'hasBought' => $hasBought,
            'user' => $user,
            'webinarContentCount' => $webinarContentCount,
            'advertisingBanners' => $advertisingBanners->where('position', 'course'),
            'advertisingBannersSidebar' => $advertisingBanners->where('position', 'course_sidebar'),
            'activeSpecialOffer' => $course->activeSpecialOffer(),
            'sessionsWithoutChapter' => $sessionsWithoutChapter,
            'filesWithoutChapter' => $filesWithoutChapter,
            'textLessonsWithoutChapter' => $textLessonsWithoutChapter,
            'quizzes' => $quizzes,
        ];

        // if ($justReturnData) {
        //     return $data;
        // }



        return response(['statusCode' => 200, 'message' => 'course detail', 'data' => $data_list]);
    }
    
     public function check_buy(Request $request)
     {
        $validator = Validator::make($request->all(), [

            'slug' => 'required',
        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        // dd($user = auth()->user()->id);
        $user = null;
        $slug = $request->slug;
        $justReturnData = false;

        if (auth()->check()) {
            $user = auth()->user();
        } else if (getFeaturesSettings('webinar_private_content_status')) {
            $data = [
                'pageTitle' => trans('update.private_content'),
                'pageRobot' => getPageRobotNoIndex(),
            ];


            return response(['statusCode' => 200, 'message' => 'course detail', 'data' => $data]);
        }

        if (!empty($user) and !$user->access_content) {
            $data = [
                'pageTitle' => trans('update.not_access_to_content'),
                'pageRobot' => getPageRobotNoIndex(),
                'userNotAccess' => true
            ];


            return response(['statusCode' => 200, 'message' => 'course detail', 'data' => $data]);
        }
        
         $webinar = Webinar::where('slug', $slug)->first();
        if($webinar == null){
            return response(['statusCode' => 404, 'message' => 'course detail not found']);
        }

        $course = Webinar::where('slug', $slug)
            ->with([
                'quizzes' => function ($query) {
                    $query->where('status', 'active')
                        ->with(['quizResults', 'quizQuestions']);
                },
                'tags',
                'prerequisites' => function ($query) {
                    $query->with(['prerequisiteWebinar' => function ($query) {
                        $query->with(['teacher' => function ($qu) {
                            $qu->select('id', 'full_name', 'avatar');
                        }]);
                    }]);
                    $query->orderBy('order', 'asc');
                },
                'faqs' => function ($query) {
                    $query->orderBy('order', 'asc');
                },
                'webinarExtraDescription' => function ($query) {
                    $query->orderBy('order', 'asc');
                },
                'chapters' => function ($query) use ($user) {
                    //dd($query->first());
                    $query->where('status', WebinarChapter::$chapterActive);
                    $query->orderBy('order', 'asc');

                    $query->with([
                        'chapterItems' => function ($query) {
                            // dd($query->first());
                            $query->orderBy('order', 'asc');
                            // $query->with('textLessons');
                        }
                    ]);
                    // dd($query->first());
                },

                'files' => function ($query) use ($user) {
                    $query->join('webinar_chapters', 'webinar_chapters.id', '=', 'files.chapter_id')
                        ->select('files.*', DB::raw('webinar_chapters.order as chapterOrder'))
                        ->where('files.status', WebinarChapter::$chapterActive)
                        ->orderBy('chapterOrder', 'asc')
                        ->orderBy('files.order', 'asc')
                        ->with([
                            'learningStatus' => function ($query) use ($user) {
                                $query->where('user_id', !empty($user) ? $user->id : null);
                            }
                        ]);
                },
                'textLessons' => function ($query) use ($user) {
                    $query->where('status', WebinarChapter::$chapterActive)
                        ->withCount(['attachments'])
                        ->orderBy('order', 'asc')
                        ->with([
                            'learningStatus' => function ($query) use ($user) {
                                $query->where('user_id', !empty($user) ? $user->id : null);
                            }
                        ]);
                },
                'sessions' => function ($query) use ($user) {
                    $query->where('status', WebinarChapter::$chapterActive)
                        ->orderBy('order', 'asc')
                        ->with([
                            'learningStatus' => function ($query) use ($user) {
                                $query->where('user_id', !empty($user) ? $user->id : null);
                            }
                        ]);
                },
                'assignments' => function ($query) {
                    $query->where('status', WebinarChapter::$chapterActive);
                },
                'tickets' => function ($query) {
                    $query->orderBy('order', 'asc');
                },
                'filterOptions',
                'category',
                'teacher',
                'reviews' => function ($query) {
                    $query->where('status', 'active');
                    $query->with([
                        'comments' => function ($query) {
                            $query->where('status', 'active');
                        },
                        'creator' => function ($qu) {
                            $qu->select('id', 'full_name', 'avatar');
                        }
                    ]);
                },
                'comments' => function ($query) {
                    $query->where('status', 'active');
                    $query->whereNull('reply_id');
                    $query->with([
                        'user' => function ($query) {
                            $query->select('id', 'full_name', 'role_name', 'role_id', 'avatar');
                        },
                        'replies' => function ($query) {
                            $query->where('status', 'active');
                            $query->with([
                                'user' => function ($query) {
                                    $query->select('id', 'full_name', 'role_name', 'role_id', 'avatar');
                                }
                            ]);
                        }
                    ]);
                    $query->orderBy('created_at', 'desc');
                },
            ])
            ->withCount([
                'sales' => function ($query) {
                    $query->whereNull('refund_at');
                },
                'noticeboards'
            ])
            ->where('status', 'active')
            ->first();

        if (empty($course)) {
            return $justReturnData ? false : back();
        }

        $isPrivate = $course->private;
        if (!empty($user) and ($user->id == $course->creator_id or $user->organ_id == $course->creator_id or $user->isAdmin())) {
            $isPrivate = false;
        }

        if ($isPrivate) {
            return $justReturnData ? false : back();
        }

      

        $hasBought = $course->checkUserHasBought($user);

       


        // = getPageRobot('course_show'); // index

        $data_list = [
            // 'pageTitle' => $course->title,
            // 'pageDescription' => $course->seo_description,
            // 'pageRobot' => $pageRobot,
            // 'course' => $course,
            // 'isFavorite' => $isFavorite,
            'hasBought' => $hasBought,
            // 'user' => $user,
            // 'webinarContentCount' => $webinarContentCount,
            // 'advertisingBanners' => $advertisingBanners->where('position', 'course'),
            // 'advertisingBannersSidebar' => $advertisingBanners->where('position', 'course_sidebar'),
            // 'activeSpecialOffer' => $course->activeSpecialOffer(),
            // 'sessionsWithoutChapter' => $sessionsWithoutChapter,
            // 'filesWithoutChapter' => $filesWithoutChapter,
            // 'textLessonsWithoutChapter' => $textLessonsWithoutChapter,
            // 'quizzes' => $quizzes,
        ];

        // if ($justReturnData) {
        //     return $data;
        // }



        return response(['statusCode' => 200, 'message' => 'course detail', 'data' => $data_list]);
    }
    private function checkQuizzesResults($user, $quizzes)
    {
        $canDownloadCertificate = false;

        foreach ($quizzes as $quiz) {

            $quiz = $this->checkQuizResults($user, $quiz);
        }


        return $quizzes;
    }

    public function CheckFile($id)
    {
        $canTryAgainQuiz = false;
        $files = File::where('id', $id)
            ->first();
           return $files;

    }

    private function checkSession($id)
    {

        $sessions = Session::where('id',$id)->first();

        return $sessions;
    }
    
    private function text_lesson($id)
    {
        //dd('hello',$id);
        $sessions = TextLesson::where('id',$id)->first();

        return $sessions;
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
    
    
    public function previousyearpaper(Request $request)
    {   //dd('hello');
       // $this->authorize('admin_webinars_list');

        removeContentLocale();
        $sub_id = Category::where('parent_id',Auth::user()->course_category_id)->pluck('id');
       // dd($sub_id,Auth::user()->course_category_id);
        $type = $request->get('type', 'webinar');
        $query = Webinar::where('webinars.type', $type)->whereIn('category_id',  $sub_id)->with('tags');

        $totalWebinars = $query->count();
        $totalPendingWebinars = deepClone($query)->where('webinars.status', 'pending')->count();
        $totalDurations = deepClone($query)->sum('duration');
        $totalSales = deepClone($query)->join('sales', 'webinars.id', '=', 'sales.webinar_id')
            ->select(DB::raw('count(sales.webinar_id) as sales_count'))
            ->whereNotNull('sales.webinar_id')
            ->whereNull('sales.refund_at')
            ->first();

        $categories = Category::where('parent_id', null)
            ->with('subCategories')
            ->get();

        $inProgressWebinars = 0;
        if ($type == 'webinar') {
            $inProgressWebinars = $this->getInProgressWebinarsCount();
        }

        // $query = $this->filterWebinar($query, $request)
        //     ->with([
        //         'category',
        //         'teacher' => function ($qu) {
        //             $qu->select('id', 'full_name');
        //         },
        //         'sales' => function ($query) {
        //             $query->whereNull('refund_at');
        //         }
        //     ]);

        $webinars = $query->paginate(10);

        if ($request->get('status', null) == 'active_finished') {
            foreach ($webinars as $key => $webinar) {
                if ($webinar->last_date > time()) { // is in progress
                    unset($webinars[$key]);
                }
            }
        }

        $data = [
           // 'pageTitle' => trans('admin/pages/webinars.webinars_list_page_title'),
            'webinars' => $webinars,
            'totalWebinars' => $totalWebinars,
            'totalPendingWebinars' => $totalPendingWebinars,
            'totalDurations' => $totalDurations,
            'totalSales' => !empty($totalSales) ? $totalSales->sales_count : 0,
           // 'categories' => $categories,
            'inProgressWebinars' => $inProgressWebinars,
            'classesType' => $type,
        ];
        
        //dd($data);

        $teacher_ids = $request->get('teacher_ids', null);
        if (!empty($teacher_ids)) {
            $data['teachers'] = User::select('id', 'full_name')->whereIn('id', $teacher_ids)->get();
        }

        //return view('admin.webinars.lists', $data);
        return $data;
    }


}
