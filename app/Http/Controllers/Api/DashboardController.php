<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ValidationErrorTrait;


use App\Models\AdvertisingBanner;
use App\Models\Blog;
use App\Models\Bundle;
use App\Models\FeatureWebinar;
use App\Models\Category;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SpecialOffer;
use App\Models\Subscribe;
use App\Models\Ticket;
use App\Models\TrendCategory;
use App\Models\Webinar;
use App\Models\Testimonial;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Quiz;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    //
     use ValidationErrorTrait;

     public function index(Request $request){
          $homeSections = HomeSection::orderBy('order', 'asc')->get();
        $selectedSectionsName = $homeSections->pluck('name')->toArray();

        $featureWebinars = null;
        if (in_array(HomeSection::$featured_classes, $selectedSectionsName)) {
            $featureWebinars = FeatureWebinar::whereIn('page', ['home', 'home_categories'])
                ->where('status', 'publish')
                ->whereHas('webinar', function ($query) {
                    $query->where('status', Webinar::$active);
                })
                ->with([
                    'webinar' => function ($query) {
                        $query->with([
                            'teacher' => function ($qu) {
                                $qu->select('id', 'full_name', 'avatar');
                            },
                            'reviews' => function ($query) {
                                $query->where('status', 'active');
                            },
                            'tickets',
                            'feature'
                        ]);
                    }
                ])
                ->orderBy('updated_at', 'desc')
                ->get();
            //$selectedWebinarIds = $featureWebinars->pluck('id')->toArray();
        }

        if (in_array(HomeSection::$latest_classes, $selectedSectionsName)) {
            $latestWebinars = Webinar::where('status', Webinar::$active)
                ->where('private', false)
                ->orderBy('updated_at', 'desc')
                ->with([
                    'teacher' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    },
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'tickets',
                    'feature'
                ])
                ->limit(6)
                ->get();

            //$selectedWebinarIds = array_merge($selectedWebinarIds, $latestWebinars->pluck('id')->toArray());
        }

        if (in_array(HomeSection::$latest_bundles, $selectedSectionsName)) {
            $latestBundles = Bundle::where('status', Webinar::$active)
                ->orderBy('updated_at', 'desc')
                ->with([
                    'teacher' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    },
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'tickets',
                ])
                ->limit(6)
                ->get();
        }

        if (in_array(HomeSection::$best_sellers, $selectedSectionsName)) {
            $bestSaleWebinarsIds = Sale::whereNotNull('webinar_id')
                ->select(DB::raw('COUNT(id) as cnt,webinar_id'))
                ->groupBy('webinar_id')
                ->orderBy('cnt', 'DESC')
                ->limit(6)
                ->pluck('webinar_id')
                ->toArray();

            $bestSaleWebinars = Webinar::whereIn('id', $bestSaleWebinarsIds)
                ->where('status', Webinar::$active)
                ->where('private', false)
                ->with([
                    'teacher' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    },
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'sales',
                    'tickets',
                    'feature'
                ])
                ->get();

            //$selectedWebinarIds = array_merge($selectedWebinarIds, $bestSaleWebinars->pluck('id')->toArray());
        }

        if (in_array(HomeSection::$best_rates, $selectedSectionsName)) {
            $bestRateWebinars = Webinar::join('webinar_reviews', 'webinars.id', '=', 'webinar_reviews.webinar_id')
                ->select('webinars.*', 'webinar_reviews.rates', 'webinar_reviews.status', DB::raw('avg(rates) as avg_rates'))
                ->where('webinars.status', 'active')
                ->where('webinars.private', false)
                ->where('webinar_reviews.status', 'active')
                ->groupBy('teacher_id')
                ->orderBy('avg_rates', 'desc')
                ->with([
                    'teacher' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    }
                ])
                ->limit(6)
                ->get();
        }

        // hasDiscountWebinars
        if (in_array(HomeSection::$discount_classes, $selectedSectionsName)) {
            $now = time();
            $webinarIdsHasDiscount = [];

            $tickets = Ticket::where('start_date', '<', $now)
                ->where('end_date', '>', $now)
                ->get();

            foreach ($tickets as $ticket) {
                if ($ticket->isValid()) {
                    $webinarIdsHasDiscount[] = $ticket->webinar_id;
                }
            }

            $specialOffersWebinarIds = SpecialOffer::where('status', 'active')
                ->where('from_date', '<', $now)
                ->where('to_date', '>', $now)
                ->pluck('webinar_id')
                ->toArray();

            $webinarIdsHasDiscount = array_merge($specialOffersWebinarIds, $webinarIdsHasDiscount);

            $hasDiscountWebinars = Webinar::whereIn('id', array_unique($webinarIdsHasDiscount))
                ->where('status', Webinar::$active)
                ->where('private', false)
                ->with([
                    'teacher' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    },
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'sales',
                    'tickets',
                    'feature'
                ])
                ->limit(6)
                ->get();
        }
        // .\ hasDiscountWebinars

        if (in_array(HomeSection::$free_classes, $selectedSectionsName)) {
            $freeWebinars = Webinar::where('status', Webinar::$active)
                ->where('private', false)
                ->where(function ($query) {
                    $query->whereNull('price')
                        ->orWhere('price', '0');
                })
                ->orderBy('updated_at', 'desc')
                ->with([
                    'teacher' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    },
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'tickets',
                    'feature'
                ])
                ->limit(6)
                ->get();
        }

        if (in_array(HomeSection::$store_products, $selectedSectionsName)) {
            $newProducts = Product::where('status', Product::$active)
                ->orderBy('updated_at', 'desc')
                ->with([
                    'creator' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    },
                ])
                ->limit(6)
                ->get();
        }

        if (in_array(HomeSection::$trend_categories, $selectedSectionsName)) {
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
        }

        if (in_array(HomeSection::$blog, $selectedSectionsName)) {
            $blog = Blog::where('status', 'publish')
                ->with(['category', 'author' => function ($query) {
                    $query->select('id', 'full_name');
                }])->orderBy('updated_at', 'desc')
                ->withCount('comments')
                ->orderBy('created_at', 'desc')
                ->limit(3)
                ->get();
        }

        if (in_array(HomeSection::$instructors, $selectedSectionsName)) {
            $instructors = User::where('role_name', Role::$teacher)
                ->select('id', 'full_name', 'avatar', 'bio')
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->where('ban', false)
                        ->orWhere(function ($query) {
                            $query->whereNotNull('ban_end_at')
                                ->where('ban_end_at', '<', time());
                        });
                })
                ->limit(8)
                ->get();
        }

        if (in_array(HomeSection::$organizations, $selectedSectionsName)) {
            $organizations = User::where('role_name', Role::$organization)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->where('ban', false)
                        ->orWhere(function ($query) {
                            $query->whereNotNull('ban_end_at')
                                ->where('ban_end_at', '<', time());
                        });
                })
                ->withCount('webinars')
                ->orderBy('webinars_count', 'desc')
                ->limit(6)
                ->get();
        }

        if (in_array(HomeSection::$testimonials, $selectedSectionsName)) {
            $testimonials = Testimonial::where('status', 'active')->get();
        }

        if (in_array(HomeSection::$subscribes, $selectedSectionsName)) {
            $subscribes = Subscribe::all();
        }

        if (in_array(HomeSection::$find_instructors, $selectedSectionsName)) {
            $findInstructorSection = getFindInstructorsSettings();
        }

        if (in_array(HomeSection::$reward_program, $selectedSectionsName)) {
            $rewardProgramSection = getRewardProgramSettings();
        }


        if (in_array(HomeSection::$become_instructor, $selectedSectionsName)) {
            $becomeInstructorSection = getBecomeInstructorSectionSettings();
        }


        if (in_array(HomeSection::$forum_section, $selectedSectionsName)) {
            $forumSection = getForumSectionSettings();
        }

        $advertisingBanners = AdvertisingBanner::where('published', true)
            ->where('position', 'App')
            ->get();

        $skillfulTeachersCount = User::where('role_name', Role::$teacher)
            ->where(function ($query) {
                $query->where('ban', false)
                    ->orWhere(function ($query) {
                        $query->whereNotNull('ban_end_at')
                            ->where('ban_end_at', '<', time());
                    });
            })
            ->where('status', 'active')
            ->count();

        $studentsCount = User::where('role_name', Role::$user)
            ->where(function ($query) {
                $query->where('ban', false)
                    ->orWhere(function ($query) {
                        $query->whereNotNull('ban_end_at')
                            ->where('ban_end_at', '<', time());
                    });
            })
            ->where('status', 'active')
            ->count();

        $liveClassCount = Webinar::where('type', 'webinar')
            ->where('status', 'active')
            ->count();

        $offlineCourseCount = Webinar::where('status', 'active')
            ->whereIn('type', ['course', 'text_lesson'])
            ->count();

        $siteGeneralSettings = getGeneralSettings();
        $heroSection = (!empty($siteGeneralSettings['hero_section2']) and $siteGeneralSettings['hero_section2'] == "1") ? "2" : "1";
        $heroSectionData = getHomeHeroSettings($heroSection);

        if (in_array(HomeSection::$video_or_image_section, $selectedSectionsName)) {
            $boxVideoOrImage = getHomeVideoOrImageBoxSettings();
        }

        $seoSettings = getSeoMetas('home');
        $pageTitle = !empty($seoSettings['title']) ? $seoSettings['title'] : trans('home.home_title');
        $pageDescription = !empty($seoSettings['description']) ? $seoSettings['description'] : trans('home.home_title');
        $pageRobot = getPageRobot('home');
            $course_list=   Webinar::where('category_id',Auth::user()->course_category_id)->where('type','course')->where('status', Webinar::$active)->where('private', false)->orderBy('updated_at', 'desc')
                ->with([
                    'teacher' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    },
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'tickets',
                    'feature'
                ])->get();
    $course_category=Category::where('parent_id','=',null)->where('id',Auth::user()->course_category_id)->with(['subCategories'])->first();

        $data = [
            'pageTitle' => $pageTitle,
            'pageDescription' => $pageDescription,
            'pageRobot' => $pageRobot,
            'heroSection' => $heroSection,
            'heroSectionData' => $heroSectionData,
            'homeSections' => $homeSections,
            'featureWebinars' => $featureWebinars,
            'latestWebinars' => $latestWebinars ?? [],
            'latestBundles' => $latestBundles ?? [],
            'bestSaleWebinars' => $bestSaleWebinars ?? [],
            'hasDiscountWebinars' => $hasDiscountWebinars ?? [],
            'bestRateWebinars' => $bestRateWebinars ?? [],
            'freeWebinars' => $freeWebinars ?? [],
            'newProducts' => $newProducts ?? [],
            'trendCategories' => $trendCategories ?? [],
            'instructors' => $instructors ?? [],
            'testimonials' => $testimonials ?? [],
            'subscribes' => $subscribes ?? [],
            'blog' => $blog ?? [],
            'organizations' => $organizations ?? [],
            'advertisingBanners1' => $advertisingBanners,
           // 'advertisingBanners2' => $advertisingBanners->where('position', 'home2'),
            'skillfulTeachersCount' => $skillfulTeachersCount,
            'studentsCount' => $studentsCount,
            'liveClassCount' => $liveClassCount,
            'offlineCourseCount' => $offlineCourseCount,
            'boxVideoOrImage' => $boxVideoOrImage ?? null,
            'findInstructorSection' => $findInstructorSection ?? null,
            'rewardProgramSection' => $rewardProgramSection ?? null,
            'becomeInstructorSection' => $becomeInstructorSection ?? null,
            'forumSection' => $forumSection ?? null,
            'course_list'=>$course_list,
            'category'=>$course_category,
        ];

         if($data){
                    return response(['statusCode' => 200, 'message' => 'Home page data','data'=>$data]);
                }else{
                      return response(['statusCode' => 400, 'message' => 'No data found']);
                }
     }

    //  public function dashboard(Request $request){

    //       $validator = Validator::make($request->all(), [

    //         'category_id' => 'required'

    //     ]);

    //     if ($validator->fails()) {
    //         $messages = implode(",", $this->errorMessages($validator->errors()));
    //         return response(['statusCode' => 400, 'message' => $messages]);
    //     }


    //          $demo_video=   Webinar::where('category_id',$request->category_id)->where('status', Webinar::$active)->where('private', false)->orderBy('updated_at', 'desc')
    //             ->with([
    //                 'teacher' => function ($qu) {
    //                     $qu->select('id', 'full_name', 'avatar');
    //                 },
    //                 'reviews' => function ($query) {
    //                     $query->where('status', 'active');
    //                 },
    //                 'tickets',
    //                 'feature'
    //             ])->get();

    //             $course_list=   Webinar::where('category_id',$request->category_id)->where('type','course')->where('status', Webinar::$active)->where('private', false)->orderBy('updated_at', 'desc')
    //             ->with([
    //                 'teacher' => function ($qu) {
    //                     $qu->select('id', 'full_name', 'avatar');
    //                 },
    //                 'reviews' => function ($query) {
    //                     $query->where('status', 'active');
    //                 },
    //                 'tickets',
    //                 'feature'
    //             ])->get();

    //             $rewardProgramSection = getRewardProgramSettings();
    //              $subscribes = Subscribe::all();
    //               $allQuizzesLists = Quiz::select('id', 'webinar_title')

    //             ->where('status', 'active')
    //             ->get();

    //             $homepage_data=[
    //                 'demo_video'=> $demo_video,
    //                 'course_list'=>$course_list,
    //                 'rewardProgramSection'=>$rewardProgramSection,
    //                 'subscribes'=>$subscribes,
    //                 'allQuizzesLists'=>$allQuizzesLists

    //                 ];



    //         if($demo_video){
    //                 return response(['statusCode' => 200, 'message' => 'Home page data','data'=>$homepage_data]);
    //             }else{
    //                   return response(['statusCode' => 400, 'message' => 'No data found']);
    //             }
    //  }

     public function demo_video_details(Request $request){

         $validator = Validator::make($request->all(), [

            'course_id' => 'required'

        ]);

        if ($validator->fails()) {
            $messages = implode(",", $this->errorMessages($validator->errors()));
            return response(['statusCode' => 400, 'message' => $messages]);
        }

         $demo_video=   Webinar::where('id',$request->course_id)->where('status', Webinar::$active)->where('private', false)->orderBy('updated_at', 'desc')
                ->with([
                    'teacher' => function ($qu) {
                        $qu->select('id', 'full_name', 'avatar');
                    },
                    'reviews' => function ($query) {
                        $query->where('status', 'active');
                    },
                    'tickets',
                    'feature'
                ])->first();
                  if($demo_video){
                    return response(['statusCode' => 200, 'message' => 'Home page data','data'=>$demo_video]);
                }else{
                      return response(['statusCode' => 400, 'message' => 'No data found']);
                }
     }

     public function search(Request $request){
        $user = Auth::user();
        $q = $request->search_key;
        // dd($user);
        $sub_id = Category::where('parent_id',$user->course_category_id)->pluck('id');
        // dd($sub_id);
        $filterprd = Webinar::where('slug','like','%'.$q.'%')->whereIn('webinars.category_id',  $sub_id)->where('status', 'active')->get();
    //   dd($filterprd);
        return response(['statusCode' => 200, 'message' => 'search  data get','data'=>$filterprd]);

     }


    //  public function dashboard(Request $request)
    // {
    //     // dd('hello');
    //     $homeSections = HomeSection::orderBy('order', 'asc')->get();
    //     $selectedSectionsName = $homeSections->pluck('name')->toArray();

    //     $featureWebinars = null;


    //     if (in_array(HomeSection::$featured_classes, $selectedSectionsName)) {
    //         $featureWebinars = FeatureWebinar::whereIn('page', ['home', 'home_categories'])
    //             ->where('status', 'publish')
    //             ->whereHas('webinar', function ($query) {
    //                 $query->where('status', Webinar::$active);
    //             })
    //             ->with([
    //                 'webinar' => function ($query) {
    //                     $query->with([
    //                         'teacher' => function ($qu) {
    //                             $qu->select('id', 'full_name', 'avatar');
    //                         },
    //                         'reviews' => function ($query) {
    //                             $query->where('status', 'active');
    //                         },
    //                         'tickets',
    //                         'feature'
    //                     ]);
    //                 }
    //             ])
    //             ->orderBy('updated_at', 'desc')
    //             ->get();
    //         //$selectedWebinarIds = $featureWebinars->pluck('id')->toArray();
    //     }
    //     $sub_id = Category::where('parent_id',Auth::user()->course_category_id)->pluck('id');
    //     // dd($sub_id);
    //   // foreach($getAllSubcategoryId as $sub_id){
    //     if (in_array(HomeSection::$latest_classes, $selectedSectionsName)) {
    //         // foreach(){
    //             $latestWebinars = Webinar::where('status', Webinar::$active)
    //             ->where('private', false)
    //             ->whereNotIn('price', ['null',0])
    //             ->whereIn('category_id',  $sub_id)
    //             ->orderBy('updated_at', 'desc')
    //             ->with([
    //                 'teacher' => function ($qu) {
    //                     $qu->select('id', 'full_name', 'avatar');
    //                 },
    //                 'reviews' => function ($query) {
    //                     $query->where('status', 'active');
    //                 },
    //                 'tickets',
    //                 'feature'
    //             ])
    //           // ->limit(6)
    //             ->get();
    //         // }
    //             // dd($latestWebinars);
    //         //$selectedWebinarIds = array_merge($selectedWebinarIds, $latestWebinars->pluck('id')->toArray());
    //     }

    //     if (in_array(HomeSection::$best_rates, $selectedSectionsName)) {
    //         $bestRateWebinars = Webinar::join('webinar_reviews', 'webinars.id', '=', 'webinar_reviews.webinar_id')

    //             ->select('webinars.*', 'webinar_reviews.rates', 'webinar_reviews.status', DB::raw('avg(rates) as avg_rates'))
    //             ->where('webinars.status', 'active')
    //             ->where('webinars.private', false)
    //             ->whereIn('webinars.category_id', $sub_id)
    //             ->where('webinar_reviews.status', 'active')
    //             ->groupBy('teacher_id')
    //             ->orderBy('avg_rates', 'desc')
    //             ->with([
    //                 'teacher' => function ($qu) {
    //                     $qu->select('id', 'full_name', 'avatar');
    //                 }
    //             ])
    //           // ->limit(6)
    //             ->get();
    //     }

    //     if (in_array(HomeSection::$discount_classes, $selectedSectionsName)) {
    //         $now = time();
    //         $webinarIdsHasDiscount = [];

    //         $tickets = Ticket::where('start_date', '<', $now)
    //             ->where('end_date', '>', $now)
    //             ->get();

    //         foreach ($tickets as $ticket) {
    //             if ($ticket->isValid()) {
    //                 $webinarIdsHasDiscount[] = $ticket->webinar_id;
    //             }
    //         }

    //         $specialOffersWebinarIds = SpecialOffer::where('status', 'active')
    //             ->where('from_date', '<', $now)
    //             ->where('to_date', '>', $now)
    //             ->pluck('webinar_id')
    //             ->toArray();

    //         $webinarIdsHasDiscount = array_merge($specialOffersWebinarIds, $webinarIdsHasDiscount);

    //         $hasDiscountWebinars = Webinar::whereIn('id', array_unique($webinarIdsHasDiscount))
    //             ->where('status', Webinar::$active)
    //             ->whereIn('category_id', $sub_id)
    //             ->where('private', false)
    //             ->with([
    //                 'teacher' => function ($qu) {
    //                     $qu->select('id', 'full_name', 'avatar');
    //                 },
    //                 'reviews' => function ($query) {
    //                     $query->where('status', 'active');
    //                 },
    //                 'sales',
    //                 'tickets',
    //                 'feature'
    //             ])
    //           // ->limit(6)
    //             ->get();
    //     }

    //     if (in_array(HomeSection::$free_classes, $selectedSectionsName)) {
    //         $freeWebinars = Webinar::where('status', Webinar::$active)
    //             ->where('private', false)
    //             ->whereIn('category_id', $sub_id)
    //             ->where(function ($query) {
    //                 $query->whereNull('price')
    //                     ->orWhere('price', '0');
    //             })
    //             ->orderBy('updated_at', 'desc')
    //             ->with([
    //                 'teacher' => function ($qu) {
    //                     $qu->select('id', 'full_name', 'avatar');
    //                 },
    //                 'reviews' => function ($query) {
    //                     $query->where('status', 'active');
    //                 },
    //                 'tickets',
    //                 'feature'
    //             ])
    //             //->limit(6)
    //             ->get();
    //     }

    //     if (in_array(HomeSection::$blog, $selectedSectionsName)) {
    //         $blog = Blog::where('status', 'publish')
    //         ->whereIn('category_id', $sub_id)
    //             ->with(['category', 'author' => function ($query) {
    //                 $query->select('id', 'full_name');
    //             }])->orderBy('updated_at', 'desc')
    //             ->withCount('comments')
    //             ->orderBy('created_at', 'desc')
    //           // ->limit(3)
    //             ->get();
    //     }


    //   // }


    //     // if (in_array(HomeSection::$latest_bundles, $selectedSectionsName)) {
    //     //     $latestBundles = Bundle::where('status', Webinar::$active)
    //     //     ->where('category_id', Auth::user()->course_category_id)
    //     //         ->orderBy('updated_at', 'desc')
    //     //         ->with([
    //     //             'teacher' => function ($qu) {
    //     //                 $qu->select('id', 'full_name', 'avatar');
    //     //             },
    //     //             'reviews' => function ($query) {
    //     //                 $query->where('status', 'active');
    //     //             },
    //     //             'tickets',
    //     //         ])
    //     //         ->limit(6)
    //     //         ->get();
    //     // }

    //     // if (in_array(HomeSection::$best_sellers, $selectedSectionsName)) {
    //     //     $bestSaleWebinarsIds = Sale::whereNotNull('webinar_id')
    //     //      //->where('category_id', Auth::user()->course_category_id)
    //     //         ->select(DB::raw('COUNT(id) as cnt,webinar_id'))
    //     //         ->groupBy('webinar_id')
    //     //         ->orderBy('cnt', 'DESC')
    //     //         ->limit(6)
    //     //         ->pluck('webinar_id')
    //     //         ->toArray();

    //     //     $bestSaleWebinars = Webinar::whereIn('id', $bestSaleWebinarsIds)
    //     //         ->where('status', Webinar::$active)
    //     //         ->where('category_id', Auth::user()->course_category_id)
    //     //         ->where('private', false)
    //     //         ->with([
    //     //             'teacher' => function ($qu) {
    //     //                 $qu->select('id', 'full_name', 'avatar');
    //     //             },
    //     //             'reviews' => function ($query) {
    //     //                 $query->where('status', 'active');
    //     //             },
    //     //             'sales',
    //     //             'tickets',
    //     //             'feature'
    //     //         ])
    //     //         ->get();

    //     //     //$selectedWebinarIds = array_merge($selectedWebinarIds, $bestSaleWebinars->pluck('id')->toArray());
    //     // }



    //     // hasDiscountWebinars

    //     // .\ hasDiscountWebinars



    //     // if (in_array(HomeSection::$store_products, $selectedSectionsName)) {
    //     //     $newProducts = Product::where('status', Product::$active)
    //     //         ->orderBy('updated_at', 'desc')
    //     //         ->with([
    //     //             'creator' => function ($qu) {
    //     //                 $qu->select('id', 'full_name', 'avatar');
    //     //             },
    //     //         ])
    //     //         ->limit(6)
    //     //         ->get();
    //     // }

    //     // if (in_array(HomeSection::$trend_categories, $selectedSectionsName)) {
    //     //     $trendCategories = TrendCategory::with([
    //     //         'category' => function ($query) {
    //     //             $query->withCount([
    //     //                 'webinars' => function ($query) {
    //     //                     $query->where('status', 'active');
    //     //                 }
    //     //             ]);
    //     //         }
    //     //     ])->orderBy('created_at', 'desc')
    //     //         ->limit(6)
    //     //         ->get();
    //     // }



    //     // if (in_array(HomeSection::$instructors, $selectedSectionsName)) {
    //     //     $instructors = User::where('role_name', Role::$teacher)
    //     //         ->select('id', 'full_name', 'avatar', 'bio')
    //     //         ->where('status', 'active')
    //     //         ->where(function ($query) {
    //     //             $query->where('ban', false)
    //     //                 ->orWhere(function ($query) {
    //     //                     $query->whereNotNull('ban_end_at')
    //     //                         ->where('ban_end_at', '<', time());
    //     //                 });
    //     //         })
    //     //         ->limit(8)
    //     //         ->get();
    //     // }

    //     // if (in_array(HomeSection::$organizations, $selectedSectionsName)) {
    //     //     $organizations = User::where('role_name', Role::$organization)
    //     //         ->where('status', 'active')
    //     //         ->where(function ($query) {
    //     //             $query->where('ban', false)
    //     //                 ->orWhere(function ($query) {
    //     //                     $query->whereNotNull('ban_end_at')
    //     //                         ->where('ban_end_at', '<', time());
    //     //                 });
    //     //         })
    //     //         ->withCount('webinars')
    //     //         ->orderBy('webinars_count', 'desc')
    //     //         ->limit(6)
    //     //         ->get();
    //     // }

    //     // if (in_array(HomeSection::$testimonials, $selectedSectionsName)) {
    //     //     $testimonials = Testimonial::where('status', 'active')->get();
    //     // }

    //     // if (in_array(HomeSection::$subscribes, $selectedSectionsName)) {
    //     //     $subscribes = Subscribe::all();
    //     // }

    //     // if (in_array(HomeSection::$find_instructors, $selectedSectionsName)) {
    //     //     $findInstructorSection = getFindInstructorsSettings();
    //     // }

    //     // if (in_array(HomeSection::$reward_program, $selectedSectionsName)) {
    //     //     $rewardProgramSection = getRewardProgramSettings();
    //     // }


    //     // if (in_array(HomeSection::$become_instructor, $selectedSectionsName)) {
    //     //     $becomeInstructorSection = getBecomeInstructorSectionSettings();
    //     // }


    //     // if (in_array(HomeSection::$forum_section, $selectedSectionsName)) {
    //     //     $forumSection = getForumSectionSettings();
    //     // }

    //     $advertisingBanners = AdvertisingBanner::where('published', true)
    //         ->whereIn('position', ['home1', 'home2'])
    //         ->get();

    //     // $skillfulTeachersCount = User::where('role_name', Role::$teacher)
    //     //     ->where(function ($query) {
    //     //         $query->where('ban', false)
    //     //             ->orWhere(function ($query) {
    //     //                 $query->whereNotNull('ban_end_at')
    //     //                     ->where('ban_end_at', '<', time());
    //     //             });
    //     //     })
    //     //     ->where('status', 'active')
    //     //     ->count();

    //     // $studentsCount = User::where('role_name', Role::$user)
    //     //     ->where(function ($query) {
    //     //         $query->where('ban', false)
    //     //             ->orWhere(function ($query) {
    //     //                 $query->whereNotNull('ban_end_at')
    //     //                     ->where('ban_end_at', '<', time());
    //     //             });
    //     //     })
    //     //     ->where('status', 'active')
    //     //     ->count();

    //     // $liveClassCount = Webinar::where('type', 'webinar')
    //     //     ->where('status', 'active')
    //     //     ->count();

    //     // $offlineCourseCount = Webinar::where('status', 'active')
    //     //     ->whereIn('type', ['course', 'text_lesson'])
    //     //     ->count();

    //     // $siteGeneralSettings = getGeneralSettings();
    //     // $heroSection = (!empty($siteGeneralSettings['hero_section2']) and $siteGeneralSettings['hero_section2'] == "1") ? "2" : "1";
    //     // $heroSectionData = getHomeHeroSettings($heroSection);

    //     if (in_array(HomeSection::$video_or_image_section, $selectedSectionsName)) {
    //         $boxVideoOrImage = getHomeVideoOrImageBoxSettings();
    //     }

    //     $seoSettings = getSeoMetas('home');
    //     $pageTitle = !empty($seoSettings['title']) ? $seoSettings['title'] : trans('home.home_title');
    //     $pageDescription = !empty($seoSettings['description']) ? $seoSettings['description'] : trans('home.home_title');
    //     $pageRobot = getPageRobot('home');
    //     $course_category=Category::where('parent_id','=',null)->whereNotIn('id',[673,674,])->where('id',Auth::user()->course_category_id)->with(['subCategories'])->first();
    //     $CurrentAffair = Blog::where('category_id','38')->orderBy('created_at','DESC')->get();
    //     $LatestNotification = Blog::where('category_id','39')->orderBy('created_at','DESC')->get();
    //      $UpcomingNotification = Blog::where('category_id','40')->orderBy('created_at','DESC')->get();
    //     $data = [
    //         'pageTitle' => $pageTitle,
    //         'pageDescription' => $pageDescription,
    //         'pageRobot' => $pageRobot,
    //       // 'heroSection' => $heroSection,
    //       //  'heroSectionData' => $heroSectionData,
    //         'homeSections' => $homeSections,
    //         'featureWebinars' => $featureWebinars,
    //         'latestWebinars' => $latestWebinars ?? [],
    //         'latestBundles' => $latestBundles ?? [],
    //         'bestSaleWebinars' => $bestSaleWebinars ?? [],
    //         'hasDiscountWebinars' => $hasDiscountWebinars ?? [],
    //         'bestRateWebinars' => $bestRateWebinars ?? [],
    //         'freeWebinars' => $freeWebinars ?? [],
    //       // 'newProducts' => $newProducts ?? [],
    //         'trendCategories' => $trendCategories ?? [],
    //         'instructors' => $instructors ?? [],
    //         'testimonials' => $testimonials ?? [],
    //         'subscribes' => $subscribes ?? [],
    //         'blog' => $blog ?? [],
    //         'organizations' => $organizations ?? [],
    //         'advertisingBanners1' => $advertisingBanners->where('position', 'home1'),
    //         'advertisingBanners2' => $advertisingBanners->where('position', 'home2'),
    //         // 'skillfulTeachersCount' => $skillfulTeachersCount,
    //         // 'studentsCount' => $studentsCount,
    //         // 'liveClassCount' => $liveClassCount,
    //         // 'offlineCourseCount' => $offlineCourseCount,
    //         'boxVideoOrImage' => $boxVideoOrImage ?? null,
    //       // 'findInstructorSection' => $findInstructorSection ?? null,
    //         //'rewardProgramSection' => $rewardProgramSection ?? null,
    //         //'becomeInstructorSection' => $becomeInstructorSection ?? null,
    //         //'forumSection' => $forumSection ?? null,
    //         'category'=>$course_category ?? null,
    //         'CurrentAffair'=>$CurrentAffair ?? [],
    //         'LatestNotification'=>$LatestNotification ?? [],
    //         'UpcomingNotification' => $UpcomingNotification ?? [],
    //     ];

    //     if($data){
    //         return response(['statusCode' => 200, 'message' => 'Home page data','data'=>$data]);
    //     }else{
    //           return response(['statusCode' => 400, 'message' => 'No data found']);
    //     }
    // }
    
    
    public function dashboard(Request $request)
    {

        $homeSections = HomeSection::orderBy('order', 'asc')->get();
        $selectedSectionsName = $homeSections->pluck('name')->toArray();
        //dd($homeSections,$selectedSectionsName);
        $featureWebinars = null;
        // $select = ['id','type','slug','thumbnail','price'];

        // if (in_array(HomeSection::$featured_classes, $selectedSectionsName)) {
        //     $featureWebinars = FeatureWebinar::whereIn('page', ['home', 'home_categories'])
        //         ->where('status', 'publish')
        //         ->whereHas('webinar', function ($query) {
        //             $query->where('status', Webinar::$active);
        //         })
        //         ->with([
        //             'webinar' => function ($query) {
        //                 $query->select('id' ,'type','slug','thumbnail','price');
        //                 // $query->with([
        //                 //     'teacher' => function ($qu) {
        //                 //         $qu->select('id', 'full_name', 'avatar');
        //                 //     },
        //                 //     'reviews' => function ($query) {
        //                 //         $query->where('status', 'active');
        //                 //     },
        //                 //     'tickets',
        //                 //     'feature'
        //                 // ]);
        //             }
        //         ])
        //         ->orderBy('updated_at', 'desc')
        //         ->get();
        //       // dd($featureWebinars);
        //     //$selectedWebinarIds = $featureWebinars->pluck('id')->toArray();
        // }//dd('kk');
        $sub_id = Category::where('parent_id',Auth::user()->course_category_id)->pluck('id');
       // dd($getAllSubcategoryId);
      // foreach($getAllSubcategoryId as $sub_id){
        if (in_array(HomeSection::$latest_classes, $selectedSectionsName)) {
            // foreach(){
                $latestWebinars = DB::table('webinars')->join('webinar_translations','webinar_translations.webinar_id','webinars.id')
                ->where('webinars.status', Webinar::$active)
                ->where('webinars.private', false)
                ->whereNotIn('webinars.price', ['nul'])
                ->whereIn('webinars.category_id',  $sub_id)
                ->orderBy('webinars.updated_at', 'desc')
                ->select('webinars.id','webinars.type','webinars.slug','webinars.thumbnail','webinars.price','webinar_translations.title')
               // ->limit(6)
                ->get();
            // }
                
            //$selectedWebinarIds = array_merge($selectedWebinarIds, $latestWebinars->pluck('id')->toArray());
        }

        // if (in_array(HomeSection::$best_rates, $selectedSectionsName)) {
        //     $bestRateWebinars = Webinar::join('webinar_reviews', 'webinars.id', '=', 'webinar_reviews.webinar_id')

        //         ->select('webinars.type','webinars.slug','webinars.price','webinars.thumbnail', 'webinar_reviews.rates', 'webinar_reviews.status', DB::raw('avg(rates) as avg_rates'))
        //         ->where('webinars.status', 'active')
        //         ->where('webinars.private', false)
        //         ->whereIn('webinars.category_id', $sub_id)
        //         ->where('webinar_reviews.status', 'active')
        //         ->groupBy('teacher_id')
        //         ->orderBy('avg_rates', 'desc')
        //         // ->with([
        //         //     'teacher' => function ($qu) {
        //         //         $qu->select('id', 'full_name', 'avatar');
        //         //     }
        //         // ])
        //       // ->limit(6)
        //         ->get();
        //         // dd($bestRateWebinars);
        // }

        if (in_array(HomeSection::$discount_classes, $selectedSectionsName)) {
            $now = time();
            $webinarIdsHasDiscount = [];

            $tickets = Ticket::where('start_date', '<', $now)
                ->where('end_date', '>', $now)
                ->get();
// dd($tickets);
            foreach ($tickets as $ticket) {
                if ($ticket->isValid()) {
                    $webinarIdsHasDiscount[] = $ticket->webinar_id;
                }
            }

            $specialOffersWebinarIds = SpecialOffer::where('status', 'active')
                ->where('from_date', '<', $now)
                ->where('to_date', '>', $now)
                ->pluck('webinar_id')
                ->toArray();
    // dd($specialOffersWebinarIds);
  
            $webinarIdsHasDiscount = array_merge($specialOffersWebinarIds, $webinarIdsHasDiscount);
            
            $hasDiscountWebinars = DB::table('webinars')->join('webinar_translations','webinar_translations.webinar_id','webinars.id')->whereIn('webinars.id', array_unique($webinarIdsHasDiscount))
                ->where('status', Webinar::$active)
                ->whereIn('webinars.category_id', $sub_id)
                ->where('webinars.private', false)
                 ->select('webinars.id','webinars.type','webinars.slug','webinars.thumbnail','webinars.price','webinar_translations.title')
                // ->with([
                //     'teacher' => function ($qu) {
                //         $qu->select('id', 'full_name', 'avatar');
                //     },
                //     'reviews' => function ($query) {
                //         $query->where('status', 'active');
                //     },
                //     'sales',
                //     'tickets',
                //     'feature'
                // ])
               // ->limit(6)
                ->get();
                //dd($hasDiscountWebinars);
        }
          DB::table('webinars')->join('webinar_translations','webinar_translations.webinar_id','webinars.id')
                ->where('webinars.status', Webinar::$active)
                ->where('webinars.private', false)
                ->whereNotIn('webinars.price', ['nul'])
                ->whereIn('webinars.category_id',  $sub_id)
                ->orderBy('webinars.updated_at', 'desc')
                ->select('webinars.id','webinars.type','webinars.slug','webinars.thumbnail','webinars.price','webinar_translations.title')
               // ->limit(6)
                ->get();
        if (in_array(HomeSection::$free_classes, $selectedSectionsName)) {
            $freeWebinars = DB::table('webinars')->join('webinar_translations','webinar_translations.webinar_id','webinars.id')
                ->where('webinars.private', false)
                ->whereIn('webinars.category_id', $sub_id)
                ->where(function ($query) {
                    $query->whereNull('webinars.price')
                        ->orWhere('webinars.price', '0');
                })
               ->select('webinars.id','webinars.type','webinars.slug','webinars.thumbnail','webinars.price','webinar_translations.title')
                ->orderBy('webinars.updated_at', 'desc')
                // ->with([
                //     'teacher' => function ($qu) {
                //         $qu->select('id', 'full_name', 'avatar');
                //     },
                //     'reviews' => function ($query) {
                //         $query->where('status', 'active');
                //     },
                //     'tickets',
                //     'feature'
                // ])
                //->limit(6)
                ->get();
                // dd($freeWebinars);
        }

        // if (in_array(HomeSection::$blog, $selectedSectionsName)) {
        //     $blog = Blog::where('status', 'publish')
        //     ->whereIn('category_id', $sub_id)
        //         ->with(['category', 'author' => function ($query) {
        //             $query->select('id', 'full_name');
        //         }])->orderBy('updated_at', 'desc')
        //         ->withCount('comments')
        //         ->orderBy('created_at', 'desc')
        //       // ->limit(3)
        //         ->get();
        // }


      // }


        // if (in_array(HomeSection::$latest_bundles, $selectedSectionsName)) {
        //     $latestBundles = Bundle::where('status', Webinar::$active)
        //     ->where('category_id', Auth::user()->course_category_id)
        //         ->orderBy('updated_at', 'desc')
        //         ->with([
        //             'teacher' => function ($qu) {
        //                 $qu->select('id', 'full_name', 'avatar');
        //             },
        //             'reviews' => function ($query) {
        //                 $query->where('status', 'active');
        //             },
        //             'tickets',
        //         ])
        //         ->limit(6)
        //         ->get();
        // }

        // if (in_array(HomeSection::$best_sellers, $selectedSectionsName)) {
        //     $bestSaleWebinarsIds = Sale::whereNotNull('webinar_id')
        //      //->where('category_id', Auth::user()->course_category_id)
        //         ->select(DB::raw('COUNT(id) as cnt,webinar_id'))
        //         ->groupBy('webinar_id')
        //         ->orderBy('cnt', 'DESC')
        //         ->limit(6)
        //         ->pluck('webinar_id')
        //         ->toArray();

        //     $bestSaleWebinars = Webinar::whereIn('id', $bestSaleWebinarsIds)
        //         ->where('status', Webinar::$active)
        //         ->where('category_id', Auth::user()->course_category_id)
        //         ->where('private', false)
        //         ->with([
        //             'teacher' => function ($qu) {
        //                 $qu->select('id', 'full_name', 'avatar');
        //             },
        //             'reviews' => function ($query) {
        //                 $query->where('status', 'active');
        //             },
        //             'sales',
        //             'tickets',
        //             'feature'
        //         ])
        //         ->get();

        //     //$selectedWebinarIds = array_merge($selectedWebinarIds, $bestSaleWebinars->pluck('id')->toArray());
        // }



        // hasDiscountWebinars

        // .\ hasDiscountWebinars



        // if (in_array(HomeSection::$store_products, $selectedSectionsName)) {
        //     $newProducts = Product::where('status', Product::$active)
        //         ->orderBy('updated_at', 'desc')
        //         ->with([
        //             'creator' => function ($qu) {
        //                 $qu->select('id', 'full_name', 'avatar');
        //             },
        //         ])
        //         ->limit(6)
        //         ->get();
        // }

        // if (in_array(HomeSection::$trend_categories, $selectedSectionsName)) {
        //     $trendCategories = TrendCategory::with([
        //         'category' => function ($query) {
        //             $query->withCount([
        //                 'webinars' => function ($query) {
        //                     $query->where('status', 'active');
        //                 }
        //             ]);
        //         }
        //     ])->orderBy('created_at', 'desc')
        //         ->limit(6)
        //         ->get();
        // }



        // if (in_array(HomeSection::$instructors, $selectedSectionsName)) {
        //     $instructors = User::where('role_name', Role::$teacher)
        //         ->select('id', 'full_name', 'avatar', 'bio')
        //         ->where('status', 'active')
        //         ->where(function ($query) {
        //             $query->where('ban', false)
        //                 ->orWhere(function ($query) {
        //                     $query->whereNotNull('ban_end_at')
        //                         ->where('ban_end_at', '<', time());
        //                 });
        //         })
        //         ->limit(8)
        //         ->get();
        // }

        // if (in_array(HomeSection::$organizations, $selectedSectionsName)) {
        //     $organizations = User::where('role_name', Role::$organization)
        //         ->where('status', 'active')
        //         ->where(function ($query) {
        //             $query->where('ban', false)
        //                 ->orWhere(function ($query) {
        //                     $query->whereNotNull('ban_end_at')
        //                         ->where('ban_end_at', '<', time());
        //                 });
        //         })
        //         ->withCount('webinars')
        //         ->orderBy('webinars_count', 'desc')
        //         ->limit(6)
        //         ->get();
        // }

        // if (in_array(HomeSection::$testimonials, $selectedSectionsName)) {
        //     $testimonials = Testimonial::where('status', 'active')->get();
        // }

        // if (in_array(HomeSection::$subscribes, $selectedSectionsName)) {
        //     $subscribes = Subscribe::all();
        // }

        // if (in_array(HomeSection::$find_instructors, $selectedSectionsName)) {
        //     $findInstructorSection = getFindInstructorsSettings();
        // }

        // if (in_array(HomeSection::$reward_program, $selectedSectionsName)) {
        //     $rewardProgramSection = getRewardProgramSettings();
        // }


        // if (in_array(HomeSection::$become_instructor, $selectedSectionsName)) {
        //     $becomeInstructorSection = getBecomeInstructorSectionSettings();
        // }


        // if (in_array(HomeSection::$forum_section, $selectedSectionsName)) {
        //     $forumSection = getForumSectionSettings();
        // }

        $advertisingBanners = AdvertisingBanner::where('published', true)->where('cat_id',Auth::user()->course_category_id)
            ->where('position', 'App')
            ->select('link','id')
            ->get();

        // $skillfulTeachersCount = User::where('role_name', Role::$teacher)
        //     ->where(function ($query) {
        //         $query->where('ban', false)
        //             ->orWhere(function ($query) {
        //                 $query->whereNotNull('ban_end_at')
        //                     ->where('ban_end_at', '<', time());
        //             });
        //     })
        //     ->where('status', 'active')
        //     ->count();

        // $studentsCount = User::where('role_name', Role::$user)
        //     ->where(function ($query) {
        //         $query->where('ban', false)
        //             ->orWhere(function ($query) {
        //                 $query->whereNotNull('ban_end_at')
        //                     ->where('ban_end_at', '<', time());
        //             });
        //     })
        //     ->where('status', 'active')
        //     ->count();

        // $liveClassCount = Webinar::where('type', 'webinar')
        //     ->where('status', 'active')
        //     ->count();

        // $offlineCourseCount = Webinar::where('status', 'active')
        //     ->whereIn('type', ['course', 'text_lesson'])
        //     ->count();

        // $siteGeneralSettings = getGeneralSettings();
        // $heroSection = (!empty($siteGeneralSettings['hero_section2']) and $siteGeneralSettings['hero_section2'] == "1") ? "2" : "1";
        // $heroSectionData = getHomeHeroSettings($heroSection);

        // if (in_array(HomeSection::$video_or_image_section, $selectedSectionsName)) {
        //     $boxVideoOrImage = getHomeVideoOrImageBoxSettings();
        // }

        // $seoSettings = getSeoMetas('home');
        $pageTitle = !empty($seoSettings['title']) ? $seoSettings['title'] : trans('home.home_title');
        // $pageDescription = !empty($seoSettings['description']) ? $seoSettings['description'] : trans('home.home_title');
        $pageRobot = getPageRobot('home');
       // $course_category=Category::where('parent_id','=',null)->whereNotIn('id',[673,674,])->where('id',Auth::user()->course_category_id)->with(['subCategories'])->first();
       // $CurrentAffair = Blog::where('category_id','38')->orderBy('created_at','DESC')->get();
        $LatestNotification =  DB::table('blog')
         ->join('blog_translations','blog_translations.blog_id','blog.id')
         ->select('blog.id as id','blog.visit_count as visit_count','blog_translations.title as title','blog_translations.content as content','blog.image')
         ->where('category_id','39')->orderBy('created_at','DESC')->get();
         $UpcomingNotification = DB::table('blog')
         ->join('blog_translations','blog_translations.blog_id','blog.id')
         ->where('category_id','40')->select('blog.id as id','blog.visit_count as visit_count','blog_translations.title as title','blog_translations.content as content','blog.image')->orderBy('created_at','DESC')
         ->get();
        $data = [
            'pageTitle' => $pageTitle,
           // 'pageDescription' => $pageDescription,
            'pageRobot' => $pageRobot,
           // 'heroSection' => $heroSection,
          //  'heroSectionData' => $heroSectionData,
            //'homeSections' => $homeSections,
            'featureWebinars' => $featureWebinars,
            'latestWebinars' => $latestWebinars ?? [],
           // 'latestBundles' => $latestBundles ?? [],
           // 'bestSaleWebinars' => $bestSaleWebinars ?? [],
            'hasDiscountWebinars' => $hasDiscountWebinars ?? [],
           // 'bestRateWebinars' => $bestRateWebinars ?? [],
            'freeWebinars' => $freeWebinars ?? [],
           // 'newProducts' => $newProducts ?? [],
            // 'trendCategories' => $trendCategories ?? [],
            // 'instructors' => $instructors ?? [],
            // 'testimonials' => $testimonials ?? [],
            // 'subscribes' => $subscribes ?? [],
            // 'blog' => $blog ?? [],
           // 'organizations' => $organizations ?? [],
            // 'advertisingBanners1' => $advertisingBanners->where('position', 'home1'),
            'advertisingBanners2' => $advertisingBanners,
            // 'skillfulTeachersCount' => $skillfulTeachersCount,
            // 'studentsCount' => $studentsCount,
            // 'liveClassCount' => $liveClassCount,
            // 'offlineCourseCount' => $offlineCourseCount,
            //'boxVideoOrImage' => $boxVideoOrImage ?? null,
           // 'findInstructorSection' => $findInstructorSection ?? null,
            //'rewardProgramSection' => $rewardProgramSection ?? null,
            //'becomeInstructorSection' => $becomeInstructorSection ?? null,
            //'forumSection' => $forumSection ?? null,
            // 'category'=>$course_category ?? null,
            // 'CurrentAffair'=>$CurrentAffair ?? [],
            'LatestNotification'=>$LatestNotification ?? [],
            'UpcomingNotification' => $UpcomingNotification ?? [],
        ];
            // dd($data);
        if($data){
            return response()->json(['statusCode' => 200, 'message' => 'Home page data','data'=>$data]);
            //return response(['statusCode' => 200, 'message' => 'Home page data','data'=>$data]);
        }else{
              return response(['statusCode' => 400, 'message' => 'No data found']);
        }
    }
}
