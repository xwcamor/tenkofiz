<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Sites the current user may filter/select by. A site-bound user (manager
     * scoped to one branch) only ever sees THEIR site.
     */
    protected function visibleSites(Request $request)
    {
        $user = $request->user();

        return Site::where('is_active', true)
            ->when($user?->isSiteBound(), fn ($q) => $q->whereKey($user->site_id))
            ->orderBy('name')
            ->get();
    }
}
