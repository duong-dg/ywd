<?php
namespace Tests\Feature;
use App\Models\Content;
use App\Models\User;
use Database\Seeders\StudioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
class StudioTest extends TestCase
{
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();$this->seed(StudioSeeder::class);}
 public function test_public_pages_translation_and_fallback(): void {
  $this->get('/')->assertOk()->assertSee('Khoảnh khắc qua đi.');
  $this->get('/?lang=en')->assertOk()->assertSee('Moments pass.')->assertSee('An &amp; Minh',false);
  $this->get('/?lang=unknown')->assertOk()->assertSee('Khoảnh khắc qua đi.');
  $this->get('/?wedding=linh-khoa')->assertOk()->assertSee('theme-romance');
  $this->get('/?wedding=missing')->assertNotFound();
 }
 public function test_drafts_are_private_and_html_is_sandboxed(): void {
  saveRecord('page',['title'=>'Hidden draft','slug'=>'hidden','body'=>'Private text','published'=>false]);
  $this->get('/?page=hidden')->assertNotFound()->assertDontSee('Private text');
  saveRecord('page',['title'=>'HTML page','slug'=>'html','mode'=>'html','body'=>'<h2>Custom content</h2><script>alert(1)</script>','published'=>true]);
  $this->get('/?page=html')->assertOk()->assertSee('sandbox=""',false)->assertDontSee('<script>alert(1)</script>',false);
 }
 public function test_guest_cannot_modify_content(): void {
  $before=Content::count();$this->post('/admin?section=event',['action'=>'delete','id'=>1])->assertForbidden();$this->assertSame($before,Content::count());
  $this->get('/admin?section=lead')->assertOk()->assertDontSee('STUDIO / CONTENT MANAGEMENT');
 }
 public function test_remote_visitors_cannot_create_first_admin(): void {
  $this->withServerVariables(['REMOTE_ADDR'=>'203.0.113.10'])->post('/admin',['action'=>'setup','username'=>'unwanted','password'=>'long-password-123'])->assertForbidden();
  $this->assertDatabaseCount('users',0);
 }
 public function test_admin_crud_and_unique_slugs(): void {
  $this->actingAs(User::factory()->create());
  $page=['action'=>'save','title'=>'Our studio','slug'=>'our-studio','body'=>'A lovely studio','mode'=>'theme','theme'=>'minimal','published'=>'on'];
  $this->post('/admin?section=page',$page)->assertRedirect('/admin?section=page');
  $this->get('/?page=our-studio')->assertOk()->assertSee('A lovely studio')->assertSee('theme-minimal');
  $this->from('/admin?section=page&new=1')->post('/admin?section=page',$page)->assertSessionHasErrors('slug');
  $id=records('page')[0]['id'];$page['id']=$id;$page['body']='Updated story';
  $this->post('/admin?section=page',$page)->assertRedirect();$this->get('/?page=our-studio')->assertSee('Updated story');
  $this->post('/admin?section=page',['action'=>'delete','id'=>$id])->assertRedirect();$this->get('/?page=our-studio')->assertNotFound();
 }
 public function test_admin_menus_languages_and_translation(): void {
  $this->actingAs(User::factory()->create());
  $this->post('/admin?section=languages',['action'=>'language','code'=>'fr','name'=>'Français'])->assertRedirect();
  $this->post('/admin?section=translations',['action'=>'translation','key'=>'hero.title','translation'=>['vi'=>'Ngày đẹp của bạn','en'=>'Your beautiful day','fr'=>'Votre beau jour']])->assertRedirect();
  $this->get('/?lang=fr')->assertSee('Votre beau jour')->assertSee('Cảm xúc ở lại.');
  $this->post('/admin?section=menu',['action'=>'save','label'=>'New page','url'=>'/?page=hello','position'=>'9','published'=>'on'])->assertRedirect();
  $this->get('/?lang=fr')->assertSee('/?page=hello&amp;lang=fr',false);
  $this->from('/admin')->post('/admin?section=menu',['action'=>'save','label'=>'Unsafe','url'=>'javascript:alert(1)'])->assertSessionHasErrors('url');
 }
 public function test_all_admin_screens_render(): void {
  $this->actingAs(User::factory()->create());
  foreach(array_keys(\App\Http\Controllers\AdminController::SECTIONS) as $section)$this->get('/admin?section='.$section)->assertOk();
  foreach(['event','page','menu'] as $section)$this->get('/admin?section='.$section.'&new=1')->assertOk();
 }
 public function test_contact_persists_without_smtp(): void {
  $this->post('/?lang=vi',['name'=>'Test Visitor','email'=>'visitor@example.com','message'=>'Xin tư vấn tổ chức đám cưới.'])->assertRedirect('/?lang=vi#contact');
  $leads=records('lead');$this->assertCount(1,$leads);$this->assertSame('not_configured',$leads[0]['mail']);
 }
 public function test_smtp_failure_preserves_enquiry(): void {
  config(['mail.default'=>'smtp','studio.mail_to'=>'studio@example.com']);Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Offline'));
  $this->post('/', ['name'=>'Test Visitor','email'=>'visitor@example.com','message'=>'A valid enquiry for our wedding.'])->assertRedirect();
  $this->assertSame('failed',records('lead')[0]['mail']);
 }
 public function test_contact_validation_honeypot_and_throttle(): void {
  $this->post('/',['name'=>'Test','email'=>'invalid','message'=>'short'])->assertSessionHasErrors(['email','message']);
  $this->post('/',['website'=>'bot'])->assertRedirect();$this->assertCount(0,records('lead'));
  $payload=['name'=>'Test','email'=>'visitor@example.com','message'=>'A valid wedding enquiry.'];
  for($i=0;$i<5;$i++)$this->post('/',$payload)->assertRedirect();
  $this->post('/',$payload)->assertSessionHasErrors('message');$this->assertCount(5,records('lead'));
 }
 public function test_event_image_upload_and_invalid_file_rejection(): void {
  Storage::fake('public_direct');$this->actingAs(User::factory()->create());
  // A real tiny PNG avoids requiring GD just to create a fixture.
  $bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aGZkAAAAASUVORK5CYII=');
  $data=['action'=>'save','title'=>'New Couple','slug'=>'new-couple','category'=>'Garden','theme'=>'editorial','published'=>'on','cover_upload'=>UploadedFile::fake()->createWithContent('cover.png',$bytes)];
  $this->post('/admin?section=event',$data)->assertSessionHasNoErrors()->assertRedirect();
  $event=records('event')[0];Storage::disk('public_direct')->assertExists(ltrim($event['cover'],'/'));
  $this->get('/?wedding=new-couple')->assertOk()->assertSee('New Couple');
  $data['slug']='bad-file';$data['cover_upload']=UploadedFile::fake()->createWithContent('payload.php','<?php echo 1;');
  $this->post('/admin?section=event',$data)->assertSessionHasErrors('cover_upload');
 }
 public function test_login_rejects_wrong_password_and_accepts_valid_one(): void {
  User::factory()->create(['name'=>'studio','password'=>bcrypt('valid-password-123')]);
  $this->post('/admin',['action'=>'login','username'=>'studio','password'=>'bad'])->assertSessionHasErrors();$this->assertGuest();
  $this->post('/admin',['action'=>'login','username'=>'studio','password'=>'valid-password-123'])->assertRedirect('/admin');$this->assertAuthenticated();
  $this->post('/admin',['action'=>'logout'])->assertRedirect('/admin');$this->assertGuest();
 }
}
