<?php
namespace App\Http\Controllers;
use App\Models\MediaAsset; use Illuminate\Support\Facades\Storage;
class MediaController extends Controller { public function show(MediaAsset $asset, ?int $width=null){$variant=$width?$asset->variants()->where('width',$width)->where('format','webp')->first():null;$path=$variant?->path??$asset->original_path;abort_unless(Storage::disk(config('legacy-media.disk'))->exists($path),404);return response(Storage::disk(config('legacy-media.disk'))->get($path),200,['Content-Type'=>$variant?'image/webp':$asset->mime,'Cache-Control'=>'public, max-age=31536000, immutable']);}}
