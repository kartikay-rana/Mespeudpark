<?php

namespace App\Imports;

use App\Models\QuizzesQuestion;
use App\Models\QuizzesQuestionsAnswer;
use App\Models\Translation\QuizzesQuestionsAnswerTranslation;
use App\Models\Translation\QuizzesQuestionTranslation;
use Maatwebsite\Excel\Concerns\ToModel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\ToCollection;

class ImportQuizzesQuestion implements ToCollection,   WithHeadingRow
{
    /**
    * @param array $row QuizzesQuestionTranslation
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    // public function model(array $row)
    // {
    //     // dd($row);
    //     // $quizQuestion =  QuizzesQuestion::create([
    //     //     'quiz_id'=> $row['quizz_id'] ?? null,
    //     //     'creator_id'=>$row['creator_id'] ?? null,
    //     //     'grade'=> $row['marks'] ?? null,
    //     //     'type'=>$row['question_type'] ?? null,
    //     //     'image'=>$row['image'] ?? null,
    //     //     'video'=>$row['video'] ?? null,
    //     //     'created_at' => time()
    //     //     ]);
    //     // $quizQuestion =  new QuizzesQuestion();
    //     //     //create([
    //     //     $quizQuestion->quiz_id = $row['quizz_id'] ?? null;
    //     //     $quizQuestion->creator_id = $row['creator_id'] ?? null;
    //     //     $quizQuestion->grade =  $row['marks'] ?? null;
    //     //     $quizQuestion->type = $row['question_type'] ?? null;
    //     //     $quizQuestion->image = $row['image'] ?? null;
    //     //     $quizQuestion->video = $row['video'] ?? null;
    //     //     $quizQuestion->created_at  = time();
    //     //     $quizQuestion->save();
    //     //     dd($quizQuestion);
    //     //     if (!empty($quizQuestion)) {
    //     //       $ddd=  QuizzesQuestionTranslation::updateOrCreate([
    //     //             'quizzes_question_id' => $quizQuestion->id,
    //     //         ], [
    //     //             'title' => $row['question'],
    //     //             'locale'=>'en',
    //     //             'correct' => $row['correct'] ?? null,
    //     //         ]);
    //     //     }
    //         // dd($quizQuestion,$ddd);
    //     return new QuizzesQuestion([
    //         'quiz_id'=> $row['quizz_id'] ?? null,
    //         'creator_id'=>$row['creator_id'] ?? null,
    //         'grade'=> $row['marks'] ?? null,
    //         'type'=>$row['question_type'] ?? null,
    //         'image'=>$row['image'] ?? null,
    //         'video'=>$row['video'] ?? null,
    //         'created_at' => time()
    //     ])
        
    //     ;
    // }
    public function collection(Collection $rows)
    {   //dd($rows);
        foreach ($rows as $row) 
        {
            // dd($row['question'],$row['quizz_id'],$row['creator_id'],$row['marks'],$row['question_type']);
            // dd($row['quizz_id']);

            $quizQuestion =  QuizzesQuestion::create([
            'quiz_id'=> $row['quizz_id'] ?? null,
            'creator_id'=>$row['creator_id'] ?? null,
            'grade'=> $row['marks'] ?? null,
            'type'=>$row['question_type'] ?? null,
            'image'=>$row['image'] ?? null,
            'video'=>$row['video'] ?? null,
            'created_at' => time()
            ]);
            
            if (!empty($quizQuestion)) {
              $ddd=  QuizzesQuestionTranslation::create(
                  [
                     'quizzes_question_id'=> $quizQuestion->id,
                    'title' => $row['question'],
                    'locale'=>'en',
                    'correct' =>  Null,
                ]);
            }
             for ($i =0; $row['total_answers'] > $i; $i++){
                $no = $i+1;
                //dd($i);
                // if()
                $explaination = null;
                $correct = 0;
                 if($no == $row['correct_answer']){
                    // dd('true', $row['correct_answer'],$i);
                   //  $explaination = $row['explanation'] ?? null;
                     $correct = 1;
                 }
              $quizAnswer =   QuizzesQuestionsAnswer::create([
                  'creator_id'=> $row['creator_id'] ?? null,
                  'question_id'=> $quizQuestion->id,
                 // 'explaination'=> $explaination,
                  'correct' => $correct ,
                 'created_at' => time()
                ]);
               // dd();
              $quizAnswerii=  QuizzesQuestionsAnswerTranslation::create([
                    'quizzes_questions_answer_id'=>$quizAnswer->id,
                    'locale'=>'en',
                    'title'=> $row['answer'.$no]
                    ]
                    );
             }
         
             
            
            // dd($quizQuestion,$ddd,$quizAnswer,$quizAnswerii);
        }
    }
}
