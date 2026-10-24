<?php
namespace App\Http\Controllers;
use App\Models\Content;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use App\Services\Seo;
use App\Models\SeoRedirect;
use App\Models\SeoNotFound;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class AdminController extends Controller
{
 const SECTIONS=['dashboard'=>'Tổng quan','post'=>'Blog','event'=>'Đám cưới','page'=>'Trang nội dung','seo'=>'SEO tổng thể','redirects'=>'Redirect & lỗi 404','menu'=>'Menu','assets'=>'CSS & JavaScript','translations'=>'Bản dịch','languages'=>'Ngôn ngữ','lead'=>'Liên hệ','settings'=>'Cài đặt'];
 const FIELDS=[
  'post'=>['title'=>'Tiêu đề bài viết / khóa dịch','slug'=>'Đường dẫn (slug)','category'=>'Chuyên mục / khóa dịch','author'=>'Tác giả / khóa dịch','excerpt'=>'Đoạn giới thiệu / khóa dịch','cover'=>'Ảnh đại diện (URL hoặc tải lên)','cover_alt'=>'Mô tả alt ảnh đại diện / khóa dịch','body'=>'Nội dung Markdown / khóa dịch','publish_at'=>'Thời gian xuất bản (để trống: xuất bản ngay)','published'=>'Xuất bản'],
  'event'=>['title'=>'Tên cặp đôi / khóa dịch','slug'=>'Đường dẫn (slug)','category'=>'Phong cách / khóa dịch','location'=>'Địa điểm','date'=>'Ngày tổ chức','cover'=>'Ảnh bìa (URL hoặc tải ảnh bên dưới)','cover_alt'=>'Mô tả alt ảnh bìa / khóa dịch','story'=>'Câu chuyện / khóa dịch','gallery'=>'Album ảnh (mỗi dòng một URL)','gallery_alts'=>'Mô tả alt album (mỗi dòng ứng với một ảnh)','theme'=>'Theme','published'=>'Xuất bản','demo'=>'Nội dung minh họa'],
  'page'=>['title'=>'Tiêu đề / khóa dịch','slug'=>'Đường dẫn (slug)','mode'=>'Kiểu trang','theme'=>'Theme','body'=>'Nội dung / HTML / khóa dịch','published'=>'Xuất bản'],
  'menu'=>['label'=>'Tên menu / khóa dịch','url'=>'Liên kết (ví dụ /#stories hoặc /?page=gioi-thieu)','placement'=>'Vị trí','position'=>'Thứ tự','published'=>'Hiển thị']
 ];
 public function index(Request $request){
  $section=$request->query('section','dashboard');if(!isset(self::SECTIONS[$section]))$section='dashboard';
  return view('admin',['sections'=>self::SECTIONS,'fields'=>self::FIELDS,'section'=>$section,'logged'=>Auth::check(),'isSetup'=>!User::exists()&&app()->environment('local')&&in_array($request->ip(),['127.0.0.1','::1'],true),'error'=>session('errors')?->first()??'','success'=>session('flash',''),'config'=>['custom_themes'=>config('studio.custom_themes'),'smtp_host'=>config('mail.default')==='smtp'?config('mail.mailers.smtp.host'):null],'lang'=>app()->getLocale(),'languages'=>setting('languages')]);
 }
 public function update(Request $request){
  $action=$request->input('action');$section=$request->query('section','dashboard');
  if($action==='setup'&&app()->environment('local')&&in_array($request->ip(),['127.0.0.1','::1'],true)&&!User::exists()){
   $data=$request->validate(['username'=>'required|string|min:3|max:100','password'=>'required|string|min:12|max:128']);
   $user=User::create(['name'=>$data['username'],'email'=>'admin@studio.local','password'=>Hash::make($data['password'])]);Auth::login($user);$request->session()->regenerate();return redirect('/admin');
  }
  if($action==='login'){
   $key='login:'.$request->ip();if(RateLimiter::tooManyAttempts($key,8))return back()->withErrors(['login'=>'Tạm khóa đăng nhập. Thử lại sau 15 phút.']);
   $data=$request->validate(['username'=>'required|string','password'=>'required|string']);$user=User::where('name',$data['username'])->first();
   if(!$user||!Hash::check($data['password'],$user->password)){RateLimiter::hit($key,900);return back()->withErrors(['login'=>'Tên đăng nhập hoặc mật khẩu không đúng.']);}
   RateLimiter::clear($key);Auth::login($user);$request->session()->regenerate();return redirect('/admin');
  }
  abort_unless(Auth::check(),403);
  if(in_array($action,['smtp_save','smtp_test','appearance_save','asset_save','asset_delete'],true))return app(SettingsController::class)->handle($request);
  if($action==='logout'){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect('/admin');}
  if($action==='save'&&isset(self::FIELDS[$section]))return $this->saveContent($request,$section);
  if($action==='delete'&&in_array($section,['event','page','post','menu','lead'])){Content::where('type',$section)->findOrFail($request->integer('id'))->delete();return back()->with('flash','Đã xóa.');}
  if($action==='seo_settings'){
   $rules=['seo_allow_indexing'=>'nullable','seo_home_title'=>'nullable|string|max:200','seo_home_description'=>'nullable|string|max:500','seo_blog_title'=>'nullable|string|max:200','seo_blog_description'=>'nullable|string|max:500','seo_share_image'=>'nullable|string|max:2048','seo_org_logo'=>'nullable|string|max:2048','seo_social_urls'=>'nullable|string|max:5000','seo_verification_google'=>['nullable','max:200','regex:/^[a-zA-Z0-9_-]+$/'],'seo_verification_bing'=>['nullable','max:200','regex:/^[a-zA-Z0-9_-]+$/']];$data=$request->validate($rules);
   foreach(['seo_share_image','seo_org_logo'] as $key)if(!empty($data[$key])&&!safeUrl($data[$key]))return back()->withErrors(['image'=>'URL ảnh không hợp lệ.'])->withInput();
   foreach(array_filter(explode("\n",$data['seo_social_urls']??'')) as $link)if(!filter_var(trim($link),FILTER_VALIDATE_URL)||!preg_match('~^https?://~i',trim($link)))return back()->withErrors(['links'=>'Liên kết mạng xã hội cần là URL HTTP/HTTPS hợp lệ.'])->withInput();
   foreach($rules as $key=>$rule)put($key,$key==='seo_allow_indexing'?$request->boolean($key):($data[$key]??''));return back()->with('flash','Đã lưu SEO tổng thể.');
  }
  if($action==='redirect_delete'){SeoRedirect::findOrFail($request->integer('id'))->delete();return back()->with('flash','Đã xóa chuyển hướng.');}
  if($action==='redirect_save'){
   $data=$request->validate(['source'=>'required|string|max:512','target'=>'required|string|max:2048','status'=>['required',Rule::in([301,302])]]);
   foreach(['source','target'] as $key)if(!Seo::localPath($data[$key],$key==='target'))return back()->withErrors(['redirect'=>'Chỉ dùng đường dẫn nội bộ bắt đầu bằng /, không dùng #, dấu gạch chéo ngược hoặc dot segment.'])->withInput();
   if(str_contains($data['source'],'?')||in_array($data['source'],['/','/blog','/sitemap.xml','/robots.txt','/up'])||preg_match('~^/(admin|assets|uploads)(/|$)~',$data['source']))return back()->withErrors(['redirect'=>'Không thể chuyển hướng đường dẫn hệ thống hoặc nguồn có query.'])->withInput();
   foreach(['event','page','post'] as $type)foreach(records($type) as $item)if(Seo::path($type,$item['slug'])===$data['source'])return back()->withErrors(['redirect'=>'Nguồn đang được nội dung sử dụng. Hãy đổi slug hoặc gỡ nội dung trước.'])->withInput();
   $visited=[$data['source']];$next=parse_url($data['target'],PHP_URL_PATH);for($i=0;$i<20;$i++){if(in_array($next,$visited,true))return back()->withErrors(['redirect'=>'Chuyển hướng tạo vòng lặp.'])->withInput();$visited[]=$next;$existing=SeoRedirect::where('source',$next)->first();if(!$existing)break;$next=parse_url($existing->target,PHP_URL_PATH);if($i===19)return back()->withErrors(['redirect'=>'Chuỗi chuyển hướng quá dài.']);}
   SeoRedirect::updateOrCreate(['source'=>$data['source']],['target'=>$data['target'],'status'=>$data['status'],'content_id'=>null]);return redirect(adminUrl('redirects'))->with('flash','Đã lưu chuyển hướng.');
  }
  if($action==='settings'){
   $rules=['brand'=>'required|string|max:100','email'=>'nullable|email|max:254','phone'=>'nullable|string|max:50','address'=>'nullable|string|max:500','hero_image'=>'nullable|string|max:2000','about_image'=>'nullable|string|max:2000','password'=>'nullable|string|min:12|max:128'];
   $data=$request->validate($rules);foreach(['hero_image','about_image'] as $key)if(!empty($data[$key])&&!safeUrl($data[$key]))return back()->withErrors(['url'=>'URL ảnh không hợp lệ.']);
   if(!empty($data['password'])){if(!Hash::check($request->input('current_password',''),Auth::user()->password))return back()->withErrors(['password'=>'Mật khẩu hiện tại không đúng.']);Auth::user()->update(['password'=>Hash::make($data['password'])]);$request->session()->regenerate();}
   foreach(['brand','email','phone','address','hero_image','about_image'] as $key)put($key,$data[$key]??'');return back()->with('flash','Đã lưu cài đặt.');
  }
  if($action==='language'){$data=$request->validate(['code'=>['required','regex:/^[a-z]{2}(?:-[a-z]{2})?$/'],'name'=>'required|string|max:60']);$languages=setting('languages');$languages[$data['code']]=$data['name'];put('languages',$languages);return back()->with('flash','Đã lưu ngôn ngữ.');}
  if($action==='remove_language'){$languages=setting('languages');$code=$request->input('code');abort_if($code==='vi',422);unset($languages[$code]);put('languages',$languages);return back();}
  if($action==='translation'){
   $data=$request->validate(['key'=>['required','max:160','regex:/^[a-zA-Z0-9_.-]+$/'],'translation'=>'required|array','translation.*'=>'nullable|string|max:100000']);$dict=setting('translations',[]);
   foreach(setting('languages') as $code=>$name){$value=trim($data['translation'][$code]??'');if($value!=='')$dict[$code][$data['key']]=$value;else unset($dict[$code][$data['key']]);}
   put('translations',$dict);return redirect(adminUrl('translations'))->with('flash','Đã lưu bản dịch.');
  }
  if($action==='lead_status'){$record=Content::where('type','lead')->findOrFail($request->integer('id'));$data=$record->data;$data['status']=$request->input('status')==='done'?'done':'new';$record->update(['data'=>$data]);return back();}
  abort(422);
 }
 private function saveContent(Request $request,string $type){
  $rules=['id'=>'nullable|integer|min:0','published'=>'nullable','demo'=>'nullable'];
  $allFields=self::FIELDS[$type]+($type!=='menu'?Seo::FIELDS:[]);
  foreach($allFields as $key=>$label)if(!isset($rules[$key]))$rules[$key]=in_array($key,['robots_noindex','robots_nofollow'])?'nullable':'nullable|string|max:100000';
  $rules[$type==='menu'?'label':'title']='required|string|max:200';
  if($type!=='menu')$rules['slug']=['required','max:180','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
  if(in_array($type,['event','post'])){$rules['cover_upload']='nullable|image|mimes:jpg,jpeg,png,webp,avif|max:8192';}
  if($type==='event'){$rules['date']='nullable|date_format:Y-m-d';$rules['gallery_upload']='nullable|array|max:40';$rules['gallery_upload.*']='nullable|image|mimes:jpg,jpeg,png,webp,avif|max:8192';}
  if($type==='post'){$rules['publish_at']='nullable|date';$rules['body']='required|string|max:100000';}
  if($type!=='menu'){$rules['seo_title']='nullable|string|max:200';$rules['seo_description']='nullable|string|max:500';$rules['focus_keyword']='nullable|string|max:200';$rules['og_image']='nullable|string|max:2048';$rules['canonical_url']='nullable|url:http,https|max:2048';}
  $data=$request->validate($rules);$id=$request->integer('id');
  $old=$id?Content::where('type',$type)->findOrFail($id):null;
  if(isset($data['slug']))foreach(records($type) as $r)if($r['slug']===$data['slug']&&$r['id']!==$id)return back()->withErrors(['slug'=>'Slug đã được sử dụng.'])->withInput();
  $values=[];foreach($allFields as $key=>$label)$values[$key]=in_array($key,['published','demo','robots_noindex','robots_nofollow'])?$request->boolean($key):trim((string)($data[$key]??''));
  if($type!=='menu'){
   $reserved=SeoRedirect::where('source',Seo::path($type,$values['slug']))->first();if($reserved&&$reserved->content_id!==$id)return back()->withErrors(['slug'=>'Đường dẫn này đã được dùng làm chuyển hướng. Xóa redirect trước nếu muốn dùng lại.'])->withInput();
   if($values['og_image']!==''&&!safeUrl($values['og_image']))return back()->withErrors(['og_image'=>'URL ảnh chia sẻ không hợp lệ.'])->withInput();
   if(str_contains($values['canonical_url'],'#'))return back()->withErrors(['canonical_url'=>'Canonical không được chứa #.'])->withInput();
  }
  if($type==='post'&&$values['publish_at']!=='')$values['publish_at']=Carbon::parse($values['publish_at'])->toIso8601String();
  if($type==='menu'&&!in_array($values['placement'],['header','footer','both'],true))$values['placement']='header';
  if($type==='menu'&&!safeUrl($values['url']))return back()->withErrors(['url'=>'Liên kết phải bắt đầu bằng /, # hoặc https://.'])->withInput();
  if($type==='page'&&!in_array($values['mode'],['theme','html']))abort(422);
  if(isset($values['theme'])&&!in_array($values['theme'],['editorial','romance','minimal',...array_keys(config('studio.custom_themes'))]))abort(422);
  if(in_array($type,['event','post'])){
   if(!$request->hasFile('cover_upload')&&!safeUrl($values['cover']))return back()->withErrors(['cover'=>'Hãy tải ảnh bìa hoặc nhập URL hợp lệ.'])->withInput();
   foreach(array_filter(explode("\n",$values['gallery']??'')) as $src)if(!safeUrl(trim($src)))return back()->withErrors(['gallery'=>'URL album không hợp lệ.'])->withInput();
   if($request->hasFile('cover_upload'))$values['cover']=\App\Services\ImageUpload::store($request->file('cover_upload'));
   if($type==='event')foreach($request->file('gallery_upload',[]) as $file)if($file)$values['gallery'].="\n".\App\Services\ImageUpload::store($file);
  }
  DB::transaction(function()use($type,$values,$id,$old){$record=saveRecord($type,$values,$id);if($type==='menu')return;$path=Seo::path($type,$values['slug']);SeoRedirect::where('source',$path)->where('content_id',$record->id)->delete();SeoRedirect::where('content_id',$record->id)->update(['target'=>$path]);if($old&&$old->data['slug']!==$values['slug'])SeoRedirect::updateOrCreate(['source'=>Seo::path($type,$old->data['slug'])],['target'=>$path,'status'=>301,'content_id'=>$record->id]);});return redirect(adminUrl($type))->with('flash','Đã lưu nội dung.');
 }
}
