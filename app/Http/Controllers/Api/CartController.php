<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ValidationErrorTrait;

use App\Models\Cart;
use App\Models\Discount;
use App\Models\DiscountUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentChannel;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\Region;
use App\Models\ReserveMeeting;
use App\Models\Setting;
use App\Models\Webinar;
use App\Models\Sale;
use Illuminate\Support\Facades\Validator;
use App\PaymentChannels\ChannelManager;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;



class CartController extends Controller
{
    //
    use ValidationErrorTrait;

    public function index($buy = null)
    {
       // dd($buy);

        $user = auth()->user();
        if ($buy) {
            $carts_data = Cart::where('creator_id', $user->id)->where('type', 'buy_now')
                ->with([
                    'user',
                    'webinar',
                    'reserveMeeting' => function ($query) {
                        $query->with([
                            'meeting',
                            'meetingTime'
                        ]);
                    },
                    'ticket',
                    'productOrder' => function ($query) {
                        $query->whereHas('product');
                        $query->with(['product']);
                    }
                ])
                ->get();
        } else { //dd('hello');
            $carts_data = Cart::where('creator_id', $user->id)
            ->where('type',null)
                ->with([
                    'user',
                    'webinar',
                    'reserveMeeting' => function ($query) {
                        $query->with([
                            'meeting',
                            'meetingTime'
                        ]);
                    },
                    'ticket',
                    'productOrder' => function ($query) {
                        $query->whereHas('product');
                        $query->with(['product']);
                    }
                ])
                ->get();
        }

// dd($carts_data,'byu');

        if ($carts_data) {

            $calculate = $this->calculatePrice($carts_data, $user);


            $hasPhysicalProduct = $carts_data->where('productOrder.product.type', Product::$physical);

            $deliveryEstimateTime = 0;

            if (!empty($hasPhysicalProduct) and count($hasPhysicalProduct)) {
                foreach ($hasPhysicalProduct as $physicalProductCart) {
                    if (
                        !empty($physicalProductCart->productOrder) and
                        !empty($physicalProductCart->productOrder->product) and
                        !empty($physicalProductCart->productOrder->product->delivery_estimated_time) and
                        $physicalProductCart->productOrder->product->delivery_estimated_time > $deliveryEstimateTime
                    ) {
                        $deliveryEstimateTime = $physicalProductCart->productOrder->product->delivery_estimated_time;
                    }
                }
            }

            if ($calculate) {

                //cart data not comming now so i am using again cart
                $carts_data = Cart::where('creator_id', $user->id)
                    ->where('type',null)
                    ->with([
                        'user',
                        'webinar',
                        'reserveMeeting' => function ($query) {
                            $query->with([
                                'meeting',
                                'meetingTime'
                            ]);
                        },
                        'ticket',
                        'productOrder' => function ($query) {
                            $query->whereHas('product');
                            $query->with(['product']);
                        }
                    ])
                    ->get();
                $data = [

                    'user' => $user,
                    'carts' => $carts_data,
                    'subTotal' => $calculate["sub_total"],
                    'totalDiscount' => $calculate["total_discount"],
                    'tax' => $calculate["tax"],
                    'taxPrice' => $calculate["tax_price"],
                    'total' => $calculate["total"],
                    'productDeliveryFee' => $calculate["product_delivery_fee"],
                    'taxIsDifferent' => $calculate["tax_is_different"],
                    'userGroup' => $user->userGroup,
                    'hasPhysicalProduct' => $hasPhysicalProduct,
                    'deliveryEstimateTime' => $deliveryEstimateTime,
                ];



                $data = array_merge($data, $this->getUserLocationsData($user));

                if($buy){
                    return $data;
                }
                if ($data) {
                    return response(['statusCode' => 200, 'message' => "Cart details", 'data' => $data]);
                } else {
                    return response(['statusCode' => 400, 'message' => "Please try again"]);
                }
            }
        } else {
            return response(['statusCode' => 400, 'message' => "No data found"]);
        }
    }

