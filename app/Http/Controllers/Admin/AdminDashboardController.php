<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MacSearch;
use App\Models\MikrotikRouter;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $kpis = [
            'routers_total' => MikrotikRouter::count(),
            'routers_active' => MikrotikRouter::where('is_active', true)->count(),
            'routers_down' => MikrotikRouter::where('is_active', true)->where('connectivity_status', 'fail')->count(),
            'users_total' => User::count(),
            'users_active' => User::where('is_active', true)->count(),
            'searches_today' => MacSearch::whereDate('created_at', now())->count(),
            'searches_month' => MacSearch::where('created_at', '>=', now()->startOfMonth())->count(),
            'searches_success' => MacSearch::where('result_count', '>', 0)->count(),
            'searches_empty' => MacSearch::where('result_count', 0)->where('status', '!=', 'error')->count(),
            'searches_error' => MacSearch::where('status', 'error')->count(),
        ];

        return view('admin.dashboard', [
            'kpis' => $kpis,
            'lastLogin' => auth()->user()->last_login_at,
            'dailySeries' => $this->dailySeries(7),
        ]);
    }

    /**
     * Serie diaria de consultas para el gráfico del panel.
     *
     * @return array{labels: list<string>, values: list<int>}
     */
    private function dailySeries(int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();

        $rows = MacSearch::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', $from)
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d/m');
            $values[] = (int) ($rows[$date->toDateString()] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public function stats()
    {
        $daily = MacSearch::query()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total, SUM(result_count > 0) as hits')
            ->where('created_at', '>=', now()->subDays(14)->startOfDay())
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $mechanisms = \DB::table('mac_search_results')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('mechanism, COUNT(*) as total')
            ->groupBy('mechanism')
            ->pluck('total', 'mechanism');

        $topUsers = MacSearch::query()
            ->selectRaw('user_id, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('user_id')
            ->orderByDesc('total')
            ->limit(8)
            ->with('user:id,name,lastname')
            ->get()
            ->map(fn ($r) => [
                'label' => $r->user?->fullName() ?? 'Usuario eliminado',
                'total' => (int) $r->total,
            ]);

        return response()->json([
            'daily' => $daily,
            'mechanisms' => $mechanisms,
            'top_users' => $topUsers,
        ]);
    }

    public function health()
    {
        $routers = MikrotikRouter::where('is_active', true)
            ->orderBy('priority')
            ->get(['id', 'name', 'host', 'api_port', 'use_ssl', 'municipality', 'connectivity_status', 'last_checked_at', 'last_success_at']);

        return view('admin.health.index', compact('routers'));
    }
}