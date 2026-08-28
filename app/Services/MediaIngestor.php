<?php
namespace App\Services;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
class MediaIngestor
{
 public function ingest(UploadedFile $file, ?string $altText=null, ?string $caption=null): MediaAsset { $info=@getimagesize($file->getRealPath());if(!$info||!in_array($info['mime'],['image/jpeg','image/png','image/webp'],true))throw ValidationException::withMessages(['file'=>'Le fichier doit être une image JPEG, PNG ou WebP valide.']);if($file->getSize()>10*1024*1024)throw ValidationException::withMessages(['file'=>'Le fichier dépasse 10 Mo.']);$checksum=hash_file('sha256',$file->getRealPath());$asset=MediaAsset::where('checksum',$checksum)->first();if(!$asset){$path='media/originals/'.$checksum.'.'.($file->extension()?:'bin');Storage::disk(config('legacy-media.disk'))->put($path,file_get_contents($file->getRealPath()));$asset=MediaAsset::create(['original_path'=>$path,'mime'=>$info['mime'],'width'=>$info[0],'height'=>$info[1],'bytes'=>$file->getSize(),'checksum'=>$checksum,'alt_text'=>$altText,'caption'=>$caption]);app(MediaVariantGenerator::class)->generate($asset);}app(AdminAudit::class)->record('media.uploaded',$asset);return $asset; }
}
