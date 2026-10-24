<?php
namespace App\Services;
use App\Models\Content;
use Illuminate\Support\Str;
use Carbon\Carbon;
class Seo
{
 public const FIELDS=['seo_title'=>'Tiêu đề SEO / khóa dịch','seo_description'=>'Mô tả SEO / khóa dịch','focus_keyword'=>'Từ khóa trọng tâm / khóa dịch','og_image'=>'Ảnh chia sẻ (URL, để trống dùng ảnh bìa)','canonical_url'=>'Canonical tùy chỉnh (URL tuyệt đối, thường để trống)','robots_noindex'=>'Không lập chỉ mục trang này (noindex)','robots_nofollow'=>'Không theo liên kết (nofollow)'];
 public static function path(string $type,string $slug=''): string {
  return match($type){'event'=>'/weddings/'.$slug,'page'=>'/pages/'.$slug,'blog'=>'/blog','post'=>$slug===''?'/blog':'/blog/'.$slug,default=>'/'};
 }
 public static function absolute(string $path): string {
  if(preg_match('~^https?://~i',$path))return $path;
  return rtrim(config('app.url'),'/').'/'.ltrim($path,'/');
 }
 public static function localPath(string $value,bool $allowQuery=true): bool {
  $decoded=rawurldecode($value);
  return str_starts_with($decoded,'/')&&!str_starts_with($decoded,'//')&&!preg_match('/[\x00-\x20\\\\#]/',$decoded)&&!preg_match('~/(\.|\.\.)(/|$)~',explode('?',$decoded,2)[0])&&($allowQuery||!str_contains($decoded,'?'));
 }
 public static function localizedUrl(string $path,string $locale='vi',array $query=[]): string {
  if($locale!=='vi')$query=['lang'=>$locale]+$query;
  return $path.($query?'?'.http_build_query($query):'');
 }
 public static function visible(array $record): bool {
  if(empty($record['published']))return false;
  if(!empty($record['publish_at'])){try{if(Carbon::parse($record['publish_at'])->isFuture())return false;}catch(\Throwable){return false;}}
  return true;
 }
 public static function locales(?array $item,string $type='home'): array {
  $dict=setting('translations',[]);$key=match($type){'event'=>$item['story']??'','page'=>$item['body']??'','post'=>$item['body']??'','blog'=>'blog.title',default=>'hero.title'};
  $locales=['vi'];foreach(setting('languages',['vi'=>'Tiếng Việt']) as $code=>$name){if($code==='vi')continue;if(isset($dict[$code][$key])&&trim($dict[$code][$key])!=='')$locales[]=$code;}
  return $locales;
 }
 public static function text(string $value): string {return trim(preg_replace('/\s+/u',' ',strip_tags($value)));}
 public static function markdown(string $body): array {
  $html=Str::markdown($body,['html_input'=>'strip','allow_unsafe_links'=>false]);
  $dom=new \DOMDocument();$previous=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="utf-8"?><div id="article-root">'.$html.'</div>',LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);libxml_clear_errors();libxml_use_internal_errors($previous);
  $toc=[];$used=[];$xpath=new \DOMXPath($dom);
  foreach(iterator_to_array($xpath->query('//h1|//h2|//h3')) as $node){
   if($node->nodeName==='h1'){$replacement=$dom->createElement('h2');while($node->firstChild)$replacement->appendChild($node->firstChild);$node->parentNode->replaceChild($replacement,$node);$node=$replacement;}
   $base=Str::slug($node->textContent)?:'section';$id=$base;$n=2;while(isset($used[$id]))$id=$base.'-'.$n++;$used[$id]=true;$node->setAttribute('id',$id);$toc[]=['id'=>$id,'label'=>$node->textContent,'level'=>$node->nodeName];
  }
  foreach($xpath->query('//img') as $img){$img->setAttribute('loading','lazy');$img->setAttribute('decoding','async');$src=$img->getAttribute('src');if(preg_match('~^/uploads/[a-zA-Z0-9_.-]+$~',$src)&&is_file(public_path($src))){$size=@getimagesize(public_path($src));if($size){$img->setAttribute('width',(string)$size[0]);$img->setAttribute('height',(string)$size[1]);}}}
  foreach($xpath->query('//a') as $a){$href=$a->getAttribute('href');if(str_starts_with($href,'http')&&parse_url($href,PHP_URL_HOST)!==parse_url(config('app.url'),PHP_URL_HOST))$a->setAttribute('rel','noopener');}
  $root=$dom->getElementById('article-root');$output='';if($root)foreach($root->childNodes as $child)$output.=$dom->saveHTML($child);
  return ['html'=>$output,'toc'=>$toc];
 }
 public static function metadata(?array $item,string $type,string $path,bool $missing=false,bool $preview=false,array $query=[]): array {
  $locale=app()->getLocale();$brand=setting('brand','Wedding Studio');$locales=self::locales($item,$type);$isTranslated=in_array($locale,$locales,true);
  $fallbackTitle=match($type){'home'=>t(setting('seo_home_title')?:'hero.title').' '.t('hero.italic'),'blog'=>t(setting('seo_blog_title')?:'blog.title'),default=>$item?field($item,'title'):$brand};
  $customTitle=$item['seo_title']??($type==='home'?setting('seo_home_title'):($type==='blog'?setting('seo_blog_title'):''));
  $title=$customTitle?t($customTitle):$fallbackTitle.' · '.$brand;
  $defaultDescription=match($type){'home'=>t(setting('seo_home_description')?:'hero.description'),'blog'=>t(setting('seo_blog_description')?:'blog.description'),'event'=>field($item??[],'story'),'post'=>field($item??[],'excerpt')?:self::text(self::markdown(field($item??[],'body'))['html']),default=>self::text(field($item??[],'body'))};
  $description=!empty($item['seo_description'])?field($item,'seo_description'):Str::limit(self::text($defaultDescription),160,'…');
  $self=self::absolute(self::localizedUrl($path,$locale,$query));
  $canonical=!empty($item['canonical_url'])?$item['canonical_url']:self::absolute(self::localizedUrl($path,$isTranslated?$locale:'vi',$query));
  $noindex=$missing||$preview||!setting('seo_allow_indexing',false)||!empty($item['robots_noindex'])||!$isTranslated||request()->filled('category');
  if($missing){$title=t('error.not_found').' · '.$brand;$description='';$canonical='';}
  $image=$item['og_image']??'';$image=$image?:($item['cover']??'');$image=$image?:setting('seo_share_image');$image=$image?self::absolute($image):'';
  $alternates=[];if(!$missing&&!$preview&&empty($item['robots_noindex'])&&(empty($item['canonical_url'])||$canonical===$self))foreach($locales as $code)$alternates[$code]=self::absolute(self::localizedUrl($path,$code,$query));
  $robots=($noindex?'noindex':'index').','.(!empty($item['robots_nofollow'])?'nofollow':'follow').',max-image-preview:large';
  $graph=[];$orgId=self::absolute('/').'#organization';$websiteId=self::absolute('/').'#website';
  if(!$missing){
   $org=['@type'=>'Organization','@id'=>$orgId,'name'=>$brand,'url'=>self::absolute('/')];
   if(setting('seo_org_logo'))$org['logo']=self::absolute(setting('seo_org_logo'));
   $social=array_values(array_filter(array_map('trim',explode("\n",setting('seo_social_urls')))));if($social)$org['sameAs']=$social;
   $graph[]=$org;$graph[]=['@type'=>'WebSite','@id'=>$websiteId,'url'=>self::absolute('/'),'name'=>$brand,'publisher'=>['@id'=>$orgId]];
   $graph[]=['@type'=>$type==='blog'?'CollectionPage':'WebPage','@id'=>$self.'#webpage','url'=>$self,'name'=>$title,'description'=>$description,'inLanguage'=>$isTranslated?$locale:'vi','isPartOf'=>['@id'=>$websiteId]];
   if($type!=='home'){
    $crumbs=[['@type'=>'ListItem','position'=>1,'name'=>t('nav.home'),'item'=>self::absolute(self::localizedUrl('/',$locale))]];
    if($type==='post')$crumbs[]=['@type'=>'ListItem','position'=>2,'name'=>t('blog.title'),'item'=>self::absolute(self::localizedUrl('/blog',$locale))];
    $crumbs[]=['@type'=>'ListItem','position'=>count($crumbs)+1,'name'=>$type==='blog'?t('blog.title'):field($item??[],'title'),'item'=>$self];$graph[]=['@type'=>'BreadcrumbList','itemListElement'=>$crumbs];
   }
   if($type==='post'&&$item){
    $article=['@type'=>'BlogPosting','headline'=>field($item,'title'),'description'=>$description,'mainEntityOfPage'=>['@id'=>$self.'#webpage'],'datePublished'=>Carbon::parse(($item['publish_at']??'')?:($item['_created_at']??'now'))->toIso8601String(),'dateModified'=>Carbon::parse($item['_updated_at']??'now')->toIso8601String(),'publisher'=>['@id'=>$orgId],'inLanguage'=>$isTranslated?$locale:'vi'];
    $article['author']=!empty($item['author'])?['@type'=>'Person','name'=>field($item,'author')]:['@id'=>$orgId];if($image)$article['image']=[$image];$graph[]=$article;
   }
  }
  return compact('title','description','canonical','image','alternates','robots','graph','noindex','self');
 }
 public static function report(array $item,string $type): array {
  $title=field($item,'seo_title')?:field($item,'title');$description=field($item,'seo_description');$body=field($item,$type==='event'?'story':'body');$html=$type==='post'?self::markdown($body)['html']:$body;$text=self::text($html);$keyword=mb_strtolower(field($item,'focus_keyword'));$len=mb_strlen($title);$descLen=mb_strlen($description);
  $checks=[['Tiêu đề SEO khoảng 30–65 ký tự', $len>=30&&$len<=65],['Mô tả riêng khoảng 120–165 ký tự',$descLen>=120&&$descLen<=165],['Đã đặt từ khóa trọng tâm',$keyword!==''],['Từ khóa trong tiêu đề',$keyword!==''&&str_contains(mb_strtolower($title),$keyword)],['Từ khóa trong đoạn mở đầu',$keyword!==''&&str_contains(mb_strtolower(mb_substr($text,0,400)),$keyword)],['Có ảnh bìa và mô tả alt',!empty($item['cover'])&&!empty($item['cover_alt'])]];
  $checks[]=['Từ khóa trong đường dẫn',$keyword!==''&&str_contains($item['slug']??'',Str::slug($keyword))];
  if($type==='post'){$checks[]=['Có heading H2',str_contains($html,'<h2')];preg_match_all('~<h[23][^>]*>(.*?)</h[23]>~s',$html,$headings);$checks[]=['Từ khóa trong heading',$keyword!==''&&str_contains(mb_strtolower(strip_tags(implode(' ',$headings[1]))),$keyword)];$checks[]=['Có liên kết trong bài',str_contains($html,'<a ')];$checks[]=['Ảnh trong bài có alt',!preg_match('/<img\b[^>]*alt=""/i',$html)];}
  return ['checks'=>$checks,'words'=>count(preg_split('/\s+/u',$text,-1,PREG_SPLIT_NO_EMPTY)),'title'=>$title,'description'=>$description];
 }
 public static function indexable(array $item,string $type): bool {
  return self::visible($item)&&empty($item['robots_noindex'])&&(empty($item['canonical_url'])||rtrim($item['canonical_url'],'/')===rtrim(self::absolute(self::path($type,$item['slug'])),'/'));
 }
}