    private function calculatePrice($carts, $user, $discountCoupon = null)
    {
        $financialSettings = getFinancialSettings();

        $subTotal = 0;
        $totalDiscount = 0;
        $tax = (($financialSettings['tax']) and $financialSettings['tax'] > 0) ? $financialSettings['tax'] : 0;
        $taxPrice = 0;
        $commissionPrice = 0;
        $commission = 0;

        $cartHasWebinar = array_filter($carts->pluck('webinar_id')->toArray());
        $cartHasBundle = array_filter($carts->pluck('bundle_id')->toArray());
        $cartHasMeeting = array_filter($carts->pluck('reserve_meeting_id')->toArray());

        $taxIsDifferent = (count($cartHasWebinar) or count($cartHasBundle) or count($cartHasMeeting));

        foreach ($carts as $cart) {
            $orderPrices = $this->handleOrderPrices($cart, $user, $taxIsDifferent);
            $subTotal += $orderPrices['sub_total'];
            $totalDiscount += $orderPrices['total_discount'];
            $tax = $orderPrices['tax'];
            $taxPrice += $orderPrices['tax_price'];
            $commission += $orderPrices['commission'];
            $commissionPrice += $orderPrices['commission_price'];
            $taxIsDifferent = $orderPrices['tax_is_different'];
        }

        if (($discountCoupon)) {
            $totalDiscount += $this->handleDiscountPrice($discountCoupon, $carts, $subTotal);
        }

        if ($totalDiscount > $subTotal) {
            $totalDiscount = $subTotal;
        }

        $subTotalWithoutDiscount = $subTotal - $totalDiscount;
        $productDeliveryFee = $this->calculateProductDeliveryFee($carts);

        $total = $subTotalWithoutDiscount + $taxPrice + $productDeliveryFee;

        if ($total < 0) {
            $total = 0;
        }


        $data = [
            'sub_total' => round($subTotal, 2),
            'total_discount' => round($totalDiscount, 2),
            'tax' => $tax,
            'tax_price' => round($taxPrice, 2),
            'commission' => $commission,
            'commission_price' => round($commissionPrice, 2),
            'total' => round($total, 2),
            'product_delivery_fee' => round($productDeliveryFee, 2),
            'tax_is_different' => $taxIsDifferent
        ];
        return $data;
    }

    private function getUserLocationsData($user)
    {
        $provinces = null;
        $cities = null;
        $districts = null;

        $countries = Region::select(DB::raw('*, ST_AsText(geo_center) as geo_center'))
            ->where('type', Region::$country)
            ->get();

        if (($user->country_id)) {
            $provinces = Region::select(DB::raw('*, ST_AsText(geo_center) as geo_center'))
                ->where('type', Region::$province)
                ->where('country_id', $user->country_id)
                ->get();
        }

        if (($user->province_id)) {
            $cities = Region::select(DB::raw('*, ST_AsText(geo_center) as geo_center'))
                ->where('type', Region::$city)
                ->where('province_id', $user->province_id)
                ->get();
        }

        if (($user->city_id)) {
            $districts = Region::select(DB::raw('*, ST_AsText(geo_center) as geo_center'))
                ->where('type', Region::$district)
                ->where('city_id', $user->city_id)
                ->get();
        }

        return [
            'countries' => $countries,
            'provinces' => $provinces,
            'cities' => $cities,
            'districts' => $districts,
        ];
    }

    public function couponValidate(Request $request)
    {
        $user = auth()->user();
        $coupon = $request->get('coupon');

        $discountCoupon = Discount::where('code', $coupon)
            ->first();

        if (!empty($discountCoupon)) {
            $checkDiscount = $discountCoupon->checkValidDiscount();
            if ($checkDiscount != 'ok') {
                return response()->json([
                    'status' => 422,
                    'msg' => $checkDiscount
                ]);
            }
            if($request->type == 'buy_now'){
                 $carts = Cart::where('creator_id', $user->id)->where('type','buy_now')
                ->get();
            }else{
            $carts = Cart::where('creator_id', $user->id)
                ->get();
            }
            if (!empty($carts) and !$carts->isEmpty()) {
                $calculate = $this->calculatePrice($carts, $user, $discountCoupon);

                if (!empty($calculate)) {
                    return response()->json([
                        'status' => 200,
                        'discount_id' => $discountCoupon->id,
                        'total_discount' => addCurrencyToPrice(handlePriceFormat($calculate["total_discount"])),
                        'total_tax' => addCurrencyToPrice(handlePriceFormat($calculate["tax_price"])),
                        'total_amount' => addCurrencyToPrice(handlePriceFormat($calculate["total"])),
                    ], 200);
                }
            }
        }

        return response()->json([
            'status' => 422,
            'msg' => trans('cart.coupon_invalid')
        ]);
    }

