<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UnsplashService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShowcaseImageController extends Controller
{
    public function search(Request $request, UnsplashService $unsplash): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|max:255',
        ]);

        $photos = $unsplash->searchPhotos($request->input('query'), 6);

        return response()->json(['photos' => $photos]);
    }
}
