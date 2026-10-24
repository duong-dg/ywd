<?php
namespace App\Http\Controllers;
use App\Models\Content;
use Illuminate\Http\Request;
class CustomAssetController extends Controller {
 public function show(Request $request,int $id,string $ext){
  $record=Content::where('type','asset')->findOrFail($id);$data=$record->data;abort_unless(!empty($data['enabled'])&&$data['kind']===$ext,404);
  $response=response($data['code'],200,['Content-Type'=>$ext==='css'?'text/css; charset=UTF-8':'application/javascript; charset=UTF-8','X-Content-Type-Options'=>'nosniff','X-Robots-Tag'=>'noindex','Cache-Control'=>'public, max-age=3600']);$response->setEtag(hash('sha256',$data['code']));$response->isNotModified($request);return $response;
 }
}
