<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\EmployeeAnnouncement;
use App\Models\EmployeeProfile;
use App\Models\EmployeeRequest;
use App\Models\User;
use App\Support\ErpViewData;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
            'type' => ['nullable', 'string', 'max:40'],
        ]);

        $month = (int) ($data['month'] ?? now()->month);
        $year = (int) ($data['year'] ?? now()->year);
        $activeType = $data['type'] ?? '';
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $calendarStart = $periodStart->copy()->startOfWeek(Carbon::MONDAY);
        $calendarEnd = $periodEnd->copy()->endOfWeek(Carbon::SUNDAY);
        $user = $request->user();

        $sourceEvents = collect()
            ->merge($this->manualEvents($user, $calendarStart, $calendarEnd))
            ->merge($this->announcementEvents($user, $calendarStart, $calendarEnd))
            ->merge($this->employeeRequestEvents($user, $calendarStart, $calendarEnd))
            ->merge($this->birthdayEvents($user, $calendarStart, $calendarEnd));

        $sourceEvents = $sourceEvents
            ->sortBy([['sort_at', 'asc'], ['title', 'asc']])
            ->values();

        $events = $activeType
            ? $sourceEvents->where('type', $activeType)->values()
            : $sourceEvents;

        $eventsByDate = $events
            ->groupBy('date_key')
            ->map(fn (Collection $items) => $items->values());

        $weeks = collect();
        $cursor = $calendarStart->copy();

        while ($cursor->lte($calendarEnd)) {
            $week = collect();

            for ($day = 0; $day < 7; $day++) {
                $date = $cursor->copy();
                $key = $date->toDateString();
                $week->push([
                    'date' => $date,
                    'key' => $key,
                    'events' => $eventsByDate->get($key, collect()),
                    'is_current_month' => $date->month === $month,
                    'is_today' => $date->isSameDay(now()),
                ]);
                $cursor->addDay();
            }

            $weeks->push($week);
        }

        $monthEvents = $sourceEvents
            ->filter(fn (array $event) => Carbon::parse($event['date_key'])->betweenIncluded($periodStart, $periodEnd))
            ->values();
        $typeCounts = $monthEvents->countBy('type');
        $typeOptions = $this->typeOptions($typeCounts);
        $years = range(now()->year + 1, now()->year - 4);
        $prevMonth = $periodStart->copy()->subMonth();
        $nextMonth = $periodStart->copy()->addMonth();
        $departments = Schema::hasTable('employee_profiles')
            ? EmployeeProfile::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department')->filter()->values()
            : collect();

        return view('calendar.index', array_merge(ErpViewData::layout(), [
            'month' => $month,
            'year' => $year,
            'years' => $years,
            'activeType' => $activeType,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
            'prevMonth' => $prevMonth,
            'nextMonth' => $nextMonth,
            'weeks' => $weeks,
            'eventsByDate' => $eventsByDate,
            'agendaEvents' => $events,
            'typeOptions' => $typeOptions,
            'typeCounts' => $typeCounts,
            'departments' => $departments,
            'summary' => [
                'total' => $monthEvents->count(),
                'manual' => $monthEvents->where('source', 'manual')->count(),
                'announcements' => $monthEvents->whereIn('source', ['announcement', 'news'])->count(),
                'requests' => $monthEvents->where('source', 'employee_request')->count(),
                'birthdays' => $monthEvents->where('type', 'birthday')->count(),
            ],
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1200'],
            'type' => ['required', Rule::in(['event', 'meeting', 'deadline', 'holiday', 'finance', 'hr', 'sales', 'production', 'reminder'])],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'all_day' => ['nullable', 'boolean'],
            'location' => ['nullable', 'string', 'max:180'],
            'audience' => ['required', 'string', 'max:120'],
            'color' => ['nullable', Rule::in(['blue', 'green', 'amber', 'red', 'violet', 'slate'])],
        ]);

        CalendarEvent::create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'start_at' => Carbon::parse($data['start_at']),
            'end_at' => ! empty($data['end_at']) ? Carbon::parse($data['end_at']) : null,
            'all_day' => $request->boolean('all_day'),
            'location' => $data['location'] ?? null,
            'audience' => $data['audience'],
            'color' => $data['color'] ?? 'blue',
            'source' => 'manual',
            'created_by' => $request->user()->id,
            'metadata' => ['created_from' => 'calendar'],
        ]);

        return back()->with('success', 'Agenda kalender berhasil ditambahkan.');
    }

    public function destroy(CalendarEvent $calendarEvent)
    {
        abort_unless($calendarEvent->source === 'manual', 403);

        $calendarEvent->delete();

        return back()->with('success', 'Agenda kalender berhasil dihapus.');
    }

    private function manualEvents(User $user, Carbon $calendarStart, Carbon $calendarEnd): Collection
    {
        if (! Schema::hasTable('calendar_events')) {
            return collect();
        }

        return CalendarEvent::query()
            ->with('creator')
            ->where(function ($query) use ($calendarStart, $calendarEnd) {
                $query
                    ->whereBetween('start_at', [$calendarStart, $calendarEnd])
                    ->orWhereBetween('end_at', [$calendarStart, $calendarEnd])
                    ->orWhere(function ($inner) use ($calendarStart, $calendarEnd) {
                        $inner->whereNotNull('end_at')
                            ->where('start_at', '<=', $calendarStart)
                            ->where('end_at', '>=', $calendarEnd);
                    });
            })
            ->when(! $user->hasPrivilege('calendar.manage'), fn ($query) => $this->visibleAudienceQuery($query, $user))
            ->orderBy('start_at')
            ->get()
            ->flatMap(fn (CalendarEvent $event) => $this->expandEventDates(
                $event->start_at,
                $event->end_at ?: $event->start_at,
                $calendarStart,
                $calendarEnd,
                [
                    'id' => 'manual-'.$event->id,
                    'source_id' => $event->id,
                    'source' => 'manual',
                    'type' => $event->type,
                    'label' => $this->typeLabel($event->type),
                    'title' => $event->title,
                    'description' => $event->description,
                    'status' => $this->audienceLabel($event->audience),
                    'source_label' => 'Agenda Manual',
                    'icon' => $this->typeIcon($event->type),
                    'tone' => $this->colorTone($event->color, $event->type),
                    'all_day' => $event->all_day,
                    'time' => $event->all_day ? 'Sepanjang hari' : $this->timeRange($event->start_at, $event->end_at),
                    'location' => $event->location,
                    'url' => null,
                    'can_delete' => true,
                ]
            ));
    }

    private function announcementEvents(User $user, Carbon $calendarStart, Carbon $calendarEnd): Collection
    {
        if (! Schema::hasTable('employee_announcements')) {
            return collect();
        }

        return EmployeeAnnouncement::query()
            ->published()
            ->visibleToUser($user)
            ->where(function ($query) use ($calendarStart, $calendarEnd) {
                $query
                    ->whereBetween('publish_at', [$calendarStart, $calendarEnd])
                    ->orWhere(function ($inner) use ($calendarStart, $calendarEnd) {
                        $inner->whereNull('publish_at')
                            ->whereBetween('created_at', [$calendarStart, $calendarEnd]);
                    });
            })
            ->orderBy('publish_at')
            ->get()
            ->map(function (EmployeeAnnouncement $announcement) {
                $date = ($announcement->publish_at ?: $announcement->created_at)->copy();
                $type = $announcement->kind === 'news' ? 'news' : 'announcement';

                return $this->eventPayload($date, [
                    'id' => $type.'-'.$announcement->id,
                    'source_id' => $announcement->id,
                    'source' => $type,
                    'type' => $type,
                    'label' => $type === 'news' ? 'Berita' : 'Pengumuman',
                    'title' => $announcement->title,
                    'description' => $announcement->summary ?: str($announcement->body)->limit(160)->toString(),
                    'status' => $announcement->category,
                    'source_label' => $announcement->source_department ?: 'HR',
                    'icon' => $type === 'news' ? 'newspaper' : 'megaphone',
                    'tone' => $type === 'news' ? 'success' : 'info',
                    'all_day' => true,
                    'time' => 'Publikasi',
                    'location' => null,
                    'url' => route('announcements.show', $announcement),
                    'can_delete' => false,
                ]);
            });
    }

    private function employeeRequestEvents(User $user, Carbon $calendarStart, Carbon $calendarEnd): Collection
    {
        if (! Schema::hasTable('employee_requests')) {
            return collect();
        }

        return EmployeeRequest::query()
            ->with(['profile.user', 'user'])
            ->where(function ($query) use ($calendarStart, $calendarEnd) {
                $query
                    ->whereBetween('start_date', [$calendarStart->toDateString(), $calendarEnd->toDateString()])
                    ->orWhereBetween('end_date', [$calendarStart->toDateString(), $calendarEnd->toDateString()])
                    ->orWhere(function ($inner) use ($calendarStart, $calendarEnd) {
                        $inner->where('start_date', '<=', $calendarStart->toDateString())
                            ->where('end_date', '>=', $calendarEnd->toDateString());
                    });
            })
            ->when(! $user->hasPrivilege('employee.manage'), function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->orderBy('start_date')
            ->get()
            ->flatMap(function (EmployeeRequest $employeeRequest) use ($calendarStart, $calendarEnd, $user) {
                $start = $employeeRequest->start_date ?: $employeeRequest->created_at;
                $end = $employeeRequest->end_date ?: $start;
                $type = $employeeRequest->type ?: 'request';
                $profile = $employeeRequest->profile;
                $payload = $employeeRequest->payload ?? [];
                $name = $employeeRequest->user?->name ?: $profile?->user?->name ?: 'Pegawai';
                $time = in_array($type, ['overtime', 'manual_attendance'], true)
                    ? ($payload['start_time'] ?? $payload['clock_in_at'] ?? '--:--').' - '.($payload['end_time'] ?? $payload['clock_out_at'] ?? '--:--')
                    : ($employeeRequest->total_days ? number_format((float) $employeeRequest->total_days, 2, ',', '.').' hari' : 'Pengajuan');

                return $this->expandEventDates(Carbon::parse($start), Carbon::parse($end), $calendarStart, $calendarEnd, [
                    'id' => 'request-'.$employeeRequest->id,
                    'source_id' => $employeeRequest->id,
                    'source' => 'employee_request',
                    'type' => $type,
                    'label' => $this->typeLabel($type),
                    'title' => $this->typeLabel($type).' - '.$name,
                    'description' => $employeeRequest->title.($employeeRequest->description ? ' - '.$employeeRequest->description : ''),
                    'status' => strtoupper($employeeRequest->status),
                    'source_label' => $profile?->department ?: 'HRIS',
                    'icon' => $this->typeIcon($type),
                    'tone' => $this->requestTone($employeeRequest->status, $type),
                    'all_day' => ! in_array($type, ['overtime', 'manual_attendance'], true),
                    'time' => $time,
                    'location' => $payload['travel_destination'] ?? $payload['address'] ?? null,
                    'url' => $user->hasPrivilege('employee.manage') && $profile
                        ? route('settings.employees.requests', $profile)
                        : route('employee.portal'),
                    'can_delete' => false,
                ]);
            });
    }

    private function birthdayEvents(User $user, Carbon $calendarStart, Carbon $calendarEnd): Collection
    {
        if (! Schema::hasTable('employee_profiles')) {
            return collect();
        }

        $birthdayMonths = collect(CarbonPeriod::create($calendarStart->copy()->startOfMonth(), '1 month', $calendarEnd->copy()->startOfMonth()))
            ->map(fn (Carbon $date) => $date->month)
            ->unique()
            ->values()
            ->all();

        return EmployeeProfile::query()
            ->with('user')
            ->whereNotNull('birth_date')
            ->whereIn(DB::raw('MONTH(birth_date)'), $birthdayMonths)
            ->whereNotIn('employment_status', ['inactive'])
            ->when(! $user->hasPrivilege('employee.manage'), function ($query) use ($user) {
                $query->where(function ($inner) use ($user) {
                    $inner->where('user_id', $user->id);

                    if ($user->employeeProfile?->department) {
                        $inner->orWhere('department', $user->employeeProfile->department);
                    }
                });
            })
            ->get()
            ->map(function (EmployeeProfile $profile) use ($calendarStart, $calendarEnd, $user) {
                $birthDate = $profile->birth_date;
                $year = $calendarStart->year;
                $day = min($birthDate->day, Carbon::create($year, $birthDate->month, 1)->endOfMonth()->day);
                $eventDate = Carbon::create($year, $birthDate->month, $day);

                if ($eventDate->lt($calendarStart)) {
                    $year = $calendarEnd->year;
                    $day = min($birthDate->day, Carbon::create($year, $birthDate->month, 1)->endOfMonth()->day);
                    $eventDate = Carbon::create($year, $birthDate->month, $day);
                }

                if (! $eventDate->betweenIncluded($calendarStart, $calendarEnd)) {
                    return null;
                }

                $name = $profile->user?->name ?: $profile->employee_no;

                return $this->eventPayload($eventDate, [
                    'id' => 'birthday-'.$profile->id,
                    'source_id' => $profile->id,
                    'source' => 'employee_profile',
                    'type' => 'birthday',
                    'label' => 'Ulang Tahun',
                    'title' => 'Ulang tahun '.$name,
                    'description' => $profile->department ?: $profile->position ?: 'Employee',
                    'status' => $profile->employee_no,
                    'source_label' => 'Employee',
                    'icon' => 'cake',
                    'tone' => 'violet',
                    'all_day' => true,
                    'time' => 'Hari ulang tahun',
                    'location' => $profile->work_location,
                    'url' => $user->hasPrivilege('employee.manage')
                        ? route('settings.employees.edit', $profile)
                        : ($profile->user_id === $user->id ? route('employee.portal') : null),
                    'can_delete' => false,
                ]);
            })
            ->filter()
            ->values();
    }

    private function visibleAudienceQuery($query, User $user)
    {
        $audiences = ['all_systems', $user->role];

        if ($user->role === 'employee' || $user->employeeProfile) {
            $audiences[] = 'all';
            $audiences[] = 'employee';
        }

        if ($user->employeeProfile?->department) {
            $audiences[] = $user->employeeProfile->department;
        }

        return $query->whereIn('audience', array_unique($audiences));
    }

    private function expandEventDates(Carbon $start, Carbon $end, Carbon $calendarStart, Carbon $calendarEnd, array $payload): Collection
    {
        $rangeStart = $start->copy()->max($calendarStart)->startOfDay();
        $rangeEnd = $end->copy()->min($calendarEnd)->startOfDay();

        return collect(CarbonPeriod::create($rangeStart, '1 day', $rangeEnd))
            ->map(fn (Carbon $date) => $this->eventPayload($date, $payload, $start));
    }

    private function eventPayload(Carbon $date, array $payload, ?Carbon $sortAt = null): array
    {
        return array_merge($payload, [
            'date_key' => $date->toDateString(),
            'date' => $date->copy(),
            'sort_at' => ($sortAt ?: $date)->copy(),
        ]);
    }

    private function typeOptions(Collection $typeCounts): array
    {
        $types = [
            'event',
            'meeting',
            'deadline',
            'holiday',
            'announcement',
            'news',
            'leave',
            'permit',
            'sick',
            'overtime',
            'manual_attendance',
            'training',
            'business_trip',
            'birthday',
            'finance',
            'hr',
            'sales',
            'production',
            'reminder',
        ];

        return collect($types)
            ->mapWithKeys(fn ($type) => [$type => [
                'label' => $this->typeLabel($type),
                'count' => (int) ($typeCounts[$type] ?? 0),
                'icon' => $this->typeIcon($type),
            ]])
            ->all();
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'event' => 'Event',
            'meeting' => 'Meeting',
            'deadline' => 'Deadline',
            'holiday' => 'Libur',
            'announcement' => 'Pengumuman',
            'news' => 'Berita',
            'leave' => 'Cuti',
            'permit' => 'Izin',
            'sick' => 'Sakit',
            'overtime' => 'Lembur',
            'manual_attendance' => 'Presensi Manual',
            'training' => 'Pelatihan',
            'business_trip' => 'Perjalanan Dinas',
            'birthday' => 'Ulang Tahun',
            'finance' => 'Finance',
            'hr' => 'HR',
            'sales' => 'Sales',
            'production' => 'Produksi',
            'reminder' => 'Reminder',
            default => str($type)->replace('_', ' ')->headline()->toString(),
        };
    }

    private function typeIcon(string $type): string
    {
        return match ($type) {
            'meeting' => 'video',
            'deadline' => 'alarm-clock',
            'holiday', 'leave' => 'calendar-check',
            'announcement' => 'megaphone',
            'news' => 'newspaper',
            'permit' => 'file-check-2',
            'sick' => 'heart-pulse',
            'overtime' => 'clock',
            'manual_attendance' => 'fingerprint',
            'training' => 'graduation-cap',
            'business_trip' => 'plane',
            'birthday' => 'cake',
            'finance' => 'wallet',
            'hr' => 'users',
            'sales' => 'shopping-cart',
            'production' => 'factory',
            'reminder' => 'bell-ring',
            default => 'calendar-days',
        };
    }

    private function requestTone(string $status, string $type): string
    {
        if ($status === 'approved') {
            return in_array($type, ['leave', 'permit', 'sick'], true) ? 'success' : 'info';
        }

        if ($status === 'rejected') {
            return 'danger';
        }

        return 'warning';
    }

    private function colorTone(?string $color, string $type): string
    {
        return match ($color ?: $type) {
            'green' => 'success',
            'amber' => 'warning',
            'red' => 'danger',
            'violet' => 'violet',
            'slate' => 'muted',
            default => 'info',
        };
    }

    private function timeRange(Carbon $start, ?Carbon $end): string
    {
        if (! $end) {
            return $start->format('H:i');
        }

        return $start->format('H:i').' - '.$end->format('H:i');
    }

    private function audienceLabel(string $audience): string
    {
        return match ($audience) {
            'all_systems' => 'Semua User',
            'all' => 'Semua Pegawai',
            'employee' => 'Employee',
            'admin' => 'Admin',
            'member' => 'Member',
            'user' => 'User Viewer',
            default => $audience,
        };
    }
}
