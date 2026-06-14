<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'organization' => $request->user()->organization,
        ]);
    }

    public function update(UpdateOrganizationRequest $request): JsonResponse
    {
        $organization = $request->user()->organization()->updateOrCreate([], [
            'source_url' => $request->validated('url'),
            'external_id' => null,
            'name' => null,
            'rating' => null,
            'ratings_count' => null,
            'reviews_count' => null,
            'sync_status' => 'pending',
            'sync_error' => null,
            'synced_at' => null,
        ]);

        return response()->json([
            'organization' => $organization,
        ]);
    }
}
