<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ValidationErrorTrait;

use App\Models\QuizzesQuestion;
use App\Models\QuizzesQuestionsAnswer;
use App\Models\Translation\QuizzesQuestionsAnswerTranslation;
use App\Models\Translation\QuizzesQuestionTranslation;
use Illuminate\Support\Facades\Validator;
use App\Models\Quiz;


class QuizQuestController extends Controller
{
    //
     use ValidationErrorTrait;
       
       public function quizquestionlist(Request $request){
             $validator = Validator::make($request->all(), [

            'quiz_id' => 'required'
            
        ]);
        
        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        $id=$request->quiz_id;
        // $c_id=$request->webinar_id;

        //  $question = Quiz::where('webinar_id', $c_id)

        //     //->with('quizzesQuestionsAnswers')
        //     ->pluck('id'); 
        //     dd($question);
        
        $question = Quiz::where('id', $id)
        ->with([
            'quizQuestions' => function ($query) {
                $query->with('quizzesQuestionsAnswers');
            },
        ])
        ->first();
           //dd($question->translatedAttributes);

        if (!empty($question)) {
            $locale = $request->get('locale', app()->getLocale());

            foreach ($question->translatedAttributes as $attribute) {
                try {
                    $question->$attribute = $question->translate(mb_strtolower($locale))->$attribute;
                } catch (\Exception $e) {
                    $question->$attribute = null;
                }
            }

            if (!empty($question->quizzesQuestionsAnswers) and count($question->quizzesQuestionsAnswers)) {
                foreach ($question->quizzesQuestionsAnswers as $answer) {
                    foreach ($answer->translatedAttributes as $att) {
                        try {
                            $answer->$att = $answer->translate(mb_strtolower($locale))->$att;
                        } catch (\Exception $e) {
                            $answer->$att = null;
                        }
                    }
                }
            }


              return response(['statusCode' => 200, 'message' => 'Quize list','data'=>$question]);

        }else{
              return response(['statusCode' => 404, 'message' => 'No data found']);
        }

       }
       public function sectionquestionlist(Request $request){
             $validator = Validator::make($request->all(), [
            
            'webinar_id'=>'required'
        ]);
        
        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }
        
        $c_id=$request->webinar_id;

         $quiz_id = Quiz::where('webinar_id', $c_id)

            //->with('quizzesQuestionsAnswers')
            ->pluck('id'); 
           // dd($question);
        
        $question = Quiz::whereIn('id', $quiz_id)
        ->with([
            'quizQuestions' => function ($query) {
                $query->with('quizzesQuestionsAnswers');
            },
        ])
        ->get();
        //   dd($question);
        
return response(['statusCode' => 200, 'message' => 'Quize list','data'=>$question]);
        if (!empty($question)) {
            $locale = $request->get('locale', app()->getLocale());

            foreach ($question->translatedAttributes as $attribute) {
                try {
                    $question->$attribute = $question->translate(mb_strtolower($locale))->$attribute;
                } catch (\Exception $e) {
                    $question->$attribute = null;
                }
            }

            if (!empty($question->quizzesQuestionsAnswers) and count($question->quizzesQuestionsAnswers)) {
                foreach ($question->quizzesQuestionsAnswers as $answer) {
                    foreach ($answer->translatedAttributes as $att) {
                        try {
                            $answer->$att = $answer->translate(mb_strtolower($locale))->$att;
                        } catch (\Exception $e) {
                            $answer->$att = null;
                        }
                    }
                }
            }


              return response(['statusCode' => 200, 'message' => 'Quize list','data'=>$question]);

        }else{
              return response(['statusCode' => 404, 'message' => 'No data found']);
        }

       }
}
