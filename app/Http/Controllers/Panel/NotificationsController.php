<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationStatus;
use Illuminate\Http\Request;
use App\User;

class NotificationsController extends Controller
{
   public function index(Request $request)
    { // request use phone api
        $user = auth()->user();
      //dd($user,$request->user_id);
      if($request->user_id){
        $user = User::where('id',$request->user_id)->first();
      }

        $notifications = Notification::where(function ($query) use ($user) {
            $query->where('notifications.user_id', $user->id)
                ->where('notifications.type', 'single');
        })->orWhere(function ($query) use ($user) {
            if (!$user->isAdmin()) {
                $query->whereNull('notifications.user_id')
                    ->whereNull('notifications.group_id')
                    ->where('notifications.type', 'all_users');
            }
        });
       // dd($notifications);
        $userGroup = $user->userGroup()->first();
       // dd($user->id,$request->user_id,$user->isAdmin(),$userGroup);
        if (!empty($userGroup)) {
            $notifications->orWhere(function ($query) use ($userGroup) {
                $query->where('notifications.group_id', $userGroup->group_id)
                    ->where('notifications.type', 'group');
            });
        }

        $notifications->orWhere(function ($query) use ($user) {
            $query->whereNull('notifications.user_id')
                ->whereNull('notifications.group_id')
                ->where(function ($query) use ($user) {
                    if ($user->isUser()) {
                        $query->where('notifications.type', 'students');
                    } elseif ($user->isTeacher()) {
                        $query->where('notifications.type', 'instructors');
                    } elseif ($user->isOrganization()) {
                        $query->where('notifications.type', 'organizations');
                    }
                });
        });

        /* Get Course Students Notifications */
        $userBoughtWebinarsIds = $user->getAllPurchasedWebinarsIds();

        if (!empty($userBoughtWebinarsIds)) {
            $notifications->orWhere(function ($query) use ($userBoughtWebinarsIds) {
                $query->whereIn('webinar_id', $userBoughtWebinarsIds)
                    ->where('type', 'course_students');
            });
        }

        $notifications = $notifications->leftJoin('notifications_status', 'notifications.id', '=', 'notifications_status.notification_id')
            ->selectRaw('notifications.*, count(notifications_status.notification_id) AS `count`')
            ->with(['notificationStatus'])
            ->groupBy('notifications.id')
            ->orderBy('count', 'asc')
            ->orderBy('notifications.created_at', 'DESC')
            ->paginate(10);

        $data = [
            'pageTitle' => trans('panel.notifications'),
            'notifications' => $notifications
        ];
       // dd($data);
        if($request->notification_type == 'phone'){
            return response(['statusCode' => 200, 'message' => 'Notifications list', 'data' => $data]);
        }
        return view(getTemplate() . '.panel.notifications.index', $data);
    }

    public function saveStatus($id)
    {
        $user = auth()->user();

        $unReadNotifications = $user->getUnReadNotifications();

        if (!empty($unReadNotifications) and !$unReadNotifications->isEmpty()) {
            $notification = $unReadNotifications->where('id', $id)->first();

            if (!empty($notification)) {
                $status = NotificationStatus::where('user_id', $user->id)
                    ->where('notification_id', $notification->id)
                    ->first();

                if (empty($status)) {
                    NotificationStatus::create([
                        'user_id' => $user->id,
                        'notification_id' => $notification->id,
                        'seen_at' => time()
                    ]);
                }
            }
        }

        return response()->json([], 200);
    }
     // this api create phone 
    public function saveStatusNoti(Request $request)
    { //dd('hello');

        if($request->user_id){
            $user = User::where('id',$request->user_id)->first();
          }
        $unReadNotifications = $user->getUnReadNotifications();
       // dd($unReadNotifications);
        if (!empty($unReadNotifications) and !$unReadNotifications->isEmpty()) {
            $notification = $unReadNotifications->where('id', $request->notification_id)->first();

            if (!empty($notification)) {
                $status = NotificationStatus::where('user_id', $user->id)
                    ->where('notification_id', $notification->id)
                    ->first();

                if (empty($status)) {
                    NotificationStatus::create([
                        'user_id' => $user->id,
                        'notification_id' => $notification->id,
                        'seen_at' => time()
                    ]);
                }
            }
        }

        return response()->json([
            'message' => 'save'
        ], 200);
    }
}