    private function handleDiscountPrice($discount, $carts, $subTotal)
    {
        $user = auth()->user();
        $percent = $discount->percent ?? 1;
        $totalDiscount = 0;

        if ($discount->source == Discount::$discountSourceCourse) {
            $totalWebinarsAmount = 0;
            $webinarOtherDiscounts = 0;
            $discountWebinarsIds = $discount->discountCourses()->pluck('course_id')->toArray();

            foreach ($carts as $cart) {
                $webinar = $cart->webinar;
                if (!empty($webinar) and in_array($webinar->id, $discountWebinarsIds)) {
                    $totalWebinarsAmount += $webinar->price;
                    $webinarOtherDiscounts += $webinar->getDiscount($cart->ticket, $user);
                }
            }

            if ($discount->discount_type == Discount::$discountTypeFixedAmount) {
                $totalDiscount = ($totalWebinarsAmount > $discount->amount) ? $discount->amount : $totalWebinarsAmount;

                if (!empty($webinarOtherDiscounts)) {
                    $totalDiscount = $totalDiscount - (int)$webinarOtherDiscounts;
                }
            } else {
                $totalDiscount = ($totalWebinarsAmount > 0) ? $totalWebinarsAmount * $percent / 100 : 0;
            }
        } elseif ($discount->source == Discount::$discountSourceProduct) {
            $totalProductsAmount = 0;
            $productOtherDiscounts = 0;

            foreach ($carts as $cart) {
                if (!empty($cart->productOrder)) {
                    $product = $cart->productOrder->product;

                    if (!empty($product) and ($discount->product_type == 'all' or $discount->product_type == $product->type)) {
                        $totalProductsAmount += ($product->price * $cart->productOrder->quantity);
                        $productOtherDiscounts += $product->getDiscountPrice();
                    }
                }
            }

            if ($discount->discount_type == Discount::$discountTypeFixedAmount) {
                $totalDiscount = ($totalProductsAmount > $discount->amount) ? $discount->amount : $totalProductsAmount;

                if (!empty($productOtherDiscounts)) {
                    $totalDiscount = $totalDiscount - (int)$productOtherDiscounts;
                }
            } else {
                $totalDiscount = ($totalProductsAmount > 0) ? $totalProductsAmount * $percent / 100 : 0;
            }
        } elseif ($discount->source == Discount::$discountSourceMeeting) {
            $totalMeetingAmount = 0;
            $meetingOtherDiscounts = 0;

            foreach ($carts as $cart) {
                $reserveMeeting = $cart->reserveMeeting;

                if (!empty($reserveMeeting)) {
                    $totalMeetingAmount += $reserveMeeting->paid_amount;
                    $meetingOtherDiscounts += $reserveMeeting->getDiscountPrice($user);
                }
            }

            if ($discount->discount_type == Discount::$discountTypeFixedAmount) {
                $totalDiscount = ($totalMeetingAmount > $discount->amount) ? $discount->amount : $totalMeetingAmount;

                if (!empty($meetingOtherDiscounts)) {
                    $totalDiscount = $totalDiscount - $meetingOtherDiscounts;
                }
            } else {
                $totalDiscount = ($totalMeetingAmount > 0) ? $totalMeetingAmount * $percent / 100 : 0;
            }
        } elseif ($discount->source == Discount::$discountSourceCategory) {
            $totalCategoriesAmount = 0;
            $categoriesOtherDiscounts = 0;

            $categoriesIds = ($discount->discountCategories) ? $discount->discountCategories()->pluck('category_id')->toArray() : [];

            foreach ($carts as $cart) {
                $webinar = $cart->webinar;

                if (!empty($webinar) and in_array($webinar->category_id, $categoriesIds)) {
                    $totalCategoriesAmount += $webinar->price;
                    $categoriesOtherDiscounts += $webinar->getDiscount($cart->ticket, $user);
                }
            }

            if ($discount->discount_type == Discount::$discountTypeFixedAmount) {
                $totalDiscount = ($totalCategoriesAmount > $discount->amount) ? $discount->amount : $totalCategoriesAmount;

                if (!empty($categoriesOtherDiscounts)) {
                    $totalDiscount = $totalDiscount - $categoriesOtherDiscounts;
                }
            } else {
                $totalDiscount = ($totalCategoriesAmount > 0) ? $totalCategoriesAmount * $percent / 100 : 0;
            }
        } else {
            $totalCartAmount = 0;
            $totalCartOtherDiscounts = 0;

            foreach ($carts as $cart) {
                $webinar = $cart->webinar;
                $reserveMeeting = $cart->reserveMeeting;

                if (!empty($webinar)) {
                    $totalCartAmount += $webinar->price;
                    $totalCartOtherDiscounts += $webinar->getDiscount($cart->ticket, $user);
                }

                if (!empty($reserveMeeting)) {
                    $totalCartAmount += $reserveMeeting->paid_amount;
                    $totalCartOtherDiscounts += $reserveMeeting->getDiscountPrice($user);
                }
            }

            if ($discount->discount_type == Discount::$discountTypeFixedAmount) {
                $totalDiscount = ($totalCartAmount > $discount->amount) ? $discount->amount : $totalCartAmount;

                if (!empty($totalCartOtherDiscounts)) {
                    $totalDiscount = $totalDiscount - $totalCartOtherDiscounts;
                }
            } else {
                $totalDiscount = ($totalCartAmount > 0) ? $totalCartAmount * $percent / 100 : 0;
            }
        }

        if ($discount->discount_type != Discount::$discountTypeFixedAmount and !empty($discount->max_amount) and $totalDiscount > $discount->max_amount) {
            $totalDiscount = $discount->max_amount;
        }

        return $totalDiscount;
    }

