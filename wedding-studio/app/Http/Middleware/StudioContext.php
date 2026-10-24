<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class StudioContext
{
 public function handle(Request $request,Closure $next){
  $languages=setting('languages',['vi'=>'Tiếng Việt']);$rawLocale=$request->query('lang','vi');$locale=is_string($rawLocale)?$rawLocale:'vi';app()->setLocale(isset($languages[$locale])?$locale:'vi');
  $response=$next($request);$response->headers->set('X-Content-Type-Options','nosniff');$response->headers->set('Referrer-Policy','strict-origin-when-cross-origin');$response->headers->set('X-Frame-Options','SAMEORIGIN');
  if($request->is('admin*')){$response->headers->set('Cache-Control','no-store, private');$response->headers->set('X-Robots-Tag','noindex, nofollow');}
  return $response;
 }
}
