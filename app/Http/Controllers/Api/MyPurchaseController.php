<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ProductOrder;
use App\Models\Sale;
use App\Models\Webinar;
use App\User;

class MyPurchaseController extends Controller
{
    //
     public function purchases(Request $request)
    {
        $user = auth()->user();

    if($request->slug == null){
        $query = Sale::where('sales.buyer_id', $user->id)
        ->whereNull('sales.refund_at')
        ->where('access_to_purchased_item', true)
        ->where(function ($query) {
            $query->where(function ($query) {
                $query->whereNotNull('sales.webinar_id')
                    ->where('sales.type', 'webinar')
                    ->whereHas('webinar', function ($query) {
                        $query->where('status', 'active');
                    });
            });
            $query->orWhere(function ($query) {
                $query->whereNotNull('sales.bundle_id')
                    ->where('sales.type', 'bundle')
                    ->whereHas('bundle', function ($query) {
                        $query->where('status', 'active');
                    });
            });
        });


    $sales = deepClone($query)
        ->with([
            'webinar' => function ($query) {
                $query->with([
                    'files',
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'category',
                    'teacher' => function ($query) {
                        $query->select('id', 'full_name');
                    },
                ]);
                $query->withCount([
                    'sales' => function ($query) {
                        $query->whereNull('refund_at');
                    }
                ]);
            },
            'bundle' => function ($query) {
                $query->with([
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'category',
                    'teacher' => function ($query) {
                        $query->select('id', 'full_name');
                    },
                ]);
            }
        ])
        ->orderBy('created_at', 'desc')
        ->paginate(10);

    $purchasedCount = deepClone($query)
        ->where(function ($query) {
            $query->whereHas('webinar');
            $query->orWhereHas('bundle');
        })
        ->count();

    $webinarsHours = deepClone($query)->join('webinars', 'webinars.id', 'sales.webinar_id')
        ->select(DB::raw('sum(webinars.duration) as duration'))
        ->sum('duration');
    $bundlesHours = deepClone($query)->join('bundle_webinars', 'bundle_webinars.bundle_id', 'sales.bundle_id')
        ->join('webinars', 'webinars.id', 'bundle_webinars.webinar_id')
        ->select(DB::raw('sum(webinars.duration) as duration'))
        ->sum('duration');

    $hours = $webinarsHours + $bundlesHours;

    $time = time();
    $upComing = deepClone($query)->join('webinars', 'webinars.id', 'sales.webinar_id')
        ->where('webinars.start_date', '>', $time)
        ->count();
        
   
    $data = [
        'pageTitle' => trans('webinars.webinars_purchases_page_title'),
        'sales' => $sales,
        'purchasedCount' => $purchasedCount,
        'hours' => $hours,
        'upComing' => $upComing,
        
    ];
}else{ //dd('hello');
        $webinar = Webinar::where('slug',$request->slug)->first();
        $data = [
            'webinar' => $webinar,
        ];
       }
  return response(['statusCode' => 200, 'message' => "Successful payment",'data'=>$data]);
      //  return view(getTemplate() . '.panel.webinar.purchases', $data);
    }
    
}
