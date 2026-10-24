<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\SeoRedirect;
use App\Models\SeoNotFound;
use App\Services\Seo;
use Database\Seeders\StudioSeeder;
use Database\Seeders\SeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class SeoTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();$this->seed([StudioSeeder::class,SeoSeeder::class]);config(['app.url'=>'https://weddings.example.com']);}
 private function article(array $extra=[]): array {
  $data=array_replace(['title'=>'Kinh nghiệm tổ chức đám cưới','slug'=>'kinh-nghiem','body'=>"## Chuẩn bị\n\nNội dung hướng dẫn cho ngày cưới. [Xem bộ sưu tập](/#stories).\n\n## Chuẩn bị\n\n![Không gian cưới](/uploads/sample.webp)",'excerpt'=>'Một hướng dẫn hữu ích.','category'=>'Kinh nghiệm','author'=>'Test Author','cover'=>'/uploads/sample.webp','cover_alt'=>'Không gian tiệc cưới','publish_at'=>'','published'=>true,'seo_title'=>'Kinh nghiệm tổ chức đám cưới đáng nhớ','seo_description'=>str_repeat('Mô tả hữu ích cho ngày cưới. ',5)],$extra);
  $record=saveRecord('post',$data);return ['id'=>$record->id]+$data;
 }
 public function test_clean_routes_canonical_share_tags_and_structured_data(): void {
  put('seo_allow_indexing',true);$this->article();$r=$this->get('/blog/kinh-nghiem?utm_source=test');$r->assertOk()->assertSee('rel="canonical" href="https://weddings.example.com/blog/kinh-nghiem"',false)->assertSee('property="og:type" content="article"',false)->assertSee('name="twitter:card" content="summary_large_image"',false)->assertHeader('X-Robots-Tag','index,follow,max-image-preview:large');
  preg_match('~<script type="application/ld\+json">(.*?)</script>~s',$r->getContent(),$match);$json=json_decode($match[1],true,512,JSON_THROW_ON_ERROR);$types=array_column($json['@graph'],'@type');$this->assertContains('BlogPosting',$types);$this->assertContains('BreadcrumbList',$types);$article=collect($json['@graph'])->firstWhere('@type','BlogPosting');$this->assertSame('Test Author',$article['author']['name']);$this->assertArrayHasKey('dateModified',$article);
 }
 public function test_only_actual_translations_get_hreflang(): void {
  put('seo_allow_indexing',true);$this->article(['body'=>'post.body']);$dict=setting('translations');$dict['vi']['post.body']='## Tiếng Việt';put('translations',$dict);
  $this->get('/blog/kinh-nghiem?lang=en')->assertSee('rel="canonical" href="https://weddings.example.com/blog/kinh-nghiem"',false)->assertDontSee('hreflang="en"',false)->assertHeader('X-Robots-Tag','noindex,follow,max-image-preview:large');
  $dict['en']['post.body']='## English story';put('translations',$dict);
  $this->get('/blog/kinh-nghiem?lang=en')->assertSee('rel="canonical" href="https://weddings.example.com/blog/kinh-nghiem?lang=en"',false)->assertSee('hreflang="en"',false)->assertSee('hreflang="x-default"',false)->assertHeader('X-Robots-Tag','index,follow,max-image-preview:large');
 }
 public function test_sitemap_excludes_drafts_scheduled_noindex_and_external_canonical(): void {
  put('seo_allow_indexing',true);$this->article();$this->article(['slug'=>'draft','published'=>false]);$this->article(['slug'=>'future','publish_at'=>now()->addDay()->toIso8601String()]);$this->article(['slug'=>'hidden','robots_noindex'=>true]);$this->article(['slug'=>'syndicated','canonical_url'=>'https://other.example.com/source']);
  $r=$this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type','application/xml; charset=UTF-8');$xml=simplexml_load_string($r->getContent());$this->assertNotFalse($xml);$r->assertSee('/blog/kinh-nghiem')->assertDontSee('/blog/draft')->assertDontSee('/blog/future')->assertDontSee('/blog/hidden')->assertDontSee('/blog/syndicated');$this->assertStringContainsString('<lastmod>',$r->getContent());
  put('seo_allow_indexing',false);$this->get('/sitemap.xml')->assertDontSee('<url>',false);$this->get('/')->assertHeader('X-Robots-Tag','noindex,follow,max-image-preview:large');
 }
 public function test_scheduled_posts_and_preview_are_protected(): void {
  $this->article(['published'=>false]);$this->article(['slug'=>'future','publish_at'=>now()->addDay()->toIso8601String()]);$this->get('/blog')->assertDontSee('Kinh nghiệm tổ chức đám cưới');$this->get('/blog/kinh-nghiem?preview=1')->assertNotFound();$this->get('/blog/future')->assertNotFound();
  $this->actingAs(User::factory()->create());$this->get('/blog/kinh-nghiem?preview=1')->assertOk()->assertSee('Bản xem trước')->assertHeader('Cache-Control','no-store, private')->assertHeader('X-Robots-Tag','noindex,follow,max-image-preview:large');
  $this->travel(2)->days();$this->get('/blog/future')->assertOk();
 }
 public function test_markdown_is_safe_and_heading_ids_are_unique(): void {
  $this->article(['body'=>"# Title inside body\n\n## Repeat\n\n## Repeat\n\n<script>alert('x')</script>\n\n[bad](javascript:alert(1))\n\n![Alt](/uploads/sample.webp)"]);
  $r=$this->get('/blog/kinh-nghiem')->assertOk()->assertSee('id="repeat"',false)->assertSee('id="repeat-2"',false)->assertSee('loading="lazy"',false)->assertDontSee('<script>alert',false)->assertDontSee('href="javascript:',false);
  $this->assertSame(1,substr_count($r->getContent(),'<h1'));
 }
 public function test_renaming_slug_creates_direct_301_and_legacy_query_redirect(): void {
  $this->actingAs(User::factory()->create());$record=$this->article();$payload=$record+['action'=>'save'];$payload['slug']='second';$payload['published']='on';$this->post('/admin?section=post',$payload)->assertSessionHasNoErrors();$payload['slug']='final';$this->post('/admin?section=post',$payload)->assertSessionHasNoErrors();
  $this->get('/blog/kinh-nghiem?lang=en')->assertStatus(301)->assertRedirect('/blog/final?lang=en');$this->get('/blog/second')->assertStatus(301)->assertRedirect('/blog/final');$this->get('/blog/final')->assertOk();
  $event=records('event')[0];$event['action']='save';$old=$event['slug'];$event['slug']='renamed-wedding';$event['published']='on';$this->post('/admin?section=event',$event)->assertSessionHasNoErrors();$this->get('/?wedding='.$old)->assertStatus(301)->assertRedirect('/weddings/renamed-wedding');
 }
 public function test_manual_redirects_flatten_and_reject_loops_and_external_targets(): void {
  $this->actingAs(User::factory()->create());
  foreach([['/old','/new'],['/new','/blog']] as [$source,$target])$this->post('/admin?section=redirects',['action'=>'redirect_save','source'=>$source,'target'=>$target,'status'=>301])->assertSessionHasNoErrors();
  $this->get('/old')->assertStatus(301)->assertRedirect('/blog');
  $this->post('/admin?section=redirects',['action'=>'redirect_save','source'=>'/new','target'=>'/old','status'=>301])->assertSessionHasErrors('redirect');
  foreach(['https://evil.example.com','//evil.example.com','/\\evil.example.com','/%2Fevil.example.com','/blog/../admin'] as $target)$this->post('/admin?section=redirects',['action'=>'redirect_save','source'=>'/unsafe','target'=>$target,'status'=>301])->assertSessionHasErrors('redirect');
 }
 public function test_404_log_omits_query_and_counts_hits(): void {
  $this->get('/not-found?secret=do-not-store')->assertNotFound()->assertHeader('X-Robots-Tag','noindex,follow,max-image-preview:large');$this->get('/not-found')->assertNotFound();$record=SeoNotFound::where('path','/not-found')->firstOrFail();$this->assertSame(2,$record->hits);$this->assertStringNotContainsString('secret',$record->path);
 }
 public function test_blog_pagination_categories_and_page_canonical(): void {
  for($i=0;$i<10;$i++)$this->article(['slug'=>'post-'.$i]);$this->get('/blog?p=2')->assertOk()->assertSee('rel="canonical" href="https://weddings.example.com/blog?p=2"',false);$this->get('/blog?p=3')->assertNotFound();$this->get('/blog?category=Kinh%20nghi%E1%BB%87m')->assertOk()->assertHeader('X-Robots-Tag','noindex,follow,max-image-preview:large');
 }
 public function test_admin_editors_and_global_seo_settings(): void {
  $this->actingAs(User::factory()->create());foreach(['post','page','event'] as $type)$this->get('/admin?section='.$type.'&new=1')->assertOk()->assertSee('SEO &amp; chia sẻ',false)->assertSee('name="seo_title"',false);
  $this->post('/admin?section=seo',['action'=>'seo_settings','seo_allow_indexing'=>'on','seo_home_title'=>'Our wedding studio','seo_home_description'=>'A custom description.','seo_verification_google'=>'test-verification'])->assertSessionHasNoErrors();$this->get('/')->assertSee('<title>Our wedding studio</title>',false)->assertSee('content="test-verification"',false)->assertHeader('X-Robots-Tag','index,follow,max-image-preview:large');
 }
 public function test_seeding_seo_is_idempotent_and_preserves_settings(): void {
  put('seo_home_title','Custom title');put('seo_allow_indexing',true);$before=count(records('menu'));$this->seed(SeoSeeder::class);$this->assertSame($before,count(records('menu')));$this->assertSame('Custom title',setting('seo_home_title'));$this->assertTrue(setting('seo_allow_indexing'));
 }
 public function test_malformed_language_or_content_queries_do_not_crash(): void {
  $this->get('/?lang[]=en')->assertOk();$this->get('/?wedding[]=invalid')->assertNotFound();$this->get('/blog?category[]=x')->assertOk();
 }
 public function test_explicit_noindex_and_canonical_are_respected(): void {
  put('seo_allow_indexing',true);$this->article(['robots_noindex'=>true,'robots_nofollow'=>true,'canonical_url'=>'https://example.org/original']);
  $this->get('/blog/kinh-nghiem')->assertHeader('X-Robots-Tag','noindex,nofollow,max-image-preview:large')->assertSee('rel="canonical" href="https://example.org/original"',false)->assertDontSee('hreflang=',false);
 }
}
