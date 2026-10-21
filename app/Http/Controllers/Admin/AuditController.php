<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('audit.view');
        $q = AuditLog::with('user')->latest('created_at')->latest('id');
        if ($u = $request->query('user')) {
            $q->where('user_id', (int) $u);
        }
        if ($a = $request->query('action')) {
            $q->where('action', 'like', $a.'%');
        }
        if ($s = trim((string) $request->query('search'))) {
            $q->where('description', 'like', "%{$s}%");
        }

        return view('admin.audit', [
            'logs' => $q->paginate(50)->withQueryString(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'actions' => ['page', 'media', 'menu', 'settings', 'user', 'redirect', 'submission', 'auth', 'roles'],
        ]);
    }
}
