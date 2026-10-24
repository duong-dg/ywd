<?php
namespace App\Services;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class ImageUpload {
 public static function store(UploadedFile $file): string {
  $dimensions=@getimagesize($file->getRealPath());
  if(!$dimensions||$dimensions[0]*$dimensions[1]>24000000)throw ValidationException::withMessages(['cover_upload'=>'Ảnh không hợp lệ hoặc lớn hơn 24 megapixel. Vui lòng thu nhỏ trước khi tải lên.']);
  if(!function_exists('imagecreatefromstring')||!function_exists('imagewebp'))return '/'.$file->store('uploads','public_direct');
  $source=@imagecreatefromstring(file_get_contents($file->getRealPath()));if(!$source)return '/'.$file->store('uploads','public_direct');
  // Apply EXIF orientation before removing metadata during WebP encoding.
  if($file->getMimeType()==='image/jpeg'&&function_exists('exif_read_data')){
   $orientation=(@exif_read_data($file->getRealPath())['Orientation']??1);
   $angle=match((int)$orientation){3,4=>180,5,6=>-90,7,8=>90,default=>0};
   if($angle){$rotated=imagerotate($source,$angle,0);if($rotated){imagedestroy($source);$source=$rotated;}}
   if(in_array((int)$orientation,[2,4,5,7],true))imageflip($source,IMG_FLIP_HORIZONTAL);
  }
  $width=imagesx($source);$height=imagesy($source);$ratio=min(1,2000/max($width,$height));$target=imagecreatetruecolor(max(1,(int)round($width*$ratio)),max(1,(int)round($height*$ratio)));
  imagealphablending($target,false);imagesavealpha($target,true);imagefill($target,0,0,imagecolorallocatealpha($target,0,0,0,127));imagecopyresampled($target,$source,0,0,0,0,imagesx($target),imagesy($target),$width,$height);
  ob_start();$ok=imagewebp($target,null,82);$bytes=ob_get_clean();imagedestroy($source);imagedestroy($target);
  if(!$ok||!$bytes)throw ValidationException::withMessages(['cover_upload'=>'Không thể tối ưu ảnh. Vui lòng thử ảnh khác.']);
  $path='uploads/'.Str::uuid().'.webp';Storage::disk('public_direct')->put($path,$bytes);return '/'.$path;
 }
}
