<?php

use App\Http\Controllers\Api\WebsiteExtractController;
use Illuminate\Support\Facades\Route;

Route::post('/extract/website', [WebsiteExtractController::class, 'store']);