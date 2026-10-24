<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
class SiteController extends Controller
{
 public function index(Request $request){
  $events=array_values(array_filter(records('event'),fn($r)=>!empty($r['published'])));$event=null;$page=null;
  foreach($events as $r)if($r['slug']===$request->query('wedding'))$event=$r;
  foreach(records('page') as $r)if(!empty($r['published'])&&$r['slug']===$request->query('page'))$page=$r;
  $missing=($request->has('wedding')&&!$event)||($request->has('page')&&!$page);
  $brand=setting('brand','Vow & Verse');$title=$event?field($event,'title'):($page?field($page,'title'):$brand);
  $theme=$event['theme']??$page['theme']??'editorial';$customThemes=config('studio.custom_themes',[]);
  if(!in_array($theme,['editorial','romance','minimal'],true)&&!isset($customThemes[$theme]))$theme='editorial';
  return response()->view('site',compact('events','event','page','missing','brand','title','theme','customThemes')+['lang'=>app()->getLocale(),'languages'=>setting('languages'),'notice'=>session('notice',''),'formError'=>session('errors')?->first()??''],$missing?404:200);
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
