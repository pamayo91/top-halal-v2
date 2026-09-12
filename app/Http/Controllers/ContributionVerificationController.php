<?php

namespace App\Http\Controllers;

use App\Models\ContributionVerification;
use App\Services\ContributionIdentityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContributionVerificationController extends Controller
{
    public function __invoke(Request $request, ContributionVerification $verification, string $token, ContributionIdentityService $identities): View
    {
        $request->session()->regenerate();
        $result = $identities->verify($request, $verification, $token);

        return view('public.contributions.email-verified', $result);
    }
}
