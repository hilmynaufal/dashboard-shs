<?php

namespace App\Http\Controllers;

use App\Authorization\ResourceAccess;
use App\Support\{AccountValidation,ActivityTrail};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(array_intersect($request->user()->permissionCodes(),['audit.technical','audit.assigned']),403);
        $actions=ActivityTrail::actions($request->user());
        $filters=$request->validate(['action'=>['nullable',Rule::in(array_keys($actions))],'page'=>['nullable','integer','min:1','max:100000']],AccountValidation::MESSAGES);
        $events=ActivityTrail::query($request->user(),app(ResourceAccess::class))->when($filters['action']??null,fn($q,$action)=>$q->where('log.action',$action))->paginate(25)->withQueryString();
        return view('activity.index',compact('actions','filters','events'));
    }
}
