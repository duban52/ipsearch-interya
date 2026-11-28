<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SearchUnauthorizedException;
use App\Http\Controllers\Controller;
use App\Models\MacSearch;
use App\Services\MacAddressResolverService;
use Illuminate\Http\Request;

class MacResolverController extends Controller
{
    public function __construct(private readonly MacAddressResolverService $resolver) {}

    public function search(Request $request)
    {
        $validated = $request->validate([
            'mac' => ['required', 'string', 'max:32'],
        ], [
            'mac.required' => 'MAC requerida.',
        ]);

        try {
            $result = $this->resolver->resolve($validated['mac'], $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'validation_error', 'message' => $e->getMessage()], 422);
        } catch (\App\Exceptions\SearchUnauthorizedException $e) {
            return response()->json(['error' => 'unauthorized', 'message' => $e->getMessage()], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'internal_error', 'message' => 'Error interno'], 500);
        }

        $search = $this->resolver->persist($result, $request->user(), $request->ip(), $request->userAgent());

        return response()->json([
            'search_id' => $search->id,
            'mac' => $result['mac_normalized'],
            'status' => $result['status'],
            'count' => $result['result_count'],
            'results' => MacAddressResolverService::forResponse($result['results']),
        ]);
    }

    public function history(Request $request)
    {
        $searches = MacSearch::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get(['id', 'mac_normalized', 'ip_address', 'result_count', 'status', 'duration_ms', 'created_at']);

        return response()->json($searches);
    }
}