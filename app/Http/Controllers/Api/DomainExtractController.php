<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtractDomainRequest;
use App\Services\Contracts\DomainIntelligenceServiceInterface;

class DomainExtractController extends Controller
{
    public function __construct(
        private readonly DomainIntelligenceServiceInterface $service
    ) {
    }

    public function store(ExtractDomainRequest $request)
    {
        $data = $this->service->lookup($request->validated('domain'));

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}