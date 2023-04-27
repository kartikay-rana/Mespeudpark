<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\Reward;
use App\Models\RewardAccounting;
use App\Models\Role;
use App\Models\Translation\QuizTranslation;
use App\Models\WebinarChapter;
use App\Models\WebinarChapterItem;
use App\User;
use App\Models\Webinar;
use App\Models\QuizzesResult;
use App\Models\QuizzesQuestion;
use App\Models\QuizzesQuestionsAnswer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $allQuizzesLists = Quiz::select('id', 'webinar_title')
            ->where('creator_id', $user->id)
            ->where('status', 'active')
            ->get();


        $query = Quiz::where('creator_id', $user->id);

        $quizzesCount = deepClone($query)->count();

        $quizFilters = $this->filters($request, $query);

        $quizzes = $quizFilters->with([
            'webinar',
            'quizQuestions',
            'quizResults',
        ])->orderBy('created_at', 'desc')
            ->orderBy('updated_at', 'desc')
            ->paginate(10);

        $userSuccessRate = [];
        $questionsCount = 0;
        $userCount = 0;

        foreach ($quizzes as $quiz) {

            $countSuccess = $quiz->quizResults
                ->where('status', \App\Models\QuizzesResult::$passed)
                ->pluck('user_id')
                ->count();

            $rate = 0;
            if ($countSuccess) {
                $rate = round($countSuccess / $quiz->quizResults->count() * 100);
            }

            $quiz->userSuccessRate = $rate;

            $questionsCount += $quiz->quizQuestions->count();
            $userCount += $quiz->quizResults
                ->pluck('user_id')
                ->count();
        }

        $data = [
            'pageTitle' => trans('quiz.quizzes_list_page_title'),
            'quizzes' => $quizzes,
            'userSuccessRate' => $userSuccessRate,
            'questionsCount' => $questionsCount,
            'quizzesCount' => $quizzesCount,
            'userCount' => $userCount,
            'allQuizzesLists' => $allQuizzesLists
        ];

        return view(getTemplate() . '.panel.quizzes.lists', $data);
    }

    public function filters(Request $request, $query)
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $quiz_id = $request->get('quiz_id');
        $total_mark = $request->get('total_mark');
        $status = $request->get('status');
        $active_quizzes = $request->get('active_quizzes');


        $query = fromAndToDateFilter($from, $to, $query, 'created_at');

        if (!empty($quiz_id) and $quiz_id != 'all') {
            $query->where('id', $quiz_id);
        }

        if ($status and $status !== 'all') {
            $query->where('status', strtolower($status));
        }

        if (!empty($active_quizzes)) {
            $query->where('status', 'active');
        }

        if ($total_mark) {
            $query->where('total_mark', '>=', $total_mark);
        }

        return $query;
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $webinars = Webinar::where(function ($query) use ($user) {
            $query->where('teacher_id', $user->id)
                ->orWhere('creator_id', $user->id);
        })->get();

        $locale = $request->get('locale', app()->getLocale());

        $data = [
            'pageTitle' => trans('quiz.new_quiz_page_title'),
            'webinars' => $webinars,
            'userLanguages' => getUserLanguagesLists(),
            'locale' => mb_strtolower($locale),
            'defaultLocale' => getDefaultLocale(),
        ];

        return view(getTemplate() . '.panel.quizzes.create', $data);
    }

    public function store(Request $request)
    {
        $data = $request->get('ajax');
        $rules = [
            'title' => 'required|max:255',
            'webinar_id' => 'nullable',
            'pass_mark' => 'required',
        ];

        if ($request->ajax()) {
            $validate = Validator::make($data, $rules);

            if ($validate->fails()) {
                return response()->json([
                    'code' => 422,
                    'errors' => $validate->errors()
                ], 422);
            }
        } else {
            $this->validate($request, $rules);
        }

        $user = auth()->user();

        $webinar = null;
        $chapter = null;
        if (!empty($data['webinar_id'])) {
            $webinar = Webinar::where('id', $data['webinar_id'])
                ->where(function ($query) use ($user) {
                    $query->where('teacher_id', $user->id)
                        ->orWhere('creator_id', $user->id);
                })->first();

            if (!empty($webinar) and !empty($data['chapter_id'])) {
                $chapter = WebinarChapter::where('id', $data['chapter_id'])
                    ->where('webinar_id', $webinar->id)
                    ->first();
            }
        }

        $quiz = Quiz::create([
            'webinar_id' => !empty($webinar) ? $webinar->id : null,
            'chapter_id' => !empty($chapter) ? $chapter->id : null,
            'creator_id' => $user->id,
            'webinar_title' => !empty($webinar) ? $webinar->title : null,
            'attempt' => $data['attempt'] ?? null,
            'pass_mark' => $data['pass_mark'],
            'time' => $data['time'] ?? null,
            'status' => (!empty($data['status']) and $data['status'] == 'on') ? Quiz::ACTIVE : Quiz::INACTIVE,
            'certificate' => (!empty($data['certificate']) and $data['certificate'] == 'on') ? true : false,
            'created_at' => time(),
        ]);

        if (!empty($quiz)) {
            QuizTranslation::updateOrCreate([
                'quiz_id' => $quiz->id,
                'locale' => mb_strtolower($data['locale']),
            ], [
                'title' => $data['title'],
            ]);

            if (!empty($quiz->chapter_id)) {
                WebinarChapterItem::makeItem($user->id, $quiz->chapter_id, $quiz->id, WebinarChapterItem::$chapterQuiz);
            }
        }

        if ($request->ajax()) {

            $redirectUrl = '';

            if (empty($data['is_webinar_page'])) {
                $redirectUrl = '/panel/quizzes/' . $quiz->id . '/edit';
            }

            return response()->json([
                'code' => 200,
                'redirect_url' => $redirectUrl
            ]);
        } else {
            return redirect()->route('panel_edit_quiz', ['id' => $quiz->id]);
        }
    }

    public function edit(Request $request, $id)
    {
        $user = auth()->user();
        $webinars = Webinar::where(function ($query) use ($user) {
            $query->where('teacher_id', $user->id)
                ->orWhere('creator_id', $user->id);
        })->get();

        $quiz = Quiz::where('id', $id)
            ->where('creator_id', $user->id)
            ->with([
                'quizQuestions' => function ($query) {
                    $query->with('quizzesQuestionsAnswers');
                },
            ])->first();

        if (!empty($quiz)) {
            $chapters = collect();

            if (!empty($quiz->webinar)) {
                $chapters = $quiz->webinar->chapters;
            }

            $locale = $request->get('locale', app()->getLocale());

            $data = [
                'pageTitle' => trans('public.edit') . ' ' . $quiz->title,
                'webinars' => $webinars,
                'quiz' => $quiz,
                'quizQuestions' => $quiz->quizQuestions,
                'chapters' => $chapters,
                'userLanguages' => getUserLanguagesLists(),
                'locale' => mb_strtolower($locale),
                'defaultLocale' => getDefaultLocale(),
            ];

            return view(getTemplate() . '.panel.quizzes.create', $data);
        }

        abort(404);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'title' => 'required|max:255',
            'webinar_id' => 'nullable',
            'pass_mark' => 'required',
        ];
        $data = $request->get('ajax');

        if ($request->ajax()) {
            $validate = Validator::make($data, $rules);

            if ($validate->fails()) {
                return response()->json([
                    'code' => 422,
                    'errors' => $validate->errors()
                ], 422);
            }
        } else {
            $this->validate($request, $rules);
        }

        $user = auth()->user();

        $webinar = null;
        if (!empty($data['webinar_id'])) {
            $webinar = Webinar::where('id', $data['webinar_id'])
                ->where(function ($query) use ($user) {
                    $query->where('teacher_id', $user->id)
                        ->orWhere('creator_id', $user->id);
                })->first();

            if (!empty($webinar) and !empty($data['chapter_id'])) {
                $chapter = WebinarChapter::where('id', $data['chapter_id'])
                    ->where('webinar_id', $webinar->id)
                    ->first();
            }
        }

        $quiz = Quiz::find($id);
        $quiz->update([
            'webinar_id' => !empty($webinar) ? $webinar->id : null,
            'chapter_id' => !empty($chapter) ? $chapter->id : null,
            'webinar_title' => !empty($webinar) ? $webinar->title : null,
            'attempt' => $data['attempt'] ?? null,
            'pass_mark' => $data['pass_mark'],
            'time' => $data['time'] ?? null,
            'status' => (!empty($data['status']) and $data['status'] == 'on') ? Quiz::ACTIVE : Quiz::INACTIVE,
            'certificate' => (!empty($data['certificate']) and $data['certificate'] == 'on') ? true : false,
            'updated_at' => time(),
        ]);


        $checkChapterItem = WebinarChapterItem::where('user_id', $user->id)
            ->where('item_id', $quiz->id)
            ->where('type', WebinarChapterItem::$chapterQuiz)
            ->first();

        if (!empty($quiz->chapter_id)) {
            if (empty($checkChapterItem)) {
                WebinarChapterItem::makeItem($user->id, $quiz->chapter_id, $quiz->id, WebinarChapterItem::$chapterQuiz);
            } elseif ($checkChapterItem->chapter_id != $quiz->chapter_id) {
                $checkChapterItem->delete(); // remove quiz from old chapter and assign it to new chapter

                WebinarChapterItem::makeItem($user->id, $quiz->chapter_id, $quiz->id, WebinarChapterItem::$chapterQuiz);
            }
        } else if (!empty($checkChapterItem)) {
            $checkChapterItem->delete();
        }

        QuizTranslation::updateOrCreate([
            'quiz_id' => $quiz->id,
            'locale' => mb_strtolower($data['locale']),
        ], [
            'title' => $data['title'],
        ]);

        if ($request->ajax()) {
            return response()->json([
                'code' => 200
            ]);
        } else {
            return redirect('panel/quizzes');
        }
    }

    public function destroy(Request $request, $id)
    {
        $user_id = auth()->id();
        $quiz = Quiz::where('id', $id)
            ->where('creator_id', $user_id)
            ->first();

        if (!empty($quiz)) {

            if ($quiz->delete()) {
                $checkChapterItem = WebinarChapterItem::where('user_id', $user_id)
                    ->where('item_id', $id)
                    ->where('type', WebinarChapterItem::$chapterQuiz)
                    ->first();

                if (!empty($checkChapterItem)) {
                    $checkChapterItem->delete();
                }

                return response()->json([
                    'code' => 200
                ], 200);
            }
        }

        return response()->json([], 422);
    }

    public function start(Request $request, $id)
    {
        $quiz = Quiz::where('id', $id)
            ->with([
                'quizQuestions' => function ($query) {
                    $query->with('quizzesQuestionsAnswers');
                },
            ])
            ->first();
            //    dd($quiz);
        $user = auth()->user();

        if ($quiz) {
            $userQuizDone = QuizzesResult::where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->get();
                // dd($userQuizDone);
            $status_pass = false;
            foreach ($userQuizDone as $result) {
                if ($result->status == QuizzesResult::$passed) {
                    $status_pass = true;
                }
            }

            if (!isset($quiz->attempt) or ($userQuizDone->count() < $quiz->attempt)) {
                $newQuizStart = QuizzesResult::create([
                    'quiz_id' => $quiz->id,
                    'user_id' => $user->id,
                    'course_id' => $request->course_id ?? null,
                    'results' => '',
                    'user_grade' => 0,
                    'status' => 'waiting',
                    'created_at' => time()
                ]);

                $data = [
                    'pageTitle' => trans('quiz.quiz_start'),
                    'quiz' => $quiz,
                    'quizQuestions' => $quiz->quizQuestions,
                    'attempt_count' => $userQuizDone->count() + 1,
                    'newQuizStart' => $newQuizStart
                ];

                if($request->device_type == 'android' OR $request->device_type == 'iso'){
                    //$data =  $this->status($quizResultId,$request);

                      return response(['statusCode' => 200, 'message' => 'quizzes start', 'data' => ['attempt_count' => $userQuizDone->count() + 1,
                    'newQuizStart' => $newQuizStart]]);
                  }

                return view(getTemplate() . '.panel.quizzes.start', $data);
            } else {
                if($request->device_type == 'android' OR $request->device_type == 'iso'){
                    //$data =  $this->status($quizResultId,$request);

                      return response(['statusCode' => 404, 'message' => 'cant_start_quiz']);
                  }
                return back()->with('msg', trans('quiz.cant_start_quiz'));
            }
        }
        abort(404);
    }

    public function quizzesStoreResult(Request $request, $id)
    { //dd($request->all());
        $user = auth()->user();
        // dd($user);
        $quiz = Quiz::where('id', $id)->first();
        $negative_mark = $quiz->negative_mark;
        $course_id = $request->course_id;
        if($negative_mark == null){
            $negative_mark = 0;
        }
        //dd($quiz);
        if ($quiz) {
            $results = $request->get('question');
            //dd( count($results),'jkkk');
            $total_play_question = count($results);
            $quizResultId = $request->get('quiz_result_id');
      //dd( count($results),'jkkk',$quizResultId);
            if (!empty($quizResultId)) {

                $quizResult = QuizzesResult::where('id', $quizResultId)
                    ->where('user_id', $user->id)
                    ->first();

                if (!empty($quizResult)) {
                    $passMark = $quiz->pass_mark;
                    $totalMark = 0;
                    $status = '';
                    $trueQustion =0;
                    $falseQuestion =0;

                    if (!empty($results)) {
                        foreach ($results as $questionId => $result) {
                            //dd($results);
                            if (!is_array($result)) {
                                unset($results[$questionId]);

                            } else {

                                $question = QuizzesQuestion::where('id', $questionId)
                                    ->where('quiz_id', $quiz->id)
                                    ->first();

                                if ($question and !empty($result['answer'])) {
                                    $answer = QuizzesQuestionsAnswer::where('id', $result['answer'])
                                        ->where('question_id', $question->id)
                                        ->where('creator_id', $quiz->creator_id)
                                        ->first();

                                    $results[$questionId]['status'] = false;
                                    $results[$questionId]['grade'] = $question->grade;
                                  //  dd($results);
                                    if ($answer and $answer->correct) {
                                        $results[$questionId]['status'] = true;
                                        $totalMark += (int)$question->grade;
                                        //dd($results,'jjj');
                                        $trueQustion +=1;
                                    }

                                    if ($question->type == 'descriptive') {
                                        $status = 'waiting';
                                    }
                                }
                            }
                        }
                         //dd(($results[$questionId]['status']));
                    }
                         $falseQuestion = $total_play_question - $trueQustion;
                    $negative_mark = $falseQuestion * $negative_mark;
                
                   $totalMark=  $totalMark - $negative_mark;
                    if (empty($status)) {
                        $status = ($totalMark >= $passMark) ? QuizzesResult::$passed : QuizzesResult::$failed;
                    }
                    
                   
                    //   dd($results,$trueQustion,$falseQuestion,$totalMark,$quiz,$negative_mark);
                   
                    $results["attempt_number"] = $request->get('attempt_number');
                    // dd($totalMark);
                //   // $ddd = json_encode($results);
                //   // dd(count(($ddd)));
                //   foreach($results as $key=>$val){
                //       //dd( $key);
                //       $data1 = $val;
                //       foreach($val as $k=>$v){
                //          dd(($val));
                //       }
                //       dd($data1);
                //   }
                    // dd([json_decode
                    //     'results' => json_encode($results),
                    //     'user_grade' => $totalMark,
                    //     'status' => $status,
                    //     'created_at' => time()
                    // ]);
                    
                    $quizResult->update([
                        'results' => json_encode($results),
                        'user_grade' => $totalMark,
                        'course_id' => $course_id ?? null,
                        'attempt_no' => $request->get('attempt_number') ?? null,
                        'status' => $status,
                        'created_at' => time()
                    ]);

                    if ($quizResult->status == QuizzesResult::$waiting) {
                        $notifyOptions = [
                            '[c.title]' => $quiz->webinar_title,
                            '[student.name]' => $user->full_name,
                            '[q.title]' => $quiz->title,
                        ];
                        sendNotification('waiting_quiz', $notifyOptions, $quiz->creator_id);
                    }

                    if ($quizResult->status == QuizzesResult::$passed) {
                        $passTheQuizReward = RewardAccounting::calculateScore(Reward::PASS_THE_QUIZ);
                        RewardAccounting::makeRewardAccounting($quizResult->user_id, $passTheQuizReward, Reward::PASS_THE_QUIZ, $quiz->id, true);

                        if ($quiz->certificate) {
                            $certificateReward = RewardAccounting::calculateScore(Reward::CERTIFICATE);
                            RewardAccounting::makeRewardAccounting($quizResult->user_id, $certificateReward, Reward::CERTIFICATE, $quiz->id, true);
                        }
                    }

                    if($request->device_type == 'android' OR $request->device_type == 'iso'){
                      $data =  $this->status($quizResultId,$request);
                        return response(['statusCode' => 200, 'message' => 'quizResultId', 'data' => $data]);
                    }
                    return redirect()->route('quiz_status', ['quizResultId' => $quizResult]);
                }
            }
        }
        abort(404);
    }

    public function status($quizResultId ,$request = null)
    { //dd($quizResultId);
        $user = auth()->user();

        $quizResult = QuizzesResult::where('id', $quizResultId)
            ->where('user_id', $user->id)
            ->with(['quiz' => function ($query) {
                $query->with(['quizQuestions']);
            }])
            ->first();
           // dd($quizResult);
        if ($quizResult) {
            $quiz = $quizResult->quiz;
            $attemptCount = $quiz->attempt;

            $userQuizDone = QuizzesResult::where('quiz_id', $quiz->id)
                ->where('user_id', $user->id)
                ->count();

            $canTryAgain = false;
            if ($userQuizDone < $attemptCount) {
                $canTryAgain = true;
            }

            $data = [
                'pageTitle' => trans('quiz.quiz_status'),
                'quizResult' => $quizResult,
                'quiz' => $quiz,
                'quizQuestions' => $quiz->quizQuestions,
                'attempt_count' => $userQuizDone,
                'canTryAgain' => $canTryAgain,
            ];

           if($request){
            if($request->device_type == 'android' OR $request->device_type == 'iso'){

                return $data;
            }

           }

            return view(getTemplate() . '.panel.quizzes.status', $data);
        }
        abort(404);
    }

    public function myResults(Request $request)
    {   //dd(auth()->user()->id);
        //dd($request->all());
        $query = QuizzesResult::where('user_id', auth()->user()->id);

        $quizResultsCount = deepClone($query)->count();
        $passedCount = deepClone($query)->where('status', \App\Models\QuizzesResult::$passed)->count();
        $failedCount = deepClone($query)->where('status', \App\Models\QuizzesResult::$failed)->count();
        $waitingCount = deepClone($query)->where('status', \App\Models\QuizzesResult::$waiting)->count();

        $query = $this->resultFilters($request, deepClone($query));

        $quizResults = $query->with([
            'quiz' => function ($query) {
                $query->with(['quizQuestions', 'creator', 'webinar']);
            }
        ])->orderBy('created_at', 'desc')
            ->paginate(10);

        foreach ($quizResults->groupBy('quiz_id') as $quiz_id => $quizResult) {
            $canTryAgainQuiz = false;

            $result = $quizResult->first();
            $quiz = $result->quiz;

            if (!isset($quiz->attempt) or (count($quizResult) < $quiz->attempt and $result->status !== 'passed')) {
                $canTryAgainQuiz = true;
            }

            foreach ($quizResult as $item) {
                $item->can_try = $canTryAgainQuiz;
                if ($canTryAgainQuiz and isset($quiz->attempt)) {
                    $item->count_can_try = $quiz->attempt - count($quizResult);
                }
            }
        }

        $data = [
            'pageTitle' => trans('quiz.my_results'),
            'quizzesResults' => $quizResults,
            'quizzesResultsCount' => $quizResultsCount,
            'passedCount' => $passedCount,
            'failedCount' => $failedCount,
            'waitingCount' => $waitingCount
        ];
        if($request->device_type == 'phone'){
            return response(['statusCode' => 200, 'message' => 'My result get', 'data' => $data]);
        }
        return view(getTemplate() . '.panel.quizzes.my_results', $data);
    }

    public function opens(Request $request)
    { //dd('hello');
        $user = auth()->user();
            // dd($user);
        $webinarIds = $user->getPurchasedCoursesIds();
        // dd($webinarIds);
        $query = Quiz::whereIn('webinar_id', $webinarIds)
            ->where('status', 'active')
            ->whereNotIn('q_type',['section']);
            // ->whereDoesntHave('quizResults', function ($query) use ($user) {
            //     $query->where('user_id', $user->id);
            // }); 
            // dd($query);

        $query = $this->resultFilters($request, deepClone($query));

        $quizzes = $query->orderBy('created_at', 'desc')
            ->paginate(10);

        $data = [
            'pageTitle' => trans('quiz.open_quizzes'),
            'quizzes' => $quizzes
        ];
        if($request->device_type == 'phone'){
            return response(['statusCode' => 200, 'message' => 'Quiz open', 'data' => $data]);
        }
        return view(getTemplate() . '.panel.quizzes.opens', $data);
    }

    public function results(Request $request)
    {
        $user = auth()->user();

        if (!$user->isUser()) {
            $quizzes = Quiz::where('creator_id', $user->id)
                ->where('status', 'active')
                ->get();

            $quizzesIds = $quizzes->pluck('id')->toArray();

            $query = QuizzesResult::whereIn('quiz_id', $quizzesIds);

            $studentsIds = $query->pluck('user_id')->toArray();
            $allStudents = User::select('id', 'full_name')->whereIn('id', $studentsIds)->get();

            $quizResultsCount = $query->count();
            $quizAvgGrad = round($query->avg('user_grade'), 2);
            $waitingCount = deepClone($query)->where('status', \App\Models\QuizzesResult::$waiting)->count();
            $passedCount = deepClone($query)->where('status', \App\Models\QuizzesResult::$passed)->count();
            $successRate = ($quizResultsCount > 0) ? round($passedCount / $quizResultsCount * 100) : 0;

            $query = $this->resultFilters($request, deepClone($query));

            $quizzesResults = $query->with([
                'quiz' => function ($query) {
                    $query->with(['quizQuestions', 'creator', 'webinar']);
                }, 'user'
            ])->orderBy('created_at', 'desc')
                ->paginate(10);

            $data = [
                'pageTitle' => trans('quiz.results'),
                'quizzesResults' => $quizzesResults,
                'quizResultsCount' => $quizResultsCount,
                'successRate' => $successRate,
                'quizAvgGrad' => $quizAvgGrad,
                'waitingCount' => $waitingCount,
                'quizzes' => $quizzes,
                'allStudents' => $allStudents
            ];

            return view(getTemplate() . '.panel.quizzes.results', $data);
        }

        abort(404);
    }

    public function resultFilters(Request $request, $query)
    {
        $from = $request->get('from', null);
        $to = $request->get('to', null);
        $quiz_id = $request->get('quiz_id', null);
        $total_mark = $request->get('total_mark', null);
        $status = $request->get('status', null);
        $user_id = $request->get('user_id', null);
        $instructor = $request->get('instructor', null);
        $open_results = $request->get('open_results', null);

        $query = fromAndToDateFilter($from, $to, $query, 'created_at');

        if (!empty($quiz_id) and $quiz_id != 'all') {
            $query->where('quiz_id', $quiz_id);
        }

        if ($total_mark) {
            $query->where('total_mark', $total_mark);
        }

        if (!empty($user_id) and $user_id != 'all') {
            $query->where('user_id', $user_id);
        }

        if ($instructor) {
            $userIds = User::whereIn('role_name', [Role::$teacher, Role::$organization])
                ->where('full_name', 'like', '%' . $instructor . '%')
                ->pluck('id')->toArray();

            $query->whereIn('creator_id', $userIds);
        }

        if ($status and $status != 'all') {
            $query->where('status', strtolower($status));
        }

        if (!empty($open_results)) {
            $query->where('status', 'waiting');
        }

        return $query;
    }

    public function showResult($quizResultId)
    {
        $user = auth()->user();

        $quizzesIds = Quiz::where('creator_id', $user->id)->pluck('id')->toArray();

        $quizResult = QuizzesResult::where('id', $quizResultId)
            ->where(function ($query) use ($user, $quizzesIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('quiz_id', $quizzesIds);
            })->with([
                'quiz' => function ($query) {
                    $query->with(['quizQuestions', 'webinar']);
                }
            ])->first();

        if (!empty($quizResult)) {
            $numberOfAttempt = QuizzesResult::where('quiz_id', $quizResult->quiz->id)
                ->where('user_id', $quizResult->user_id)
                ->count();

            $data = [
                'pageTitle' => trans('quiz.result'),
                'quizResult' => $quizResult,
                'userAnswers' => json_decode($quizResult->results, true),
                'numberOfAttempt' => $numberOfAttempt,
                'questionsSumGrade' => $quizResult->quiz->quizQuestions->sum('grade'),
            ];

            return view(getTemplate() . '.panel.quizzes.quiz_result', $data);
        }

        abort(404);
    }

    public function destroyQuizResult($quizResultId)
    {
        $user = auth()->user();

        $quizzesIds = Quiz::where('creator_id', $user->id)->pluck('id')->toArray();

        $quizResult = QuizzesResult::where('id', $quizResultId)
            ->whereIn('quiz_id', $quizzesIds)
            ->first();

        if (!empty($quizResult)) {
            $quizResult->delete();

            return response()->json([
                'code' => 200
            ], 200);
        }

        return response()->json([], 422);
    }

    public function editResult($quizResultId)
    {
        $user = auth()->user();

        $quizzesIds = Quiz::where('creator_id', $user->id)->pluck('id')->toArray();

        $quizResult = QuizzesResult::where('id', $quizResultId)
            ->whereIn('quiz_id', $quizzesIds)
            ->with([
                'quiz' => function ($query) {
                    $query->with([
                        'quizQuestions' => function ($query) {
                            $query->orderBy('type', 'desc');
                        },
                        'webinar'
                    ]);
                }
            ])->first();

        if (!empty($quizResult)) {
            $numberOfAttempt = QuizzesResult::where('quiz_id', $quizResult->quiz->id)
                ->where('user_id', $quizResult->user_id)
                ->count();

            $data = [
                'pageTitle' => trans('quiz.result'),
                'teacherReviews' => true,
                'quizResult' => $quizResult,
                'newQuizStart' => $quizResult,
                'userAnswers' => json_decode($quizResult->results, true),
                'numberOfAttempt' => $numberOfAttempt,
                'questionsSumGrade' => $quizResult->quiz->quizQuestions->sum('grade'),
            ];

            return view(getTemplate() . '.panel.quizzes.quiz_result', $data);
        }

        abort(404);
    }

    public function updateResult(Request $request, $id)
    {
        $user = auth()->user();
        $quiz = Quiz::where('id', $id)
            ->where('creator_id', $user->id)
            ->first();

        if (!empty($quiz)) {
            $reviews = $request->get('question');
            $quizResultId = $request->get('quiz_result_id');

            if (!empty($quizResultId)) {

                $quizResult = QuizzesResult::where('id', $quizResultId)
                    ->where('quiz_id', $quiz->id)
                    ->first();

                if (!empty($quizResult)) {

                    $oldResults = json_decode($quizResult->results, true);
                    $totalMark = 0;
                    $status = '';
                    $user_grade = $quizResult->user_grade;

                    if (!empty($oldResults) and count($oldResults)) {
                        foreach ($oldResults as $question_id => $result) {
                            foreach ($reviews as $question_id2 => $review) {
                                if ($question_id2 == $question_id) {
                                    $question = QuizzesQuestion::where('id', $question_id)
                                        ->where('creator_id', $user->id)
                                        ->first();

                                    if ($question->type == 'descriptive') {
                                        if (!empty($result['status']) and $result['status']) {
                                            $user_grade = $user_grade - (isset($result['grade']) ? (int)$result['grade'] : 0);
                                            $user_grade = $user_grade + (isset($review['grade']) ? (int)$review['grade'] : (int)$question->grade);
                                        } else if (isset($result['status']) and !$result['status']) {
                                            $user_grade = $user_grade + (isset($review['grade']) ? (int)$review['grade'] : (int)$question->grade);
                                            $oldResults[$question_id]['grade'] = isset($review['grade']) ? $review['grade'] : $question->grade;
                                        }

                                        $oldResults[$question_id]['status'] = true;
                                    }
                                }
                            }
                        }
                    } elseif (!empty($reviews) and count($reviews)) {
                        foreach ($reviews as $questionId => $review) {

                            if (!is_array($review)) {
                                unset($reviews[$questionId]);
                            } else {
                                $question = QuizzesQuestion::where('id', $questionId)
                                    ->where('quiz_id', $quiz->id)
                                    ->first();

                                if ($question and $question->type == 'descriptive') {
                                    $user_grade += (isset($review['grade']) ? (int)$review['grade'] : 0);
                                }
                            }
                        }

                        $oldResults = $reviews;
                    }

                    $quizResult->user_grade = $user_grade;
                    $passMark = $quiz->pass_mark;

                    if ($quizResult->user_grade >= $passMark) {
                        $quizResult->status = QuizzesResult::$passed;
                    } else {
                        $quizResult->status = QuizzesResult::$failed;
                    }

                    $quizResult->results = json_encode($oldResults);

                    $quizResult->save();

                    $notifyOptions = [
                        '[c.title]' => $quiz->webinar_title,
                        '[q.title]' => $quiz->title,
                        '[q.result]' => $quizResult->status,
                    ];
                    sendNotification('waiting_quiz_result', $notifyOptions, $quizResult->user_id);

                    if ($quizResult->status == QuizzesResult::$passed) {
                        $passTheQuizReward = RewardAccounting::calculateScore(Reward::PASS_THE_QUIZ);
                        RewardAccounting::makeRewardAccounting($quizResult->user_id, $passTheQuizReward, Reward::PASS_THE_QUIZ, $quizResult->id, true);

                        if ($quiz->certificate) {
                            $certificateReward = RewardAccounting::calculateScore(Reward::CERTIFICATE);
                            RewardAccounting::makeRewardAccounting($quizResult->user_id, $certificateReward, Reward::CERTIFICATE, $quiz->id, true);
                        }
                    }

                    return redirect('panel/quizzes/results');
                }
            }
        }

        abort(404);
    }
}