    private function productDeliveryFeeBySeller($carts)
    {
        $productFee = [];

        foreach ($carts as $cart) {
            if (!empty($cart->productOrder) and !empty($cart->productOrder->product)) {
                $product = $cart->productOrder->product;

                if (!empty($product->delivery_fee)) {
                    if (!empty($productFee[$product->creator_id]) and $productFee[$product->creator_id] < $product->delivery_fee) {
                        $productFee[$product->creator_id] = $product->delivery_fee;
                    } else if (empty($productFee[$product->creator_id])) {
                        $productFee[$product->creator_id] = $product->delivery_fee;
                    }
                }
            }
        }

        return $productFee;
    }

    private function productCountBySeller($carts)
    {
        $productCount = [];

        foreach ($carts as $cart) {
            if (!empty($cart->productOrder) and !empty($cart->productOrder->product)) {
                $product = $cart->productOrder->product;

                if (!empty($productCount[$product->creator_id])) {
                    $productCount[$product->creator_id] += 1;
                } else {
                    $productCount[$product->creator_id] = 1;
                }
            }
        }

        return $productCount;
    }

    private function calculateProductDeliveryFee($carts)
    {
        $fee = 0;

        if (!empty($carts)) {
            $productsFee = $this->productDeliveryFeeBySeller($carts);

            if (!empty($productsFee) and count($productsFee)) {
                $fee = array_sum($productsFee);
            }
        }

        return $fee;
    }




    //checkout

