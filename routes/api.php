<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


// Route::middleware('auth:api')->get('/reward-products', 'RewardProductsController@index');



//search ShareLink

Route::get('/share','Api\RegistrationController@ShareLink');
// Route::get('/{quizResultId}/result', 'Api\QuizController@showResult');
// Route::get('/rrrr', function(){
//     dd('hello');
// });

//category
Route::get('/category_list','Api\CategoryController@get_category_list');
Route::get('/course_category_wise','Api\CategoryController@course_category_wise');

//category

//Registration
Route::post('/register_user','Api\RegistrationController@register_user');
Route::post('/user_login','Api\RegistrationController@login');
Route::post('/verify_opt','Api\RegistrationController@verify_opt');
Route::post('/social_login','Api\RegistrationController@social_login');
Route::post('/Signin','Api\RegistrationController@Signin');

// notification api

// Route::get('notifications', 'Panel\NotificationsController@index');
// Route::post('notifications/saveStatus', 'Panel\NotificationsController@saveStatusNoti');

//end dashboard

 //forgot password
   Route::post('send_otp', 'Api\RestpasswordController@send_otp');
   Route::post('set_new_password', 'Api\RestpasswordController@set_new_password');


Route::group(['middleware' => ['auth:api']], function () {
//dd('hhhh');

    //Comment save
    Route::get('/search','Api\DashboardController@search');
    Route::get('notifications', 'Panel\NotificationsController@index');
    Route::post('notifications/saveStatus', 'Panel\NotificationsController@saveStatusNoti');

    Route::group(['prefix' => 'comments'], function () {
      Route::post('/store', 'Web\CommentController@store');
      Route::post('/{id}/reply', 'Web\CommentController@storeReply');
      // Route::post('/{id}/update', 'Web\CommentController@update');
      // Route::post('/{id}/report', 'Web\CommentController@report');
      // Route::get('/{id}/delete', 'Web\CommentController@destroy');
  });

    Route::post('/update_category', 'Api\CategoryController@update_category');
    Route::get('/get_profile', 'Api\RegistrationController@get_profile');
    Route::post('/update_profile', 'Api\RegistrationController@update_profile');

     Route::post('/change_password', 'Api\RegistrationController@change_password');

     Route::get('/course_list', 'Api\RegistrationController@course_list');

      // Route::get('/course_detail', 'Api\RegistrationController@course_detail');

     Route::get('/logout', 'Api\RegistrationController@logout');


     //quiz

     Route::get('/quiz_list', 'Api\QuizController@quize_list');
      Route::group(['prefix' => 'quizzes'], function () {
      // dd('hello');
        Route::get('/{id}/start', 'Panel\QuizController@start');
        Route::post('/{id}/store-result', 'Panel\QuizController@quizzesStoreResult');
        Route::get('/{quizResultId}/result', 'Api\QuizController@showResult');

        Route::get('/my-results', 'Panel\QuizController@myResults');
        Route::get('/opens', 'Panel\QuizController@opens');


    });
     //end quiz
     //cart controller


      Route::post('/checkout', 'Api\CartController@checkout');
     //end cart controller

     ///subscribes controller
     Route::group(['prefix' => 'subscribes'], function () {
     Route::get('/', 'Api\SubscribesController@index');
     Route::post('/pay-subscribes', 'Api\SubscribesController@pay');
    });

     //dashboard
    Route::get('/dashboard','Api\DashboardController@dashboard');

    Route::get('/demo_video_details','Api\DashboardController@demo_video_details');

     //payment

      Route::group(['prefix' => 'payments'], function () {
       // Route::post('/payment-request', 'PaymentController@paymentRequest');
       // Route::get('/verify/{gateway}', ['as' => 'payment_verify', 'uses' => 'PaymentController@paymentVerify']);
        Route::get('/verify', ['as' => 'payment_verify_post', 'uses' => 'Api\PaymentController@paymentVerify']);
        Route::get('/status', 'Api\PaymentController@payStatus');
       //Route::get('/payku/callback/{id}', 'PaymentController@paykuPaymentVerify')->name('payku.result');
    });

    Route::get('quize_question_list','Api\QuizQuestController@quizquestionlist');
    Route::get('section_question_list','Api\QuizQuestController@sectionquestionlist');

    Route::group(['prefix' => 'favorites'], function () {
            Route::post('toggle', 'Api\FavoriteController@toggle');
            // Route::post('/{id}/update', 'FavoriteController@update');section_question_list
            // Route::get('/{id}/delete', 'FavoriteController@destroy');
            Route::get('toggle_list', 'Api\FavoriteController@toggle_list');
        });

 Route::get('course_detail', 'Api\CourseController@course');
 Route::get('check_buy', 'Api\CourseController@check_buy');

 Route::post('/previousyearpaper', 'Api\CourseController@previousyearpaper');

//reward course
 Route::get('/reward-courses', 'Api\RewardCoursesController@index');



         Route::group(['prefix' => 'purchases'], function () {
            Route::get('/', 'Api\MyPurchaseController@purchases');
           // Route::post('/getJoinInfo', 'WebinarController@getJoinInfo');
        });


         Route::group(['prefix' => 'cart'], function () {
                Route::post('/store', 'Api\CartManagerController@store');
             Route::get('/delete', 'Api\CartManagerController@delete');

            Route::get('/course_list', 'Api\CartController@index');
            Route::post('/buy_now', 'Api\CartController@store');

            Route::post('/coupon/validate', 'Api\CartController@couponValidate');
            Route::post('/checkout', 'Api\CartController@checkout')->name('checkout');
            Route::post('/checkOutFree', 'Api\CartController@checkOutFree')->name('checkOutFree');
        });

       //meeting route
      Route::group(['prefix' => 'meetings'], function () {
        Route::get('/reservation', 'ReserveMeetingController@reservation');
        Route::get('/requests', 'ReserveMeetingController@requests');

        Route::get('/settings', 'MeetingController@setting')->name('meeting_setting');
        Route::get('/create_new', 'MeetingController@create_new')->name('create_new');

         Route::get('/getrecordinglist', 'Api\MeetingController@getrecordinglist');

        Route::get('/manage_meeting', 'Api\MeetingController@manage_meeting')->name('manage_meeting');

         Route::post('update_title', 'MeetingController@update_title');
        Route::post('/{id}/update', 'MeetingController@update');
        Route::post('saveTime', 'MeetingController@saveTime');
        Route::post('deleteTime', 'MeetingController@deleteTime');
        Route::post('temporaryDisableMeetings', 'MeetingController@temporaryDisableMeetings');
          Route::post('joinToBigBlueButton', 'Api\MeetingController@joinToBigBlueButton');


        Route::get('/{id}/join', 'ReserveMeetingController@join');
        Route::post('/create-link', 'ReserveMeetingController@createLink');
        Route::get('/{id}/finish', 'ReserveMeetingController@finish');
    });

     Route::group(['prefix' => 'support'], function () {
        // dd('hello');
        Route::get('/', 'Api\SupportController@index');
        Route::get('/getSuportAndCourse', 'Api\SupportController@create');
        Route::post('/store', 'Api\SupportController@store');
        Route::get('{id}/conversations', 'Api\SupportController@index');
        Route::post('{id}/conversations', 'Api\SupportController@storeConversations');
        Route::get('{id}/close', 'Api\SupportController@close');

        Route::group(['prefix' => 'tickets'], function () {
            Route::get('/', 'Api\SupportController@tickets');
            Route::get('{id}/conversations', 'Api\SupportController@tickets');
        });
    });
});
//registration