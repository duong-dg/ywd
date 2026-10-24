<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\Seo;
class SiteController extends Controller
{
 public function index(Request $request){
  $events=array_values(array_filter(records('event'),fn($r)=>Seo::visible($r)));$event=null;$page=null;$post=null;
  $type=$request->route('kind')??($request->has('wedding')?'event':($request->has('page')?'page':'home'));
  $slug=$request->route('slug')??$request->query($type==='event'?'wedding':'page');
  if($slug!==null&&!is_string($slug)){$type='missing';$slug='';}
  $preview=$request->boolean('preview')&&Auth::check();$item=null;
  if(in_array($type,['event','page','post']))foreach(records($type) as $r)if($r['slug']===$slug&&(Seo::visible($r)||$preview))$item=$r;
  if(!$item&&$request->getPathInfo()==='/'&&in_array($type,['event','page'])){$alias=\App\Models\SeoRedirect::where('source',Seo::path($type,$slug??''))->first();if($alias&&$alias->content_id){$linked=\App\Models\Content::find($alias->content_id);if($linked&&Seo::visible($linked->data))return redirect(Seo::localizedUrl(Seo::path($linked->type,$linked->data['slug']),app()->getLocale()),301);}}
  if($type==='event')$event=$item;if($type==='page')$page=$item;if($type==='post')$post=$item;
  $isBlog=$type==='blog';$missing=$type==='missing'||(in_array($type,['event','page','post'])&&!$item);
  $path=$missing?$request->getPathInfo():($type==='home'?'/':Seo::path($type,$slug??''));
  $posts=[];$categories=[];$currentPage=1;$totalPages=1;$rawCategory=$request->query('category','');$category=is_string($rawCategory)?$rawCategory:'';$query=[];
  if($isBlog){
   $posts=array_values(array_filter(records('post'),fn($r)=>Seo::visible($r)));usort($posts,fn($a,$b)=>strcmp(($b['publish_at']??'')?:$b['_created_at'],($a['publish_at']??'')?:$a['_created_at']));
   $categories=array_values(array_unique(array_filter(array_column($posts,'category'))));
   if($category!=='')$posts=array_values(array_filter($posts,fn($r)=>$r['category']===$category));
   $currentPage=max(1,(int)$request->query('p',1));$totalPages=max(1,(int)ceil(count($posts)/9));if($currentPage>$totalPages)$missing=true;$posts=array_slice($posts,($currentPage-1)*9,9);if($currentPage>1)$query['p']=$currentPage;
  }
  $brand=setting('brand','Vow & Verse');$title=$event?field($event,'title'):($page?field($page,'title'):$brand);
  $theme=$event['theme']??$page['theme']??'editorial';$customThemes=config('studio.custom_themes',[]);
  if(!in_array($theme,['editorial','romance','minimal'],true)&&!isset($customThemes[$theme]))$theme='editorial';
  $seo=Seo::metadata($item,$type,$path,$missing,$preview,$query);$article=$post?Seo::markdown(field($post,'body')):null;
  $response=response()->view('site',compact('events','event','page','post','isBlog','posts','categories','category','currentPage','totalPages','path','seo','article','preview','missing','brand','title','theme','customThemes')+['lang'=>app()->getLocale(),'languages'=>setting('languages'),'notice'=>session('notice',''),'formError'=>session('errors')?->first()??''],$missing?404:200);
  $response->headers->set('X-Robots-Tag',$seo['robots']);if($preview)$response->headers->set('Cache-Control','no-store, private');return $response;
 }
 public function contact(Request $request){
  if($request->filled('website'))return redirect(site_url().'#contact');
  $data=$request->validate(['name'=>'required|string|max:120','email'=>'required|email|max:254','phone'=>'nullable|string|max:50','message'=>'required|string|min:10|max:5000']);
  $key='contact:'.$request->ip();if(RateLimiter::tooManyAttempts($key,5))return back()->withErrors(['message'=>app()->getLocale()==='vi'?'Bạn đã gửi nhiều yêu cầu. Vui lòng thử lại sau một giờ.':'Please try again in one hour.'])->withInput();RateLimiter::hit($key,3600);
  $lead=$data+['phone'=>'','created'=>now()->toIso8601String(),'status'=>'new','mail'=>'not_configured'];$record=saveRecord('lead',$lead);
  if(config('studio.mail_to')&&config('mail.default')==='smtp'){
   try{Mail::raw("Tên: {$data['name']}\nEmail: {$data['email']}\nĐiện thoại: {$lead['phone']}\n\n{$data['message']}",function($mail)use($data){$mail->to(config('studio.mail_to'))->replyTo($data['email'])->subject('Liên hệ tư vấn đám cưới');});$lead['mail']='sent';}
   catch(\Throwable $e){$lead['mail']='failed';Log::warning('Contact delivery failed',['lead'=>$record->id]);}
   saveRecord('lead',$lead,$record->id);
  }
  return redirect(site_url().'#contact')->with('notice',app()->getLocale()==='vi'?'Đã nhận lời nhắn của bạn. Cảm ơn bạn đã chia sẻ!':'Your message has been received. Thank you!');
 }
}
