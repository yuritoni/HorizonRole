<?php

namespace App\Support;

use App\Models\ErpRecord;
use App\Models\ErpNotification;
use App\Models\EmployeeAnnouncement;
use Illuminate\Support\Facades\Schema;

class ErpViewData
{
    public static function layout(): array
    {
        $modules = ErpModules::all();
        $accessibleModules = collect($modules)
            ->filter(fn ($config, $module) => auth()->check() && auth()->user()->hasPrivilege("modules.{$module}.view"))
            ->reject(fn ($config, $module) => $module === 'hr')
            ->toArray();

        $moduleCounts = ErpRecord::query()
            ->selectRaw('module, count(*) as total')
            ->groupBy('module')
            ->pluck('total', 'module')
            ->toArray();

        $unreadNotificationsCount = 0;
        $recentNotifications = collect();
        $announcementUnreadCount = 0;
        $announcementPublishedCount = 0;

        if (auth()->check()) {
            $unreadNotificationsCount = ErpNotification::where('user_id', auth()->id())
                ->whereNull('read_at')
                ->count();
            $recentNotifications = ErpNotification::where('user_id', auth()->id())
                ->latest()
                ->limit(5)
                ->get();

            if (Schema::hasTable('employee_announcements') && Schema::hasTable('employee_announcement_reads')) {
                $announcementQuery = EmployeeAnnouncement::query()
                    ->published()
                    ->visibleToUser(auth()->user());

                $announcementPublishedCount = (clone $announcementQuery)->count();
                $announcementUnreadCount = (clone $announcementQuery)
                    ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', auth()->id()))
                    ->count();
            }
        }

        return [
            'modules' => $modules,
            'accessibleModules' => $accessibleModules,
            'moduleCounts' => $moduleCounts,
            'unreadNotificationsCount' => $unreadNotificationsCount,
            'recentNotifications' => $recentNotifications,
            'announcementUnreadCount' => $announcementUnreadCount,
            'announcementPublishedCount' => $announcementPublishedCount,
        ];
    }
}
