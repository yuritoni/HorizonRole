<?php

namespace App\Http\Controllers;

use App\Models\MasterDataGroup;
use App\Models\MasterDataItem;
use App\Services\ErpNotificationService;
use App\Support\ErpModules;
use App\Support\ErpViewData;
use App\Support\MasterDataRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    public function index(Request $request)
    {
        MasterDataRegistry::syncDefaults();

        $groupsQuery = MasterDataGroup::withCount('items')
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->type))
            ->when($request->filled('q'), function ($query) use ($request) {
                $query->where(function ($inner) use ($request) {
                    $inner->where('key', 'like', '%'.$request->q.'%')
                        ->orWhere('name', 'like', '%'.$request->q.'%')
                        ->orWhere('description', 'like', '%'.$request->q.'%');
                });
            })
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name');

        $groups = $groupsQuery->get();
        $activeGroup = $request->filled('group')
            ? MasterDataGroup::with(['items.parent'])->where('key', $request->group)->first()
            : $groups->first();

        if (! $activeGroup && MasterDataGroup::exists()) {
            $activeGroup = MasterDataGroup::with(['items.parent'])->orderBy('sort_order')->first();
        }

        return view('settings.master-data', array_merge(ErpViewData::layout(), [
            'groups' => $groups,
            'activeGroup' => $activeGroup?->load(['items.parent']),
            'types' => MasterDataRegistry::groupTypes(),
            'modules' => ErpModules::all(),
            'systemLinks' => [
                ['label' => 'User', 'description' => 'Akun, aktivasi, role user.', 'route' => auth()->user()->hasPrivilege('users.manage') ? route('settings.users') : null],
                ['label' => 'Role & Privilege', 'description' => 'Permission role dan override user.', 'route' => auth()->user()->hasPrivilege('privileges.manage') ? route('settings.privileges') : null],
                ['label' => 'Report Master', 'description' => 'Report master data dan tabel sistem.', 'route' => auth()->user()->hasPrivilege('reports.view') ? route('reports.index', ['dataset' => 'system:master_data_items']) : null],
            ],
        ]));
    }

    public function storeGroup(Request $request)
    {
        $data = $this->groupData($request);
        $data['key'] = $data['key'] ?: $this->makeGroupKey($data['type'], $data['name']);
        $data['is_system'] = false;
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $group = MasterDataGroup::create($data);
        $this->notify('master-data.group.created', 'Master group dibuat', $group->name.' ditambahkan ke master data.', $request);

        return redirect()->route('settings.master-data', ['group' => $group->key])->with('success', 'Master group berhasil dibuat.');
    }

    public function updateGroup(Request $request, MasterDataGroup $group)
    {
        $data = $this->groupData($request, $group);
        $data['key'] = $data['key'] ?: $group->key;
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? $group->sort_order;

        $group->update($data);
        $this->notify('master-data.group.updated', 'Master group diperbarui', $group->name.' diperbarui.', $request);

        return redirect()->route('settings.master-data', ['group' => $group->key])->with('success', 'Master group berhasil diperbarui.');
    }

    public function storeItem(Request $request, MasterDataGroup $group)
    {
        $data = $this->itemData($request, $group);
        $data['master_data_group_id'] = $group->id;
        $data['code'] = $data['code'] ?: str($data['name'])->slug()->toString();
        $data['value'] = $data['value'] ?: $data['name'];
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        MasterDataItem::create($data);
        $this->notify('master-data.item.created', 'Master item dibuat', $data['name'].' ditambahkan ke '.$group->name.'.', $request);

        return redirect()->route('settings.master-data', ['group' => $group->key])->with('success', 'Master item berhasil ditambahkan.');
    }

    public function updateItem(Request $request, MasterDataItem $item)
    {
        $group = $item->group;
        $data = $this->itemData($request, $group, $item);
        $data['code'] = $data['code'] ?: $item->code;
        $data['value'] = $data['value'] ?: $data['name'];
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = $data['sort_order'] ?? $item->sort_order;

        $item->update($data);
        $this->notify('master-data.item.updated', 'Master item diperbarui', $item->name.' diperbarui di '.$group->name.'.', $request);

        return redirect()->route('settings.master-data', ['group' => $group->key])->with('success', 'Master item berhasil diperbarui.');
    }

    public function destroyItem(Request $request, MasterDataItem $item)
    {
        $group = $item->group;
        $name = $item->name;
        $item->delete();
        $this->notify('master-data.item.deleted', 'Master item dihapus', $name.' dihapus dari '.$group->name.'.', $request);

        return redirect()->route('settings.master-data', ['group' => $group->key])->with('success', 'Master item berhasil dihapus.');
    }

    private function groupData(Request $request, ?MasterDataGroup $group = null): array
    {
        return $request->validate([
            'key' => ['nullable', 'string', 'max:120', Rule::unique('master_data_groups', 'key')->ignore($group)],
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(MasterDataRegistry::groupTypes()))],
            'module' => ['nullable', 'string', Rule::in(array_keys(ErpModules::all()))],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ]);
    }

    private function itemData(Request $request, MasterDataGroup $group, ?MasterDataItem $item = null): array
    {
        return $request->validate([
            'parent_id' => ['nullable', Rule::exists('master_data_items', 'id')->where('master_data_group_id', $group->id)],
            'code' => ['nullable', 'string', 'max:120', Rule::unique('master_data_items', 'code')->where('master_data_group_id', $group->id)->ignore($item)],
            'name' => ['required', 'string', 'max:180'],
            'value' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable'],
        ]);
    }

    private function makeGroupKey(string $type, string $name): string
    {
        return $type.'.'.str($name)->slug('.')->toString();
    }

    private function notify(string $type, string $title, string $body, Request $request): void
    {
        app(ErpNotificationService::class)->notifyPrivilege('master-data.manage', [
            'type' => $type,
            'severity' => 'info',
            'title' => $title,
            'body' => $body,
            'action_url' => route('settings.master-data'),
        ], $request->user());
    }
}
