<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\SmtpSettings;
use Database\Seeders\StudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class SettingsTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void {parent::setUp();$this->seed(StudioSeeder::class);}
 private function smtp():array {return ['action'=>'smtp_save','smtp_enabled'=>'1','smtp_host'=>'smtp.example.com','smtp_port'=>587,'smtp_security'=>'tls','smtp_username'=>'sender@example.com','smtp_password'=>'private-mail-password','smtp_from_address'=>'sender@example.com','smtp_from_name'=>'Studio','smtp_to_address'=>'owner@example.com'];}
 public function test_settings_require_authentication():void {$this->post('/admin',['action'=>'smtp_save'])->assertForbidden();$this->post('/admin',['action'=>'asset_save'])->assertForbidden();}
 public function test_smtp_password_is_encrypted_preserved_and_not_rendered():void {
  $this->actingAs(User::factory()->create());$data=$this->smtp();$data['smtp_password']=' private-mail-password ';$this->post('/admin',$data)->assertRedirect();$stored=setting('smtp_config');$this->assertNotSame($data['smtp_password'],$stored['password']);$this->assertSame($data['smtp_password'],Crypt::decryptString($stored['password']));
  $this->get('/admin?section=settings')->assertOk()->assertDontSee($data['smtp_password'])->assertDontSee($stored['password']);
  $data['smtp_password']='';$this->post('/admin',$data)->assertRedirect();$this->assertSame($stored['password'],setting('smtp_config')['password']);SmtpSettings::apply();$this->assertTrue(config('mail.mailers.smtp.require_tls'));$this->assertSame('owner@example.com',config('studio.mail_to'));
  unset($data['smtp_enabled']);$data['smtp_forget_password']='1';$this->post('/admin',$data)->assertRedirect();$this->assertNull(setting('smtp_config')['password']);SmtpSettings::apply();$this->assertSame('log',config('mail.default'));
 }
 public function test_invalid_smtp_does_not_flash_password():void {$this->actingAs(User::factory()->create());$data=$this->smtp();$data['smtp_port']=70000;$this->post('/admin',$data)->assertSessionHasErrors('smtp_port')->assertSessionMissing('_old_input.smtp_password');$this->assertNull(setting('smtp_config',null));}
 public function test_smtp_test_uses_saved_config_and_sanitizes_failure():void {
  $this->actingAs(User::factory()->create());$this->post('/admin',$this->smtp());Mail::shouldReceive('purge')->zeroOrMoreTimes();Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('535 authentication failed private-mail-password'));
  $this->post('/admin',['action'=>'smtp_test','test_email'=>'test@example.com'])->assertSessionHasErrors('smtp');$this->assertFalse(setting('smtp_last_test')['success']);$this->assertStringNotContainsString('private-mail-password',setting('smtp_last_test')['message']);
 }
 public function test_uploaded_assets_are_served_cached_and_only_execute_on_frontend():void {
  $this->actingAs(User::factory()->create());$this->post('/admin',['action'=>'asset_save','kind'=>'css','placement'=>'head','position'=>1,'enabled'=>'1','asset_file'=>UploadedFile::fake()->createWithContent('custom.css','body{color:red}')])->assertRedirect();$asset=records('asset')[0];$url=custom_asset_url($asset);
  $r=$this->get($url);$r->assertOk()->assertSee('body{color:red}',false)->assertHeader('Content-Type','text/css; charset=UTF-8')->assertHeader('X-Content-Type-Options','nosniff');$this->assertFalse($r->headers->has('Set-Cookie'));$this->withHeaders(['If-None-Match'=>$r->headers->get('ETag')])->get($url)->assertStatus(304);
  $this->get('/')->assertOk()->assertSee($url,false);$this->get('/admin?section=assets')->assertOk()->assertDontSee('<link rel="stylesheet" href="'.$url.'"',false);$this->get('/admin?section=assets&edit='.$asset['id'])->assertOk();
  $this->post('/admin',['action'=>'asset_save','id'=>$asset['id'],'name'=>'custom','kind'=>'css','placement'=>'head','position'=>1,'code'=>'body{color:blue}'])->assertRedirect();$this->get($url)->assertNotFound();$this->get('/')->assertDontSee($url,false);
  $this->post('/admin',['action'=>'asset_delete','id'=>$asset['id']])->assertRedirect();$this->assertCount(0,records('asset'));
 }
 public function test_asset_upload_rejects_wrong_extension_and_binary():void {
  $this->actingAs(User::factory()->create());$base=['action'=>'asset_save','name'=>'Bad','kind'=>'js','placement'=>'footer','position'=>1];$this->post('/admin',$base+['asset_file'=>UploadedFile::fake()->createWithContent('bad.php','<?php echo 1;')])->assertSessionHasErrors('asset_file');$this->post('/admin',$base+['code'=>"test\0binary"])->assertSessionHasErrors('code');$this->assertCount(0,records('asset'));
 }
 public function test_sticky_menu_can_be_disabled():void {$this->get('/')->assertSee('sticky-menu');$this->actingAs(User::factory()->create());$this->post('/admin',['action'=>'appearance_save'])->assertRedirect();$this->get('/')->assertDontSee('sticky-menu');}
}
