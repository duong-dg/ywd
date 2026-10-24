<?php
namespace App\Http\Controllers;
use App\Models\Content;
use App\Services\SmtpSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class SettingsController extends Controller {
 public function handle(Request $request){
  abort_unless(Auth::check(),403);
  return match($request->input('action')){'smtp_save'=>$this->smtpSave($request),'smtp_test'=>$this->smtpTest($request),'appearance_save'=>$this->appearance($request),'asset_save'=>$this->assetSave($request),'asset_delete'=>$this->assetDelete($request),default=>abort(422)};
 }
 private function smtpSave(Request $request){
  $data=$request->validate(['smtp_enabled'=>'nullable','smtp_host'=>['nullable','string','max:253','regex:/^[a-zA-Z0-9][a-zA-Z0-9.:-]*$/'],'smtp_port'=>'required|integer|min:1|max:65535','smtp_security'=>['required',Rule::in(['tls','ssl','none'])],'smtp_username'=>'nullable|string|max:254','smtp_password'=>'nullable|string|max:4096','smtp_forget_password'=>'nullable','smtp_from_address'=>'nullable|email|max:254','smtp_from_name'=>['nullable','string','max:100','not_regex:/[\r\n]/'],'smtp_to_address'=>'nullable|email|max:254']);
  if($request->boolean('smtp_enabled'))foreach(['smtp_host','smtp_from_address','smtp_to_address'] as $key)if(empty($data[$key]))throw ValidationException::withMessages([$key=>'Vui lòng điền đầy đủ host và email gửi/nhận trước khi bật SMTP.']);
  $old=setting('smtp_config',null);$encrypted=$old['password']??null;
  if($old===null&&config('mail.mailers.smtp.password'))$encrypted=Crypt::encryptString(config('mail.mailers.smtp.password'));
  if($request->boolean('smtp_forget_password'))$encrypted=null;
  if(isset($data['smtp_password'])&&$data['smtp_password']!=='')$encrypted=Crypt::encryptString($data['smtp_password']);
  if($request->boolean('smtp_enabled')&&!empty($data['smtp_username'])&&!$encrypted)throw ValidationException::withMessages(['smtp_password'=>'Vui lòng nhập mật khẩu cho tài khoản SMTP.']);
  $settings=['enabled'=>$request->boolean('smtp_enabled'),'host'=>$data['smtp_host']??'','port'=>(int)$data['smtp_port'],'security'=>$data['smtp_security'],'username'=>$data['smtp_username']??'','password'=>$encrypted,'from_address'=>$data['smtp_from_address']??'','from_name'=>$data['smtp_from_name']??'','to_address'=>$data['smtp_to_address']??''];
  put('smtp_config',$settings);put('smtp_last_test',[]);return redirect(adminUrl('settings').'#smtp')->with('flash','Đã lưu SMTP. Dùng Gửi email thử để kiểm tra cấu hình.');
 }
 private function smtpTest(Request $request){
  $data=$request->validate(['test_email'=>'required|email|max:254']);
  if(!SmtpSettings::ready())return redirect(adminUrl('settings').'#smtp')->withErrors(['smtp'=>'Hãy lưu và bật cấu hình SMTP trước khi gửi thử.']);
  $key='smtp-test:'.Auth::id();if(RateLimiter::tooManyAttempts($key,1))return redirect(adminUrl('settings').'#smtp')->withErrors(['smtp'=>'Chờ một phút trước khi gửi lại.']);RateLimiter::hit($key,60);
  try{SmtpSettings::apply();Mail::raw('Đây là email kiểm tra cấu hình SMTP của website '.setting('brand').'.',fn($mail)=>$mail->to($data['test_email'])->subject('Kiểm tra SMTP · '.setting('brand')));put('smtp_last_test',['success'=>true,'at'=>now()->toIso8601String(),'message'=>'Máy chủ SMTP đã chấp nhận email thử. Kiểm tra hộp thư và thư rác.']);return redirect(adminUrl('settings').'#smtp')->with('flash','SMTP đã chấp nhận email thử. Hãy kiểm tra hộp thư nhận.');}
  catch(\Throwable $e){$message=SmtpSettings::testFailure($e);put('smtp_last_test',['success'=>false,'at'=>now()->toIso8601String(),'message'=>$message]);return redirect(adminUrl('settings').'#smtp')->withErrors(['smtp'=>$message]);}
 }
 private function appearance(Request $request){put('sticky_menu',$request->boolean('sticky_menu'));return redirect(adminUrl('settings').'#appearance')->with('flash','Đã lưu giao diện và menu.');}
 private function assetSave(Request $request){
  $data=$request->validate(['id'=>'nullable|integer|min:0','name'=>'nullable|string|max:100','kind'=>['required',Rule::in(['css','js'])],'placement'=>['required',Rule::in(['head','footer'])],'position'=>'required|integer|min:0|max:9999','enabled'=>'nullable','code'=>'nullable|string|max:262144','asset_file'=>'nullable|file|max:256']);
  $id=$request->integer('id');if($id)Content::where('type','asset')->findOrFail($id);$code=$data['code']??'';$name=trim($data['name']??'');
  if($request->hasFile('asset_file')){$file=$request->file('asset_file');if(strtolower($file->getClientOriginalExtension())!==$data['kind'])throw ValidationException::withMessages(['asset_file'=>'Định dạng file phải đúng loại CSS hoặc JS đã chọn.']);$code=file_get_contents($file->getRealPath());if($name==='')$name=pathinfo($file->getClientOriginalName(),PATHINFO_FILENAME);}
  if($name===''||mb_strlen($name)>100)throw ValidationException::withMessages(['name'=>'Nhập tên file tối đa 100 ký tự.']);
  if(trim($code)===''||strlen($code)>262144||str_contains($code,"\0")||!mb_check_encoding($code,'UTF-8'))throw ValidationException::withMessages(['code'=>'File cần là văn bản UTF-8, tối đa 256 KB, có nội dung và không chứa dữ liệu nhị phân.']);
  saveRecord('asset',['name'=>$name,'kind'=>$data['kind'],'filename'=>(Str::slug($name)?:'custom').'.'.$data['kind'],'code'=>$code,'placement'=>$data['kind']==='css'?'head':$data['placement'],'position'=>(int)$data['position'],'enabled'=>$request->boolean('enabled')],$id);
  return redirect(adminUrl('assets'))->with('flash','Đã lưu file. CSS/JS đang bật sẽ áp dụng khi tải lại website.');
 }
 private function assetDelete(Request $request){Content::where('type','asset')->findOrFail($request->integer('id'))->delete();return redirect(adminUrl('assets'))->with('flash','Đã xóa file tùy chỉnh.');}
}
