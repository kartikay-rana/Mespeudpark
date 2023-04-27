<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Webinar;
use App\Http\Controllers\Web\ClassesController;
class RewardCoursesController extends Controller
{
    //
    
     public function index(Request $request)
    {
        $webinarsQuery = Webinar::where('webinars.status', 'active')
            ->where('private', false)
            ->whereNotNull('points');

        $classesController = new ClassesController();

        $webinarsQuery = $classesController->handleFilters($request, $webinarsQuery);

        $sort = $request->get('sort', null);

        if (empty($sort)) {
            $webinarsQuery = $webinarsQuery->orderBy('webinars.created_at', 'desc')
                ->orderBy('webinars.updated_at', 'desc');
        }

        $webinars = $webinarsQuery->with(['tickets'])
            ->paginate(6);

        $seoSettings = getSeoMetas('reward_courses');
        $pageTitle = !empty($seoSettings['title']) ? $seoSettings['title'] : '';
        $pageDescription = !empty($seoSettings['description']) ? $seoSettings['description'] : '';
        $pageRobot = getPageRobot('reward_courses');

        $data = [
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'pageRobot' => $pageRobot,
            'webinars' => $webinars,
            'webinarsCount' => $webinars->total(),
            'sortFormAction' => '/reward-courses',
            'category' => null,
            'featureWebinars' => null,
            'isRewardCourses' => true
        ];
         if($data){
             return response(['statusCode' => 200, 'message' => 'Reward course','data'=>$data]);
        }else{
             return response(['statusCode' => 400, 'message' => 'No data found']);
            
        }
       
    }
}