    public function checkout(Request $request , $carts = null)
    { //dd($carts);
    
        $user = auth()->user();
        if($request->buy_now == 'buy_now'){
            $carts = Cart::where('creator_id', $user->id)->where('type','buy_now')
                ->get();
        }
        if (empty($carts)) {
            $carts = Cart::where('creator_id', $user->id)
                ->get(); //dd($carts);

        }
        // dd($user->id,$carts);
        $hasPhysicalProduct = $carts->where('productOrder.product.type', Product::$physical);
        //dd($hasPhysicalProduct);
        $this->validate($request, [
            'country_id' => Rule::requiredIf(count($hasPhysicalProduct) > 0),
            'province_id' => Rule::requiredIf(count($hasPhysicalProduct) > 0),
            'city_id' => Rule::requiredIf(count($hasPhysicalProduct) > 0),
            'district_id' => Rule::requiredIf(count($hasPhysicalProduct) > 0),
            'address' => Rule::requiredIf(count($hasPhysicalProduct) > 0),
        ]);

        $discountId = $request->input('discount_id');

        $paymentChannels = PaymentChannel::where('status', 'active')->get();
        // dd( $paymentChannels);
        $discountCoupon = Discount::where('id', $discountId)->first();

        if (empty($discountCoupon) or $discountCoupon->checkValidDiscount() != 'ok') {
            $discountCoupon = null;
        }

        if (!empty($carts)) {

            $calculate = $this->calculatePrice($carts, $user, $discountCoupon);
            // dd( $calculate);
            $order = $this->createOrderAndOrderItems($carts, $calculate, $user, $discountCoupon);
            // dd('oo',$order);
            if (count($hasPhysicalProduct) > 0) {
                $this->updateProductOrders($request, $carts, $user);
            }

            if (!empty($order) and $order->total_amount > 0) {
                $razorpay = false;
                foreach ($paymentChannels as $paymentChannel) {
                    if ($paymentChannel->class_name == 'Razorpay') {
                        $razorpay = true;
                    }
                }

                $data = [
                    'pageTitle' => trans('public.checkout_page_title'),
                    'paymentChannels' => $paymentChannels,

                    'subTotal' => $calculate["sub_total"],
                    'totalDiscount' => $calculate["total_discount"],
                    'tax' => $calculate["tax"],
                    'taxPrice' => $calculate["tax_price"],
                    'total' => $calculate["total"],
                    'userGroup' => $user->userGroup ? $user->userGroup->group : null,
                    'order' => $order,
                    'count' => $carts->count(),
                    'userCharge' => $user->getAccountingCharge(),
                    'razorpay' => $razorpay
                ];
                // dd($data);
                // return view(getTemplate() . '.cart.payment', $data);
                return response(['statusCode' => 400, 'message' => "checkout data", 'data' => $data]);
            } else {
                return response(['statusCode' => 404, 'message' => "checkout data not found"]);
                // return $this->handlePaymentOrderWithZeroTotalAmount($order);
            }
        }

        return redirect('/cart');
    }
    // check out free
    public function checkOutFree(Request $request, $carts = null)
    {
        $user = auth()->user(); //we binars
        // dd($request->webinar_id);
        $checkFree = Webinar::where('id', $request->webinar_id)->first();
        if ($checkFree == null) {
            return response(['statusCode' => 404, 'message' => "data not found"]);
        }
        //dd($checkFree);
        if ($checkFree->price == null or $checkFree->price == 0) {
            // dd('hello free');
            $checkOrder = Order::where('user_id', $user->id)->where('webinar_id', $request->webinar_id)->first();
            //dd($checkOrder);
            if ($checkOrder == null) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'status' => 'free',
                    'webinar_id' => $request->webinar_id,
                    'amount' => '0',
                    'tax' => '0',
                    'total_discount' => '0',
                    'total_amount' => '0',
                    'product_delivery_fee' => '0',
                    'created_at' => time(),
                ]);

