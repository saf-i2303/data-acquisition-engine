<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyInformationRequest;
use App\Services\CompanyInformationService;

class CompanyInformationController extends Controller
{
    public function __construct(
        private readonly CompanyInformationService $service
    ) {
    }

    public function show(CompanyInformationRequest $request)
    {
        $data = $this->service->gather(
            $request->validated('domain'),
            $request->validated('company_name')
        );

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}