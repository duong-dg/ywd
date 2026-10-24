<?php
namespace App\Http\Middleware;
use App\Models\Content;
use App\Models\SeoRedirect;
use App\Models\SeoNotFound;
use App\Services\Seo;
use Closure;
use Illuminate\Http\Request;
class SeoRouting {
 public function handle(Request $request,Closure $next){
  if($request->isMethod('GET')&&!$request->is('admin*')){
   $redirect=SeoRedirect::where('source',$request->getPathInfo())->first();
   if($redirect){
    $target=$redirect->target;$current=$redirect;$visited=[$redirect->source];
    for($i=0;$i<20;$i++){
     if($current->content_id){$record=Content::find($current->content_id);if(!$record||!Seo::visible($record->data))abort(404);$target=Seo::path($record->type,$record->data['slug']);break;}
     $target=$current->target;$targetPath=parse_url($target,PHP_URL_PATH);if(in_array($targetPath,$visited,true))abort(404);$visited[]=$targetPath;
     $nextRedirect=SeoRedirect::where('source',$targetPath)->first();if(!$nextRedirect)break;$current=$nextRedirect;if($i===19)abort(404);
    }
    if(!Seo::localPath($target))abort(404);
    if(is_string($request->query('lang'))&&isset(setting('languages')[$request->query('lang')])){$parts=explode('?',$target,2);parse_str($parts[1]??'',$params);$params['lang']=$request->query('lang');$target=$parts[0].'?'.http_build_query($params);}
    if($target!==$request->getRequestUri())return redirect($target,$redirect->status);
   }
  }
  $response=$next($request);
  if($response->getStatusCode()===404&&$request->isMethod('GET')&&!$request->is('admin*','assets/*','uploads/*')&&strlen($request->getPathInfo())<=512){
   $record=SeoNotFound::where('path',$request->getPathInfo())->first();if($record)$record->increment('hits',1,['updated_at'=>now()]);elseif(SeoNotFound::count()<1000)SeoNotFound::firstOrCreate(['path'=>$request->getPathInfo()]);
  }
  return $response;
 }
}
