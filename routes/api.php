<?php

use App\Http\Controllers\Api\CompanyInformationController;
use App\Http\Controllers\Api\DomainExtractController;
use App\Http\Controllers\Api\LocationExtractController;
use App\Http\Controllers\Api\WebsiteExtractController;
use Illuminate\Support\Facades\Route;

Route::post('/extract/website', [WebsiteExtractController::class, 'store']);
Route::post('/extract/domain', [DomainExtractController::class, 'store']);
Route::post('/extract/location', [LocationExtractController::class, 'store']);
Route::get('/company-information', [CompanyInformationController::class, 'show']);