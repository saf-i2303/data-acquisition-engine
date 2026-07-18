<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtractLocationRequest;
use App\Services\Contracts\LocationFinderServiceInterface;

class LocationExtractController extends Controller
{
    public function __construct(
        private readonly LocationFinderServiceInterface $service
    ) {
    }

    public function store(ExtractLocationRequest $request)
    {
        $data = $this->service->search($request->validated('query'));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}