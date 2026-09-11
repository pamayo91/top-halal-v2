<?php

namespace App\Http\Controllers;

use App\Models\Restaurant;
use App\Models\RestaurantClaim;
use App\Models\User;
use App\Notifications\ClaimLifecycleNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RestaurantClaimController extends Controller
{
    public function create(Request $request, Restaurant $restaurant): View|Response|RedirectResponse
    {
        if (! $restaurant->isClaimable()) return response()->view('claims.unavailable', status: 409);
        $user = $request->user();
        if ($user?->must_change_password) {
            $request->session()->put('url.intended', route('claims.create', $restaurant));
            return redirect()->route('password.change');
        }
        return view('claims.create', compact('restaurant', 'user'));
    }

    public function login(Request $request, Restaurant $restaurant): RedirectResponse { return $this->redirectToAuthentication($request, $restaurant, 'login'); }
    public function register(Request $request, Restaurant $restaurant): RedirectResponse { return $this->redirectToAuthentication($request, $restaurant, 'register'); }

    public function store(Request $request, Restaurant $restaurant): RedirectResponse
    {
        if (! $restaurant->isClaimable()) return back()->withErrors(['claim' => 'Ce restaurant est déjà géré ou fait actuellement l’objet d’une demande de revendication.']);
        $user = $request->user();
        if ($user?->isVerifiedRestaurateur()) {
            $request->validate(['certified' => ['accepted']]);
            $claim = RestaurantClaim::create(['restaurant_id' => $restaurant->id, 'user_id' => $user->id, 'email' => $user->email, 'full_name' => $user->name, 'certified' => true, 'source' => 'verified_claim', 'status' => 'pending', 'submitted_at' => now()]);
            $user->notify(new ClaimLifecycleNotification($claim, 'submitted', route('claims.show', $claim)));
            return redirect()->route('claims.show', $claim)->with('status', 'Votre demande a bien été envoyée. Elle sera vérifiée par l’équipe Top Halal.');
        }

        $request->merge(['siret' => preg_replace('/\D+/', '', (string) $request->input('siret'))]);
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255'],
            'company' => ['required', 'string', 'max:255'], 'siret' => ['required', 'digits:14'], 'certified' => ['accepted'],
            'identity_document' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ]);
        if (! $this->isValidSiret($data['siret'])) return back()->withErrors(['siret' => 'Le SIRET doit comporter 14 chiffres valides.'])->withInput();
        if (User::query()->where('email', Str::lower($data['email']))->get()->contains(fn (User $candidate) => $candidate->isVerifiedRestaurateur())) {
            return back()->withErrors(['email' => 'Un compte restaurateur existe déjà avec cette adresse e-mail. Connectez-vous pour continuer.'])->withInput();
        }
        $token = Str::random(64);
        $claim = RestaurantClaim::create([
            'restaurant_id' => $restaurant->id, 'user_id' => $user?->id, 'full_name' => $data['full_name'], 'email' => Str::lower($data['email']),
            'company' => $data['company'], 'siret' => $data['siret'], 'certified' => true,
            'identity_document_path' => $request->file('identity_document')->store('claims/identity-documents', 'local'),
            'source' => 'first_claim', 'status' => 'pending_email_verification', 'submitted_at' => now(),
            'email_verification_token' => hash('sha256', $token), 'email_verification_expires_at' => now()->addHours(24),
        ]);
        $url = URL::temporarySignedRoute('claims.verify', now()->addHours(24), ['claim' => $claim, 'token' => $token]);
        Notification::route('mail', $claim->email)->notify(new ClaimLifecycleNotification($claim, 'verify', $url));
        return redirect()->route('claims.received')->with('status', 'Votre demande a bien été enregistrée. Vérifiez votre boîte e-mail pour confirmer votre adresse.');
    }

    public function verify(RestaurantClaim $claim, string $token): View
    {
        abort_unless($claim->status === 'pending_email_verification' && $claim->email_verification_expires_at?->isFuture() && hash_equals($claim->email_verification_token ?? '', hash('sha256', $token)), 404);
        $claim->update(['status' => 'pending', 'email_verified_at' => now(), 'email_verification_token' => null, 'email_verification_expires_at' => null]);
        return view('claims.email-verified', compact('claim'));
    }

    public function show(RestaurantClaim $claim): View
    {
        abort_unless($claim->user_id === request()->user()?->id || request()->user()?->role === 'admin', 403);
        return view('claims.show', compact('claim'));
    }
    public function received(): View { return view('claims.received'); }

    private function redirectToAuthentication(Request $request, Restaurant $restaurant, string $route): RedirectResponse
    {
        abort_unless($restaurant->isClaimable(), 409, 'Ce restaurant est déjà géré ou fait actuellement l’objet d’une demande de revendication.');
        $request->session()->put('url.intended', route('claims.create', $restaurant));
        return redirect()->route($route);
    }
    private function isValidSiret(string $siret): bool { $sum = 0; foreach (str_split($siret) as $i => $digit) { $n = (int) $digit; if ($i % 2 === 0) { $n *= 2; if ($n > 9) $n -= 9; } $sum += $n; } return $sum % 10 === 0; }
}
