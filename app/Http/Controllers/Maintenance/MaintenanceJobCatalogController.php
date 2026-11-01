<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MaintenanceJobCatalogController extends Controller
{
    private function authorizeHead(Request $request): void
    {
        $user = $request->user();
        $department = mb_strtolower(trim((string) $user?->department));
        $role = mb_strtolower(trim((string) $user?->role));
        $head = ($department === 'maintenance' && in_array($role, ['head', 'maintenance head', 'admin', 'maintenance admin'], true))
            || ($department === 'admin' && in_array($role, ['head', 'admin', 'system admin'], true));
        abort_unless($head && $user?->hasSystemPermission('maintenance', 'approve'), 403);
    }

    public function store(Request $request)
    {
        $this->authorizeHead($request);
        $data = $request->validate([
            'name' => ['required','string','max:255',Rule::unique('maintenance_job_catalog','name')],
            'specializations' => ['required','array','min:1','max:20'],
            'specializations.*' => ['required','string','max:100','distinct'],
        ]);
        $known = DB::table('mechanic_specialization_options')->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim($name)))->all();
        foreach ($data['specializations'] as $skill) {
            if (!in_array(mb_strtolower(trim($skill)), $known, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['specializations'=>'Unknown specialization: '.$skill]);
            }
        }
        DB::table('maintenance_job_catalog')->insert([
            'name'=>trim($data['name']),
            'specializations'=>json_encode(array_values($data['specializations'])),
            'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        return back()->with('success','Maintenance job added.');
    }

    public function update(Request $request, int $job)
    {
        $this->authorizeHead($request);
        $data = $request->validate([
            'name' => ['required','string','max:255',Rule::unique('maintenance_job_catalog','name')->ignore($job)],
            'specializations' => ['required','array','min:1','max:20'],
            'specializations.*' => ['required','string','max:100','distinct'],
            'is_active'=>['nullable','boolean'],
        ]);
        $current = DB::table('maintenance_job_catalog')->where('id',$job)->first();
        abort_unless($current,404);
        // Historical JOs store their job names, so renaming is disallowed when referenced.
        if ($data['name'] !== $current->name &&
            DB::table('job_orders')->where('work_to_perform','like','%'.$current->name.'%')->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'This job is referenced by historical Job Orders; keep its name.']);
        }
        $known = DB::table('mechanic_specialization_options')->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim($name)))->all();
        foreach ($data['specializations'] as $skill) {
            if (!in_array(mb_strtolower(trim($skill)), $known, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['specializations'=>'Unknown specialization: '.$skill]);
            }
        }
        DB::table('maintenance_job_catalog')->where('id',$job)->update([
            'name'=>trim($data['name']),
            'specializations'=>json_encode(array_values($data['specializations'])),
            'is_active'=>$request->boolean('is_active'),
            'updated_at'=>now(),
        ]);
        return back()->with('success','Maintenance job updated.');
    }
}
