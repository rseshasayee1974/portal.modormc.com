<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 14:43:29 Asia/Calcutta (UTC+05:30)
*/
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesModule;
use App\Models\{NotificationWhatsapp, Plant, Role, User};
use App\Services\WhatsappRecipients;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class NotificationWhatsappController extends Controller
{
    use AuthorizesModule;
    protected string $module = 'setting';

    private function plant(): Plant
    {
        abort_unless(session('active_plant_id'), 422, 'Select a plant first.');
        $plant = Plant::findOrFail(session('active_plant_id'));
        abort_unless(auth()->user()->isSystemAdmin() || auth()->user()->entityUsers()
            ->where('entity_id', $plant->entity_id)->where(fn ($q) => $q->where('plant_id', $plant->id)->orWhereNull('plant_id'))->exists(), 403);
        return $plant;
    }

    public function index()
    {
        $this->authorizeModule('menu');
        $plant = $this->plant();
        $memberships = fn ($q) => $q->where('plant_id', $plant->id)->where('entity_id', $plant->entity_id);
        $users = User::where('is_active', true)->whereHas('entityUsers', $memberships)
            ->with(['entityUsers' => $memberships])->get(['id', 'username', 'mobile'])->map(fn ($user) => [
                'id' => $user->id, 'name' => $user->username, 'mobile' => $user->mobile,
                'role_ids' => $user->entityUsers->pluck('role_id')->unique()->values(),
            ]);
        return Inertia::render('NotificationWhatsapps/Index', [
            'settings' => NotificationWhatsapp::where('plant_id', $plant->id)->with(['user:id,username,mobile', 'role:id,name'])->latest()->get(),
            'plant' => $plant->only('id', 'name'), 'users' => $users,
            'roles' => Role::active()->whereIn('id', $users->pluck('role_ids')->flatten()->unique())->get(['id', 'name']),
        ]);
    }

    private function payload(Request $request, Plant $plant): array
    {
        $data = $request->validate(['type' => 'required|in:Dispatch', 'role_id' => 'nullable|integer',
            'user_id' => 'nullable|integer', 'mobile' => 'nullable|string|max:2000', 'status' => 'required|boolean']);
        if (!empty($data['role_id']) && empty($data['user_id'])) {
            throw ValidationException::withMessages(['user_id' => 'Select a user to link to this role.']);
        }
        if (!empty($data['user_id'])) {
            $user = User::where('is_active', true)->whereKey($data['user_id'])->whereHas('entityUsers', fn ($q) => $q
                ->where('plant_id', $plant->id)->where('entity_id', $plant->entity_id)->where('role_id', $data['role_id'] ?? 0))->first();
            if (!$user || !WhatsappRecipients::normalize($user->mobile)) {
                throw ValidationException::withMessages(['user_id' => 'Select a user in this plant and role with a valid mobile number.']);
            }
            $data['mobile'] = null;
        } else {
            $data['user_id'] = null;
            $data['role_id'] = null;
            $numbers = preg_split('/[,;\r\n]+/', $data['mobile'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
            if (!$numbers || count($numbers) > 50 || collect($numbers)->contains(fn ($number) => !WhatsappRecipients::normalize($number))) {
                throw ValidationException::withMessages(['mobile' => 'Enter up to 50 valid mobile numbers, separated by commas. Use a country code for international numbers.']);
            }
            $data['mobile'] = collect($numbers)->map(fn ($number) => WhatsappRecipients::normalize($number))->unique()->values()->all();
        }
        return $data;
    }

    public function store(Request $request)
    {
        $this->authorizeModule('create');
        $plant = $this->plant();
        $data = $this->payload($request, $plant);
        DB::transaction(function () use ($plant, $data) {
            Plant::whereKey($plant->id)->lockForUpdate()->firstOrFail();
            foreach (is_array($data['mobile']) ? $data['mobile'] : [null] as $mobile) {
                NotificationWhatsapp::firstOrCreate(['plant_id' => $plant->id, 'type' => $data['type'],
                    'user_id' => $data['user_id'] ?? null, 'role_id' => $data['role_id'] ?? null, 'mobile' => $mobile],
                    ['status' => $data['status'], 'created_by' => auth()->id(), 'updated_by' => auth()->id()]);
            }
        });
        return back()->with('success', 'WhatsApp recipients saved.');
    }

    public function update(Request $request, NotificationWhatsapp $notificationwhatsapp)
    {
        $this->authorizeModule('edit');
        $plant = $this->plant();
        abort_unless((int) $notificationwhatsapp->plant_id === (int) $plant->id, 403);
        $data = $this->payload($request, $plant);
        if (is_array($data['mobile'])) {
            if (count($data['mobile']) !== 1) throw ValidationException::withMessages(['mobile' => 'Edit one mobile number at a time.']);
            $data['mobile'] = $data['mobile'][0];
        }
        $notificationwhatsapp->update($data + ['updated_by' => auth()->id()]);
        return back()->with('success', 'WhatsApp recipient updated.');
    }

    public function destroy(NotificationWhatsapp $notificationwhatsapp)
    {
        $this->authorizeModule('delete');
        abort_unless((int) $notificationwhatsapp->plant_id === (int) $this->plant()->id, 403);
        $notificationwhatsapp->update(['deleted_by' => auth()->id()]);
        $notificationwhatsapp->delete();
        return back()->with('success', 'WhatsApp recipient removed.');
    }
}
