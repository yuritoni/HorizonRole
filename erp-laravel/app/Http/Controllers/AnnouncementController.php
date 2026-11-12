<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAnnouncement;
use App\Models\EmployeeAnnouncementRead;
use App\Models\User;
use App\Services\ErpNotificationService;
use App\Support\ErpModules;
use App\Support\ErpViewData;
use App\Support\MasterDataRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $baseQuery = EmployeeAnnouncement::query()
            ->published()
            ->visibleToUser($request->user())
            ->with(['creator', 'reads' => fn ($query) => $query->where('user_id', $request->user()->id)]);

        if ($request->filled('kind')) {
            $baseQuery->where('kind', $request->kind);
        }

        if ($request->filled('category')) {
            $baseQuery->where('category', $request->category);
        }

        if ($request->get('status') === 'unread') {
            $baseQuery->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $request->user()->id));
        }

        $metricsQuery = EmployeeAnnouncement::query()
            ->published()
            ->visibleToUser($request->user());

        return view('announcements.index', array_merge(ErpViewData::layout(), [
            'announcements' => $baseQuery
                ->orderByDesc('is_pinned')
                ->latest('publish_at')
                ->paginate(12)
                ->withQueryString(),
            'totalPublished' => (clone $metricsQuery)->count(),
            'unreadCount' => (clone $metricsQuery)
                ->whereDoesntHave('reads', fn ($query) => $query->where('user_id', $request->user()->id))
                ->count(),
            'newsCount' => (clone $metricsQuery)->where('kind', 'news')->count(),
            'pinnedCount' => (clone $metricsQuery)->where('is_pinned', true)->count(),
            'categories' => MasterDataRegistry::optionsForGroup('announcement.categories', ['HR', 'Kebijakan', 'Payroll', 'Benefit', 'Operasional', 'Event']),
            'filters' => $request->all(),
        ]));
    }

    public function show(Request $request, EmployeeAnnouncement $announcement)
    {
        $this->authorizeVisible($request, $announcement);

        $read = EmployeeAnnouncementRead::firstOrCreate(
            ['employee_announcement_id' => $announcement->id, 'user_id' => $request->user()->id],
            ['read_at' => now()]
        );

        if (! $read->read_at) {
            $read->update(['read_at' => now()]);
        }

        return view('announcements.show', array_merge(ErpViewData::layout(), [
            'announcement' => $announcement->load(['creator', 'reads.user']),
            'read' => $read->fresh(),
        ]));
    }

    public function acknowledge(Request $request, EmployeeAnnouncement $announcement)
    {
        $this->authorizeVisible($request, $announcement);

        EmployeeAnnouncementRead::updateOrCreate(
            ['employee_announcement_id' => $announcement->id, 'user_id' => $request->user()->id],
            ['read_at' => now(), 'acknowledged_at' => now()]
        );

        return back()->with('success', 'Konfirmasi baca tersimpan.');
    }

    public function manage(Request $request)
    {
        $query = EmployeeAnnouncement::query()
            ->with(['creator'])
            ->withCount([
                'reads as read_count' => fn ($readQuery) => $readQuery->whereNotNull('read_at'),
                'reads as acknowledged_count' => fn ($readQuery) => $readQuery->whereNotNull('acknowledged_at'),
            ]);

        if ($request->filled('kind')) {
            $query->where('kind', $request->kind);
        }

        if ($request->filled('status')) {
            $query->where('is_published', $request->status === 'published');
        }

        return view('settings.announcements', array_merge(ErpViewData::layout(), [
            'announcements' => $query->latest()->paginate(14)->withQueryString(),
            'publishedCount' => EmployeeAnnouncement::where('is_published', true)->count(),
            'draftCount' => EmployeeAnnouncement::where('is_published', false)->count(),
            'newsCount' => EmployeeAnnouncement::where('kind', 'news')->count(),
            'ackRequiredCount' => EmployeeAnnouncement::where('requires_acknowledgement', true)->count(),
            'categories' => MasterDataRegistry::optionsForGroup('announcement.categories', ['HR', 'Kebijakan', 'Payroll', 'Benefit', 'Operasional', 'Event']),
            'priorities' => MasterDataRegistry::optionsForGroup('announcement.priorities', ['normal', 'important', 'urgent']),
            'departments' => MasterDataRegistry::optionsForGroup('master.departments', ['Finance', 'Sales', 'Operations', 'HR', 'IT']),
            'modules' => ['all' => 'Semua Sistem'] + collect(ErpModules::all())->mapWithKeys(fn ($config, $module) => [$module => $config['label']])->all(),
            'filters' => $request->all(),
        ]));
    }

    public function store(Request $request, ErpNotificationService $notifications)
    {
        $announcement = EmployeeAnnouncement::create($this->validatedPayload($request) + [
            'created_by' => $request->user()->id,
            'metadata' => ['origin' => 'announcement_center'],
        ]);

        if ($this->shouldNotify($announcement)) {
            $this->notifyRecipients($announcement, $notifications, $request->user());
        }

        return back()->with('success', $announcement->kind === 'news' ? 'Berita HR berhasil dipublikasikan.' : 'Pengumuman berhasil dipublikasikan.');
    }

    public function update(Request $request, EmployeeAnnouncement $announcement, ErpNotificationService $notifications)
    {
        $announcement->update($this->validatedPayload($request));

        if ($request->boolean('notify_now') && $this->shouldNotify($announcement)) {
            $this->notifyRecipients($announcement, $notifications, $request->user());
        }

        return back()->with('success', 'Pengumuman atau berita berhasil diperbarui.');
    }

    public function updateStatus(Request $request, EmployeeAnnouncement $announcement, ErpNotificationService $notifications)
    {
        $data = $request->validate([
            'is_published' => ['required', 'boolean'],
            'notify_now' => ['nullable', 'boolean'],
        ]);

        $announcement->update([
            'is_published' => (bool) $data['is_published'],
            'publish_at' => (bool) $data['is_published'] && ! $announcement->publish_at ? now() : $announcement->publish_at,
        ]);

        if ($request->boolean('notify_now') && $this->shouldNotify($announcement)) {
            $this->notifyRecipients($announcement, $notifications, $request->user());
        }

        return back()->with('success', $announcement->is_published ? 'Publikasi sudah live.' : 'Publikasi menjadi draft.');
    }

    private function validatedPayload(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['announcement', 'news'])],
            'title' => ['required', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:220'],
            'body' => ['required', 'string', 'max:4000'],
            'category' => ['required', 'string', 'max:80'],
            'audience' => ['required', 'string', 'max:120'],
            'source_department' => ['required', 'string', 'max:120'],
            'priority' => ['required', Rule::in(['normal', 'important', 'urgent'])],
            'scope_module' => ['required', 'string', 'max:80'],
            'is_published' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'requires_acknowledgement' => ['nullable', 'boolean'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:publish_at'],
        ]);

        return [
            'kind' => $data['kind'],
            'title' => $data['title'],
            'summary' => $data['summary'] ?: Str::limit($data['body'], 180),
            'body' => $data['body'],
            'category' => $data['category'],
            'audience' => $data['audience'],
            'source_department' => $data['source_department'],
            'priority' => $data['priority'],
            'scope_module' => $data['scope_module'],
            'is_published' => $request->boolean('is_published'),
            'is_pinned' => $request->boolean('is_pinned'),
            'requires_acknowledgement' => $request->boolean('requires_acknowledgement'),
            'publish_at' => $data['publish_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
        ];
    }

    private function authorizeVisible(Request $request, EmployeeAnnouncement $announcement): void
    {
        if ($request->user()->hasPrivilege('announcements.manage')) {
            abort_unless(EmployeeAnnouncement::whereKey($announcement->id)->visibleToUser($request->user())->exists(), 404);

            return;
        }

        abort_unless(
            EmployeeAnnouncement::whereKey($announcement->id)
                ->published()
                ->visibleToUser($request->user())
                ->exists(),
            404
        );
    }

    private function shouldNotify(EmployeeAnnouncement $announcement): bool
    {
        return $announcement->is_published && (! $announcement->publish_at || $announcement->publish_at->lte(now()));
    }

    private function notifyRecipients(EmployeeAnnouncement $announcement, ErpNotificationService $notifications, User $actor): void
    {
        $notifications->notifyUsers($this->recipients($announcement), [
            'type' => $announcement->kind === 'news' ? 'announcement.news.published' : 'announcement.published',
            'severity' => $announcement->priority === 'urgent' ? 'warning' : 'info',
            'module' => 'announcement',
            'title' => ($announcement->kind === 'news' ? 'Berita HR: ' : 'Pengumuman: ').$announcement->title,
            'body' => Str::limit($announcement->summary ?: $announcement->body, 240),
            'action_url' => route('announcements.show', $announcement),
            'data' => [
                'employee_announcement_id' => $announcement->id,
                'kind' => $announcement->kind,
                'priority' => $announcement->priority,
                'source_department' => $announcement->source_department,
                'scope_module' => $announcement->scope_module,
            ],
        ], $actor);
    }

    private function recipients(EmployeeAnnouncement $announcement): Collection
    {
        $users = User::with('employeeProfile')
            ->where('account_status', 'active')
            ->get();

        if ($announcement->audience === 'all_systems') {
            return $users;
        }

        return $users
            ->filter(function (User $user) use ($announcement) {
                if ($announcement->audience === 'all') {
                    return $user->role === 'employee' || $user->employeeProfile;
                }

                if ($announcement->audience === 'employee') {
                    return $user->role === 'employee';
                }

                return $user->role === $announcement->audience
                    || $user->employeeProfile?->department === $announcement->audience;
            })
            ->values();
    }
}
