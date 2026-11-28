<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MacSearch;
use App\Models\MacSearchResult;
use App\Models\MikrotikRouter;
use App\Services\MacAddressResolverService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SearchController extends Controller
{
    public function __construct(private readonly MacAddressResolverService $resolver) {}

    public function index()
    {
        $recent = Auth::user()->searches()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('search.index', compact('recent'));
    }

    public function search(Request $request)
    {
        $request->validate([
            'mac' => ['required', 'string', 'max:32'],
        ], [
            'mac.required' => 'Debes ingresar una dirección MAC.',
        ]);

        $mac = trim($request->input('mac'));

        try {
            $result = $this->resolver->resolve($mac, $request->user());
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['mac' => $e->getMessage()]);
        } catch (\App\Exceptions\SearchUnauthorizedException $e) {
            abort(401, $e->getMessage());
        } catch (\Exception $e) {
            logger()->error('MAC search failed', [
                'mac' => $mac,
                'user' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
            throw ValidationException::withMessages(['mac' => 'Ocurrió un error inesperado. Intenta nuevamente.']);
        }

        $search = $this->resolver->persist($result, $request->user(), $request->ip(), $request->userAgent());

        return response()->json([
            'search_id' => $search->id,
            'mac' => $result['mac_normalized'],
            'status' => $result['status'],
            'total_routers' => $result['total_routers'],
            'reachable' => $result['reachable_routers'],
            'count' => $result['result_count'],
            'duration_ms' => $result['duration_ms'],
            'failures' => $result['failures'] ?? [],
            'partial_routers' => $result['partial_routers'] ?? [],
            'results' => MacAddressResolverService::forResponse($result['results']),
        ]);
    }

    /**
     * Devuelve la URL para abrir la interfaz web del dispositivo.
     *
     * Solo se permite abrir IPs que aparezcan en los resultados de una búsqueda
     * del propio usuario (el superadministrador puede abrir cualquier IP ya
     * registrada en el historial). Así el endpoint no sirve como generador de
     * enlaces hacia hosts arbitrarios.
     */
    public function openDevice(Request $request, string $ip)
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            abort(400, 'IP no válida.');
        }

        $user = $request->user();

        $permitida = MacSearchResult::query()
            ->where('ip_address', $ip)
            ->when(
                ! $user->isSuperAdmin(),
                fn ($query) => $query->whereHas('search', fn ($q) => $q->where('user_id', $user->id)),
            )
            ->exists();

        if (! $permitida) {
            abort(404, 'La IP no corresponde a ninguna búsqueda registrada.');
        }

        $port = (int) \App\Models\SystemSetting::get('device_port', 80);
        $scheme = (string) \App\Models\SystemSetting::get('device_scheme', 'http');

        return response()->json([
            'url' => sprintf('%s://%s:%d', $scheme, $ip, $port),
        ]);
    }

    public function history(Request $request)
    {
        $searches = $request->user()->searches()
            ->with('results')
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('search.history', compact('searches'));
    }

    public function detail(Request $request, int $id)
    {
        $search = $request->user()->searches()->with('results')->findOrFail($id);

        $deviceScheme = (string) \App\Models\SystemSetting::get('device_scheme', 'http');
        $devicePort = (int) \App\Models\SystemSetting::get('device_port', 80);

        return view('search.detail', compact('search', 'deviceScheme', 'devicePort'));
    }
}