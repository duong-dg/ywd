<?php
namespace App\Http\Controllers;
use App\Models\Content;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
class AdminController extends Controller
{
 const SECTIONS=['dashboard'=>'Tổng quan','event'=>'Đám cưới','page'=>'Trang nội dung','menu'=>'Menu','translations'=>'Bản dịch','languages'=>'Ngôn ngữ','lead'=>'Liên hệ','settings'=>'Cài đặt'];
 const FIELDS=[
  'event'=>['title'=>'Tên cặp đôi / khóa dịch','slug'=>'Đường dẫn (slug)','category'=>'Phong cách / khóa dịch','location'=>'Địa điểm','date'=>'Ngày tổ chức','cover'=>'Ảnh bìa (URL hoặc tải ảnh bên dưới)','story'=>'Câu chuyện / khóa dịch','gallery'=>'Album ảnh (mỗi dòng một URL)','theme'=>'Theme','published'=>'Xuất bản','demo'=>'Nội dung minh họa'],
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
  if($action==='logout'){Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();return redirect('/admin');}
  if($action==='save'&&isset(self::FIELDS[$section]))return $this->saveContent($request,$section);
  if($action==='delete'&&in_array($section,['event','page','menu','lead'])){Content::where('type',$section)->findOrFail($request->integer('id'))->delete();return back()->with('flash','Đã xóa.');}
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
  foreach(self::FIELDS[$type] as $key=>$label)if(!isset($rules[$key]))$rules[$key]='nullable|string|max:100000';
  $rules[$type==='menu'?'label':'title']='required|string|max:200';
  if($type!=='menu')$rules['slug']=['required','max:180','regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
  if($type==='event'){$rules['date']='nullable|date_format:Y-m-d';$rules['cover_upload']='nullable|image|mimes:jpg,jpeg,png,webp,avif|max:8192';$rules['gallery_upload']='nullable|array|max:40';$rules['gallery_upload.*']='nullable|image|mimes:jpg,jpeg,png,webp,avif|max:8192';}
  $data=$request->validate($rules);$id=$request->integer('id');
  if($id)Content::where('type',$type)->findOrFail($id);
  if(isset($data['slug']))foreach(records($type) as $r)if($r['slug']===$data['slug']&&$r['id']!==$id)return back()->withErrors(['slug'=>'Slug đã được sử dụng.'])->withInput();
  $values=[];foreach(self::FIELDS[$type] as $key=>$label)$values[$key]=in_array($key,['published','demo'])?$request->boolean($key):trim((string)($data[$key]??''));
  if($type==='menu'&&!in_array($values['placement'],['header','footer','both'],true))$values['placement']='header';
  if($type==='menu'&&!safeUrl($values['url']))return back()->withErrors(['url'=>'Liên kết phải bắt đầu bằng /, # hoặc https://.'])->withInput();
  if($type==='page'&&!in_array($values['mode'],['theme','html']))abort(422);
  if(isset($values['theme'])&&!in_array($values['theme'],['editorial','romance','minimal',...array_keys(config('studio.custom_themes'))]))abort(422);
  if($type==='event'){
   if(!$request->hasFile('cover_upload')&&!safeUrl($values['cover']))return back()->withErrors(['cover'=>'Hãy tải ảnh bìa hoặc nhập URL hợp lệ.'])->withInput();
   foreach(array_filter(explode("\n",$values['gallery'])) as $src)if(!safeUrl(trim($src)))return back()->withErrors(['gallery'=>'URL album không hợp lệ.'])->withInput();
   if($request->hasFile('cover_upload'))$values['cover']='/'.$request->file('cover_upload')->store('uploads','public_direct');
   foreach($request->file('gallery_upload',[]) as $file)if($file)$values['gallery'].="\n/".$file->store('uploads','public_direct');
  }
  saveRecord($type,$values,$id);return redirect(adminUrl($type))->with('flash','Đã lưu nội dung.');
 }
}
