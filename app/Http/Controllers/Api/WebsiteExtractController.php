<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtractWebsiteRequest;
use App\Services\Contracts\WebsiteMetadataServiceInterface;

class WebsiteExtractController extends Controller
{
    public function __construct(
        private readonly WebsiteMetadataServiceInterface $service
    ) {
    }

    public function store(ExtractWebsiteRequest $request)
    {
        $data = $this->service->extract($request->validated('url'));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}