<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\TrendCategory;
use App\Traits\ValidationErrorTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\WebinarFilterOption;
use App\Models\FeatureWebinar;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\Translation\CategoryTranslation;
use App\Models\Webinar;
use App\Http\Controllers\Web\ClassesController;
use App\User;
use Illuminate\Support\Facades\Auth;

// C:\xampp\htdocs\frelance\public_html\app\Http\Controllers\Web\ClassesController.php
class CategoryController extends Controller
{

      use ValidationErrorTrait;


       public  function get_category_list(Request $request){
        //$get_category=Category::get();

          $trendCategories = TrendCategory::with([
                'category' => function ($query) {
                    $query->withCount([
                        'webinars' => function ($query) {
                            $query->where('status', 'active');
                        }
                    ]);
                }
            ])->orderBy('created_at', 'desc')
                ->limit(6)
                ->get();

        $browse_category=Category::where('parent_id','=',null)->where('status', '1')->with(['subCategories'])->get();

        if($trendCategories){
               return response(['statusCode' => 200, 'message' => 'Category list','data'=>$trendCategories,'browse_category'=>$browse_category]);

        }else{
              return response(['statusCode' => 400, 'message' => 'No data found']);
        }
    }

    public function course_category_wise(Request $request){


          $validator = Validator::make($request->all(), [

            'category_id' => 'required'

        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }


        $categoryTitle_id=$request->category_id;

          $subCategoryTranslation_id=$request->sub_category_id;
           if (!empty($categoryTitle_id)) {

            $categoryTranslation = CategoryTranslation::where('category_id',  $categoryTitle_id)->first();


            $subCategoryTranslation = null;

            if (!empty($subCategoryTranslation_id)) {
                $subCategoryTranslation = CategoryTranslation::where('category_id',  $subCategoryTranslation_id)->get();
             //   dd($subCategoryTranslation);
                //  $subCategoryTranslation = CategoryTranslation::where('title', str_replace('-', ' ', $subCategoryTranslation_id))->get();

            }


            if (!empty($categoryTranslation)) {
                $category = Category::where(function ($query) use ($categoryTranslation, $subCategoryTranslation) {
                    if (!empty($subCategoryTranslation)) {
                        $query->whereIn('id', $subCategoryTranslation->pluck('category_id')->toArray());
                        $query->where('parent_id', $categoryTranslation->category_id);
                    } else {
                        $query->where('id', $categoryTranslation->category_id);
                    }
                })->withCount('webinars')
                    ->with(['filters' => function ($query) {
                        $query->with('options');
                    }])
                    ->first();
            }


            if (!empty($category)) {

                $featureWebinars = FeatureWebinar::whereIn('page', ['categories', 'home_categories'])
                    ->where('status', 'publish')
                    ->whereHas('webinar', function ($q) use ($category) {
                        $q->where('status', Webinar::$active);
                        $q->whereHas('category', function ($q) use ($category) {
                            $q->where('id', $category->id);
                        });
                    })
                    ->with(['webinar' => function ($query) {
                        $query->with(['teacher' => function ($qu) {
                            $qu->select('id', 'full_name', 'avatar');
                        }, 'reviews', 'tickets', 'feature']);
                    }])
                    ->orderBy('updated_at', 'desc')
                    ->get();


                $webinarsQuery = Webinar::where('webinars.status', 'active')
                    ->where('private', false)
                    ->where('category_id', $category->id);

                $classesController = new ClassesController();
                $webinarsQuery = $classesController->handleFilters($request, $webinarsQuery);

                $sort = $request->get('sort', null);

                if (empty($sort)) {
                    $webinarsQuery = $webinarsQuery->orderBy('webinars.created_at', 'desc')
                        ->orderBy('webinars.updated_at', 'desc');
                }

                $webinars = $webinarsQuery->with(['tickets', 'feature'])
                    ->paginate(6);


                $seoSettings = getSeoMetas('categories');
                $pageTitle = !empty($seoSettings['title']) ? $seoSettings['title'] : trans('site.categories_page_title');
                $pageDescription = !empty($seoSettings['description']) ? $seoSettings['description'] : trans('site.categories_page_title');
                $pageRobot = getPageRobot('categories');

                $data = [
                    'pageTitle' => $pageTitle,
                    'pageDescription' => $pageDescription,
                    'pageRobot' => $pageRobot,
                    'category' => $category,
                    'webinars' => $webinars,
                    'featureWebinars' => $featureWebinars,
                    'webinarsCount' => $webinars->total(),
                    'sortFormAction' => $category->getUrl(),
                ];



                if($data){
                    return response(['statusCode' => 200, 'message' => 'Course List','data'=>$data]);
                }else{
                      return response(['statusCode' => 400, 'message' => 'No data found']);
                }
            }
        }
    }

    public function update_category(Request $request){
        $user = Auth::user();

       // dd($user->id);
        $category_id = $request->cat_id;
      //  $save_user_detail->course_category_id=$request->category_id;


        // $category = User::where('id' , $user->id)->update(
        //   [ 'course_category_id' =>  $category_id,]
        // );
        $user->course_category_id = $category_id;
        $user->save();
        if($user){
            return response(['statusCode' => 200, 'message' => 'Category  update successfully']);
        }else{
              return response(['statusCode' => 400, 'message' => 'category not update']);
        }
    }
}
