<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentChannel;
use App\Models\Setting;
use App\Models\Subscribe;
use App\User;
use Illuminate\Http\Request;
class SubscribesController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $subscribes = Subscribe::all();

        $data = [
            'pageTitle' => trans('financial.subscribes'),
            'subscribes' => $subscribes,
            'activeSubscribe' => Subscribe::getActiveSubscribe($user->id),
            'dayOfUse' => Subscribe::getDayOfUse($user->id),
        ];
        //dd($data);
        if($data){
            return response(['statusCode' => 200, 'message' => 'Subscribe all list','data'=>$data]);
       }else{
            return response(['statusCode' => 404, 'message' => 'No data found']);

       }
    }

    public function pay(Request $request)
    {
        $paymentChannels = PaymentChannel::where('status', 'active')->get();
       // dd($paymentChannels);
        $subscribe = Subscribe::where('id', $request->input('id'))->first();
       // dd($subscribe);
        if (empty($subscribe)) {
            $toastData = [
                'msg' => trans('site.subscribe_not_valid'),
                'status' => 'error'
            ];
            return response(['statusCode' => 200, 'message' => 'subscribe_not_valid','data'=>$toastData]);
        }

        $user = auth()->user();
        $activeSubscribe = Subscribe::getActiveSubscribe($user->id);

        if ($activeSubscribe) {
            $toastData = [
                'title' => trans('public.request_failed'),
                'msg' => trans('site.you_have_active_subscribe'),
                'status' => 'error'
            ];
            return response(['statusCode' => 200, 'message' => 'request_failed','data'=>$toastData]);

        }

        $financialSettings = getFinancialSettings();
        $tax = $financialSettings['tax'] ?? 0;

        $amount = $subscribe->price;

        $taxPrice = $tax ? $amount *  $tax / 100 : 0;

        $order = Order::create([
            "user_id" => $user->id,
            "status" => Order::$pending,
            'tax' => $taxPrice,
            'commission' => 0,
            "amount" => $amount,
            "total_amount" => $amount + $taxPrice,
            "created_at" => time(),
        ]);

        OrderItem::updateOrCreate([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'subscribe_id' => $subscribe->id,
        ], [
            'amount' => $order->amount,
            'total_amount' => $amount + $taxPrice,
            'tax' => $tax,
            'tax_price' => $taxPrice,
            'commission' => 0,
            'commission_price' => 0,
            'created_at' => time(),
        ]);

        $razorpay = false;
        foreach ($paymentChannels as $paymentChannel) {
            if ($paymentChannel->class_name == 'Razorpay') {
                $razorpay = true;
            }
        }

        $data = [
            'pageTitle' => trans('public.checkout_page_title'),
            'paymentChannels' => $paymentChannels,
            'total' => $order->total_amount,
            'order' => $order,
            'count' => 1,
            'userCharge' => $user->getAccountingCharge(),
            'razorpay' => $razorpay
        ];

        return response(['statusCode' => 200, 'message' => 'checkout page','data'=>$data]);
    }
}
