<?php

namespace App\Http\Controllers;

use App\Services\Location\AddressSuggestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAddressAutocompleteController extends Controller
{
    public function __invoke(Request $request, AddressSuggestionService $suggestions): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:255']]);
        return response()->json(['data' => collect($suggestions->suggest($data['q']))->map(fn ($item) => ['token'=>$item['token'], 'label'=>$item['label']])->all()]);
    }
}
