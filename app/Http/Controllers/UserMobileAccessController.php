<?php
/*
Author: ragul-onemodo
Created: 2026-10-12 13:09:32 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Mobile\MobileAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserMobileAccessController extends Controller
{
    public function update(Request $request)
    {
        abort_unless(MobileAccess::canManageUserAccess($request->user()), 403, 'Only administrators can manage mobile app access.');
        $data=$request->validate(['email'=>'required|email|max:255','enabled'=>'required|boolean']);
        return DB::transaction(function () use ($request,$data) {
            $query=User::whereRaw('LOWER(email) = ?', [strtolower(trim($data['email']))]);
            if (!$request->user()->isSystemAdmin()) {
                $query->whereHas('entityUsers',fn($q)=>$q->where('entity_id',session('active_entity_id')));
            }
            $user=$query->lockForUpdate()->firstOrFail();
            abort_if(!$request->user()->isSystemAdmin() && $user->isSystemAdmin(),403,'System administrator access can only be managed by a system administrator.');
            if ((bool)$user->is_otp_enabled !== (bool)$data['enabled']) {
                $user->forceFill(['is_otp_enabled'=>(bool)$data['enabled']])->save();
            }
            if (!$data['enabled']) $user->tokens()->where('name','like','sales-mobile:%')->delete();
            return response()->json(['success'=>true,'message'=>$data['enabled']?'Mobile app access granted.':'Mobile app access removed. Existing mobile sessions are revoked.','data'=>['id'=>$user->id,'email'=>$user->email,'is_otp_enabled'=>(bool)$user->is_otp_enabled]]);
        });
    }
}
