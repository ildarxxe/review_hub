<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReviewController extends Controller
{
    private const PER_PAGE = 50;

    public function index(Request $request): AnonymousResourceCollection
    {
        $organizationId = $request->user()->organization?->id;

        $reviews = Review::query()
            ->where('organization_id', $organizationId ?? 0)
            ->orderBy('position')
            ->paginate(self::PER_PAGE);

        return ReviewResource::collection($reviews);
    }
}
