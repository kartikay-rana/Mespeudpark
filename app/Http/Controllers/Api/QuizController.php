<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;



class QuizController extends Controller
{
    //
    public function quize_list(Request $request)
    {
        $user = auth()->user();
        //dd($user);
        $allQuizzesLists = Quiz::
            // select('id', 'webinar_title')
           // ->where('creator_id', $user->id)
            where('status', 'active')
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
        if($data){
             return response(['statusCode' => 200, 'message' => 'Quiz List','data'=>$data]);
        }else{
             return response(['statusCode' => 200, 'message' => 'No data found']);
        }


       // return view(getTemplate() . '.panel.quizzes.lists', $data);
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

    


    public function showResult($quizResultId)
    {
   // dd('hello');
        $user = auth()->user();
       // $user = User::where('id',1252)->first();

        $quizzesIds = Quiz::where('creator_id', $user->id)->pluck('id')->toArray();
        
        $quizResult = QuizzesResult::where('id', $quizResultId)
            ->where(function ($query) use ($user, $quizzesIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('quiz_id', $quizzesIds);
            })->with([
                'quiz' => function ($query) {
                    $query->with([
                        'quizQuestions' => function ($query) {
                            $query->with('quizzesQuestionsAnswers');
                        },
                    'webinar']);
                }
            ])->first();
       // $questionAnswer = Quiz::where('')    
          //dd($quizResult);
           $test = $quizResult->quiz->quizQuestions;
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
                //'quizzesQuestionsAnswers' =>  $test->quizzes_questions_answers,
            ];

            if($data){
                return response(['statusCode' => 200, 'message' => 'Quiz result','data'=>$data]);
           }else{
                return response(['statusCode' => 200, 'message' => 'No data found']);
           }

        }

        // abort(404);
    }
}