                $orderDetails   = OrderItem::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'webinar_id' => $request->webinar_id,
                    'bundle_id' => $request->bundle_id ?? null,
                    'product_id' => (!empty($request->product_order_id) and !empty($request->productOrder->product)) ? $request->productOrder->product->id : null,
                    'product_order_id' => (!empty($request->product_order_id)) ? $request->product_order_id : null,
                    'reserve_meeting_id' =>  null,
                    'subscribe_id' =>  null,
                    'promotion_id' =>  null,
                    'ticket_id' =>  null,
                    'discount_id' => null,
                    'amount' => null,
                    'total_amount' => null,
                    'tax' => null,
                    'tax_price' => null,
                    'commission' => null,
                    'commission_price' => null,
                    'product_delivery_fee' => null,
                    'discount' => null,
                    'created_at' => time(),
                ]);
                //$seller_id = OrderItem::getSeller($orderDetails);
                // dd($seller_id);
                //$sale = new Sale();
                $sale = Sale::create([
                    'buyer_id' => $orderDetails->user_id,
                    'seller_id' => $checkFree->creator_id,
                    'order_id' => $orderDetails->order_id,
                    'webinar_id' => $orderDetails->webinar_id,
                    'bundle_id' => $orderDetails->bundle_id ?? null,
                    'meeting_id' => !empty($orderDetails->reserve_meeting_id) ? $orderDetails->reserveMeeting->meeting_id : null,
                    'subscribe_id' => $orderDetails->subscribe_id ?? null,
                    'promotion_id' => $orderDetails->promotion_id ?? null,
                    'registration_package_id' => $orderDetails->registration_package_id ?? null,
                    'product_order_id' => $orderDetails->product_order_id ?? null,
                    'type' => $checkFree->type,
                    'payment_method' => 'free',
                    'amount' => $orderDetails->amount ?? null,
                    'tax' => $orderDetails->tax_price ?? null,
                    'commission' => $orderDetails->commission_price ?? null,
                    'discount' => $orderDetails->discount ?? null,
                    'total_amount' => $orderDetails->total_amount ?? null,
                    'product_delivery_fee' => $orderDetails->product_delivery_fee ?? null,
                    'created_at' => time(),
                ]);

                return response(['statusCode' => 200, 'message' => "checkout data"]);
            } else {
                return response(['statusCode' => 302, 'message' => "checkout  already found"]);
            }
        } else {
            return response(['statusCode' => 302, 'message' => "checkout data found"]);
        }
    }

    private function updateProductOrders(Request $request, $carts, $user)
    {
        $data = $request->all();

        foreach ($carts as $cart) {
            if (!empty($cart->product_order_id)) {
                ProductOrder::where('id', $cart->product_order_id)
                    ->where('buyer_id', $user->id)
                    ->update([
                        'message_to_seller' => $data['message_to_seller'],
                    ]);
            }
        }

        $user->update([
            'country_id' => $data['country_id'] ?? $user->country_id,
            'province_id' => $data['province_id'] ?? $user->province_id,
            'city_id' => $data['city_id'] ?? $user->city_id,
            'district_id' => $data['district_id'] ?? $user->district_id,
            'address' => $data['address'] ?? $user->address,
        ]);
    }

    public function createOrderAndOrderItems($carts, $calculate, $user, $discountCoupon = null)
    {   //dd($carts, $calculate, $user, $discountCoupon = null);
        // $data = [
        //     'user_id' => $user->id,
        //     'status' => Order::$pending,
        //     'amount' => $calculate["sub_total"],
        //     'tax' => $calculate["tax_price"],
        //     'total_discount' => $calculate["total_discount"],
        //     'total_amount' => $calculate["total"],
        //     'product_delivery_fee' => $calculate["product_delivery_fee"] ?? null,
        //     'created_at' => time(),]; //dd($data);
        $order = Order::create([
            'user_id' => $user->id,
            'webinar_id' => null,
            'status' => Order::$pending,
            'amount' => $calculate["sub_total"],
            'tax' => $calculate["tax_price"],
            'total_discount' => $calculate["total_discount"],
            'total_amount' => $calculate["total"],
            'product_delivery_fee' => $calculate["product_delivery_fee"] ?? null,
            'created_at' => time(),
        ]);

        $productsFee = $this->productDeliveryFeeBySeller($carts);
        $sellersProductsCount = $this->productCountBySeller($carts);

        foreach ($carts as $cart) {

            $orderPrices = $this->handleOrderPrices($cart, $user);
            $price = $orderPrices['sub_total'];
            $totalDiscount = $orderPrices['total_discount'];
            $tax = $orderPrices['tax'];
            $taxPrice = $orderPrices['tax_price'];
            $commission = $orderPrices['commission'];
            $commissionPrice = $orderPrices['commission_price'];
            $discountCouponPrice = 0;

            $productDeliveryFee = 0;
            if (!empty($cart->product_order_id)) {
                $product = $cart->productOrder->product;

                if (!empty($product) and !empty($productsFee[$product->creator_id])) {
                    $productDeliveryFee = $productsFee[$product->creator_id];
                }

                $sellerProductCount = !empty($sellersProductsCount[$product->creator_id]) ? $sellersProductsCount[$product->creator_id] : 1;

                $productDeliveryFee = $productDeliveryFee > 0 ? $productDeliveryFee / $sellerProductCount : 0;
            }

            if (!empty($discountCoupon)) {
                $couponAmount = $price * $discountCoupon->percent / 100;

                if (!empty($discountCoupon->amount) and $couponAmount > $discountCoupon->amount) {
                    $discountCouponPrice += $discountCoupon->amount;
                } else {
                    $discountCouponPrice += $couponAmount;
                }
            }

            $allDiscountPrice = $totalDiscount + $discountCouponPrice;

            $subTotalWithoutDiscount = $price - $allDiscountPrice;
            $totalAmount = $subTotalWithoutDiscount + $taxPrice + $productDeliveryFee;

            $ticket = $cart->ticket;
            if (!empty($ticket) and !$ticket->isValid()) {
                $ticket = null;
            }

            OrderItem::create([
                'user_id' => $user->id,
                'order_id' => $order->id,
                'webinar_id' => $cart->webinar_id ?? null,
                'bundle_id' => $cart->bundle_id ?? null,
                'product_id' => (!empty($cart->product_order_id) and !empty($cart->productOrder->product)) ? $cart->productOrder->product->id : null,
                'product_order_id' => (!empty($cart->product_order_id)) ? $cart->product_order_id : null,
                'reserve_meeting_id' => $cart->reserve_meeting_id ?? null,
                'subscribe_id' => $cart->subscribe_id ?? null,
                'promotion_id' => $cart->promotion_id ?? null,
                'ticket_id' => !empty($ticket) ? $ticket->id : null,
                'discount_id' => $discountCoupon ? $discountCoupon->id : null,
                'amount' => $price,
                'total_amount' => $totalAmount,
                'tax' => $tax,
                'tax_price' => $taxPrice,
                'commission' => $commission,
                'commission_price' => $commissionPrice,
                'product_delivery_fee' => $productDeliveryFee,
                'discount' => $allDiscountPrice,
                'created_at' => time(),
            ]);
        }

        return $order;
    }

    private function getSeller($cart)
    {
        $user = null;

        if (!empty($cart->webinar_id) or !empty($cart->bundle_id)) {
            $user = $cart->webinar_id ? $cart->webinar->creator : $cart->bundle->creator;
        } elseif (!empty($cart->reserve_meeting_id)) {
            $user = $cart->reserveMeeting->meeting->creator;
        } elseif (!empty($cart->product_order_id)) {
            $user = $cart->productOrder->seller;
        }

        return $user;
    }

    private function handleOrderPrices($cart, $user, $taxIsDifferent = false)
    {
        $seller = $this->getSeller($cart);
        $financialSettings = getFinancialSettings();

        $subTotal = 0;
        $totalDiscount = 0;
        $tax = (!empty($financialSettings['tax']) and $financialSettings['tax'] > 0) ? $financialSettings['tax'] : 0;
        $taxPrice = 0;
        $commissionPrice = 0;
        $commission = $seller->getCommission();

        if (!empty($cart->webinar_id) or !empty($cart->bundle_id)) {
            $item = !empty($cart->webinar_id) ? $cart->webinar : $cart->bundle;
            $price = $item->price;
            $discount = $item->getDiscount($cart->ticket, $user);

            $priceWithoutDiscount = $price - $discount;

            if ($tax > 0 and $priceWithoutDiscount > 0) {
                $taxPrice += $priceWithoutDiscount * $tax / 100;
            }

            if (!empty($commission) and $commission > 0) {
                $commissionPrice += $priceWithoutDiscount > 0 ? $priceWithoutDiscount * $commission / 100 : 0;
            }

            $totalDiscount += $discount;
            $subTotal += $price;
        } elseif (!empty($cart->reserve_meeting_id)) {
            $price = $cart->reserveMeeting->paid_amount;
            $discount = $cart->reserveMeeting->getDiscountPrice($user);

            $priceWithoutDiscount = $price - $discount;

            if ($tax > 0 and $priceWithoutDiscount > 0) {
                $taxPrice += $priceWithoutDiscount * $tax / 100;
            }

            if (!empty($commission) and $commission > 0) {
                $commissionPrice += $priceWithoutDiscount > 0 ? $priceWithoutDiscount * $commission / 100 : 0;
            }

            $totalDiscount += $discount;
            $subTotal += $price;
        } elseif (!empty($cart->product_order_id)) {
            $product = $cart->productOrder->product;

            if (!empty($product)) {
                $price = ($product->price * $cart->productOrder->quantity);
                $discount = $product->getDiscountPrice();

                $commission = $product->getCommission();
                $productTax = $product->getTax();

                $priceWithoutDiscount = $price - $discount;

                $taxIsDifferent = ($taxIsDifferent and $tax != $productTax);

                $tax = $productTax;
                if ($productTax > 0 and $priceWithoutDiscount > 0) {
                    $taxPrice += $priceWithoutDiscount * $productTax / 100;
                }

                if ($commission > 0) {
                    $commissionPrice += $priceWithoutDiscount > 0 ? $priceWithoutDiscount * $commission / 100 : 0;
                }

                $totalDiscount += $discount;
                $subTotal += $price;
            }
        }

        if ($totalDiscount > $subTotal) {
            $totalDiscount = $subTotal;
        }


        return [
            'sub_total' => round($subTotal, 2),
            'total_discount' => round($totalDiscount, 2),
            'tax' => $tax,
            'tax_price' => round($taxPrice, 2),
            'commission' => $commission,
            'commission_price' => round($commissionPrice, 2),
            //'product_delivery_fee' => round($productDeliveryFee, 2),
            'tax_is_different' => $taxIsDifferent
        ];
    }

    private function handlePaymentOrderWithZeroTotalAmount($order)
    {
        $order->update([
            'payment_method' => Order::$paymentChannel
        ]);

        $paymentController = new PaymentController();

        $paymentController->setPaymentAccounting($order);

        $order->update([
            'status' => Order::$paid
        ]);

        return redirect('/payments/status?order_id=' . $order->id);
    }

    public function store(Request $request)
    { //dd($request->all());

        $user = auth()->user();


        $validator = Validator::make($request->all(), [

            'item_id' => 'required',
            'item_name' => 'nullable',

        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }

        $data = $request->except('_token');
        $webinar_id = $data['item_id'];
        $item_name = $data['item_name'];

        if (!empty($user)) { // store in DB
            $result = null;
            if ($item_name == 'buy_now') {
                $webinar = Webinar::where('id', $webinar_id)
                    ->where('private', false)
                    ->where('status', 'active')
                    ->first();
                if ($webinar) {
                    $data = [
                        'creator_id' => $user->id,
                        'type' => $item_name,
                        'webinar_id' => $webinar_id,
                        'created_at' => time()
                    ];
                  $carts=  Cart::updateOrCreate(
                        [
                            'creator_id' => $user->id,
                            'type' => $item_name
                        ],
                        $data
                    );
                    
                    $data =  $this->index('buy_now');
                    //dd($data)
                    return response(['statusCode' => 400,'data'=>$data]);
                } else {
                    $toastData = [
                        'title' => trans('public.request_failed'),
                        'msg' => trans('cart.course_not_found'),
                        'status' => 'error'
                    ];
                    //  return back()->with(['toast' => $toastData]);

                    return response(['statusCode' => 400, 'message' => $toastData]);
                }
            }
        }


        // return back()->with(['toast' => $toastData]);
    }
}