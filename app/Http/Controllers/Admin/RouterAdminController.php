<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MikrotikRouter;
use App\Services\Mikrotik\MikrotikRestClient;
use App\Services\Mikrotik\MikrotikException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class RouterAdminController extends Controller
{
    public function __construct(private readonly MikrotikRestClient $client) {}

    public function index()
    {
        $routers = MikrotikRouter::orderBy('priority')->paginate(20);

        return view('admin.routers.index', compact('routers'));
    }

    public function create()
    {
        return view('admin.routers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'host' => ['required', 'string', 'max:255'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'api_user' => ['required', 'string', 'max:100'],
            'api_password' => ['required', 'string', 'max:255'],
            'use_ssl' => ['sometimes', 'boolean'],
            'verify_ssl' => ['sometimes', 'boolean'],
            'location' => ['nullable', 'string', 'max:150'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['required', 'integer', 'min:1', 'max:10'],
            'connect_timeout_ms' => ['required', 'integer', 'min:500', 'max:30000'],
            'read_timeout_ms' => ['required', 'integer', 'min:1000', 'max:60000'],
        ]);

        $data['api_password'] = Crypt::encryptString($data['api_password']);
        $data['use_ssl'] = $request->boolean('use_ssl');
        $data['verify_ssl'] = $request->boolean('verify_ssl');
        $data['is_active'] = $request->boolean('is_active');

        $router = MikrotikRouter::create($data);

        AuditLog::record('router_created', $request->user(), "Router {$router->name} creado", $request->ip());

        return redirect()->route('admin.routers.index')
            ->with('success', 'Router creado correctamente.');
    }

    public function edit(MikrotikRouter $router)
    {
        return view('admin.routers.edit', compact('router'));
    }

    public function update(Request $request, MikrotikRouter $router)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'host' => ['required', 'string', 'max:255'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'api_user' => ['required', 'string', 'max:100'],
            'api_password' => ['nullable', 'string', 'max:255'],
            'use_ssl' => ['sometimes', 'boolean'],
            'verify_ssl' => ['sometimes', 'boolean'],
            'location' => ['nullable', 'string', 'max:150'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'priority' => ['required', 'integer', 'min:1', 'max:10'],
            'connect_timeout_ms' => ['required', 'integer', 'min:500', 'max:30000'],
            'read_timeout_ms' => ['required', 'integer', 'min:1000', 'max:60000'],
        ]);

        if ($data['api_password'] !== null && $data['api_password'] !== '') {
            $data['api_password'] = Crypt::encryptString($data['api_password']);
        } else {
            unset($data['api_password']);
        }

        $data['use_ssl'] = $request->boolean('use_ssl');
        $data['verify_ssl'] = $request->boolean('verify_ssl');
        $data['is_active'] = $request->boolean('is_active');

        $router->update($data);

        AuditLog::record('router_updated', $request->user(), "Router {$router->name} actualizado", $request->ip());

        return back()->with('success', 'Router actualizado.');
    }

    public function test(Request $request, MikrotikRouter $router)
    {
        try {
            $info = $this->client->testConnection($router);

            $router->forceFill([
                'last_checked_at' => now(),
                'last_success_at' => now(),
                'connectivity_status' => 'ok',
            ])->save();

            AuditLog::record('router_test_success', $request->user(), "Prueba de conexión exitosa: {$router->name} ({$info['board_name']})", $request->ip(), 'success', null, $info);

            return response()->json([
                'success' => true,
                'message' => 'Conexión exitosa.',
                'info' => $info,
            ]);
        } catch (MikrotikException $e) {
            $router->forceFill([
                'last_checked_at' => now(),
                'connectivity_status' => 'fail',
            ])->save();

            AuditLog::record('router_test_fail', $request->user(), "Prueba fallida: {$router->name} — {$e->getMessage()}", $request->ip(), 'fail', null, [
                'type' => $e->errorType,
                'detail' => $e->detail,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'type' => $e->errorType,
                'hint' => $e->hint(),
                'detail' => $e->detail,
                'endpoint' => $this->client->endpoint($router),
            ]);
        }
    }

    public function destroy(Request $request, MikrotikRouter $router)
    {
        $name = $router->name;
        $router->delete();

        AuditLog::record('router_deleted', $request->user(), "Router {$name} eliminado", $request->ip());

        return back()->with('success', 'Router eliminado.');
    }
}