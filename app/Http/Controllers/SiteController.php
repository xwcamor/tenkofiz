<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    public function index()
    {
        $sites = Site::withCount('employees')->orderBy('name')->get();

        return view('sites.index', compact('sites'));
    }

    public function store(Request $request)
    {
        Site::create($this->validated($request));

        return redirect()->route('sites.index')->with('ok', __('Site registered.'));
    }

    public function update(Request $request, Site $site)
    {
        $site->update($this->validated($request, $site));

        return redirect()->route('sites.index')->with('ok', __('Site updated.'));
    }

    public function destroy(Site $site)
    {
        AuditLog::record('DELETE', 'Sites',
            __('Site :name was deleted', ['name' => $site->name]),
            $site->toArray());
        $site->delete();

        return back()->with('ok', __('Site deleted. Its employees keep their records but lose the site assignment.'));
    }

    private function validated(Request $request, ?Site $site = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('sites')->ignore($site)],
            'address' => ['nullable', 'string', 'max:200'],
            'timezone' => ['nullable', 'timezone:all'],
        ], [
            'name.unique' => __('A site with that name already exists.'),
        ]) + ['is_active' => $site ? $request->boolean('is_active') : true];
    }
}
