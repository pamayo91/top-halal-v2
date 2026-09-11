<?php
namespace App\Http\Controllers;
use App\Models\RestaurantClaim;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
class ClaimIdentityDocumentController extends Controller { public function __invoke(RestaurantClaim $claim): Response { abort_unless(request()->user()?->role === 'admin',403); abort_unless($claim->identity_document_path && Storage::disk('local')->exists($claim->identity_document_path),404); return Storage::disk('local')->response($claim->identity_document_path, 'document-identite-'.$claim->id, ['Content-Disposition'=>'inline']); } }
