<?php
namespace App\Services;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\MailManager;
class SmtpSettings {
 public static function display(): array {
  $stored=setting('smtp_config',null);
  $settings=$stored??['enabled'=>config('mail.default')==='smtp','host'=>config('mail.mailers.smtp.host',''),'port'=>config('mail.mailers.smtp.port',587),'security'=>config('mail.mailers.smtp.scheme')==='smtps'?'ssl':'tls','username'=>config('mail.mailers.smtp.username',''),'from_address'=>config('mail.from.address',''),'from_name'=>config('mail.from.name',''),'to_address'=>config('studio.mail_to','')];
  $settings['has_password']=$stored!==null?!empty($stored['password']):!empty(config('mail.mailers.smtp.password'));
  unset($settings['password']);return $settings;
 }
 public static function ready(): bool {$settings=self::display();return !empty($settings['enabled'])&&!empty($settings['host'])&&!empty($settings['to_address']);}
 public static function apply(): void {
  $settings=setting('smtp_config',null);if($settings===null)return;
  config(['studio.mail_to'=>$settings['to_address']??null]);
  if(empty($settings['enabled'])){config(['mail.default'=>'log']);return;}
  $password=!empty($settings['password'])?Crypt::decryptString($settings['password']):null;
  config(['mail.default'=>'smtp','mail.mailers.smtp'=>['transport'=>'smtp','scheme'=>$settings['security']==='ssl'?'smtps':'smtp','url'=>null,'host'=>$settings['host'],'port'=>(int)$settings['port'],'username'=>$settings['username']?:null,'password'=>$password,'timeout'=>12,'auto_tls'=>$settings['security']!=='none','require_tls'=>$settings['security']==='tls'],'mail.from'=>['address'=>$settings['from_address'],'name'=>$settings['from_name']]]);
  $manager=app('mail.manager');if($manager instanceof MailManager)$manager->purge('smtp');
 }
 public static function testFailure(\Throwable $e): string {
  $message=strtolower($e->getMessage());
  if(str_contains($message,'decrypt')||$e instanceof \Illuminate\Contracts\Encryption\DecryptException)return 'Không đọc được mật khẩu đã lưu. Hãy nhập lại mật khẩu SMTP.';
  if(str_contains($message,'authenticat')||str_contains($message,'535'))return 'SMTP từ chối xác thực. Kiểm tra tài khoản và mật khẩu ứng dụng.';
  if(str_contains($message,'certificate')||str_contains($message,'tls'))return 'Không thiết lập được TLS/SSL. Kiểm tra host, cổng và chứng chỉ máy chủ.';
  if(str_contains($message,'timed out')||str_contains($message,'connection')||str_contains($message,'getaddrinfo'))return 'Không kết nối được SMTP. Kiểm tra host, cổng và kết nối mạng của server.';
  return 'Chưa gửi được email thử. Kiểm tra cấu hình và quyền gửi của tài khoản SMTP.';
 }
}
