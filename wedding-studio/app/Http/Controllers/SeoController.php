<?php
namespace App\Http\Controllers;
use App\Services\Seo;
use Illuminate\Http\Request;
class SeoController extends Controller {
 public function robots(){return response("User-agent: *\nAllow: /\nDisallow: /admin\nSitemap: ".Seo::absolute('/sitemap.xml')."\n",200,['Content-Type'=>'text/plain; charset=UTF-8']);}
 public function sitemap(Request $request){
  $entries=[];
  if(setting('seo_allow_indexing',false)){
   foreach(['home','blog'] as $type)foreach(Seo::locales(null,$type) as $locale)$entries[]=['url'=>Seo::absolute(Seo::localizedUrl($type==='home'?'/':'/blog',$locale))];
   foreach(['event','page','post'] as $type)foreach(records($type) as $item){
    if(!Seo::indexable($item,$type))continue;
    foreach(Seo::locales($item,$type) as $locale)$entries[]=['url'=>Seo::absolute(Seo::localizedUrl(Seo::path($type,$item['slug']),$locale)),'modified'=>$item['_updated_at'],'image'=>!empty($item['cover'])?Seo::absolute($item['cover']):''];
   }
  }
  $xml='<?xml version="1.0" encoding="UTF-8"?>';$escape=fn($s)=>htmlspecialchars($s,ENT_XML1|ENT_QUOTES,'UTF-8');
  if(count($entries)>5000&&!$request->has('part')){$xml.='<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';for($i=1;$i<=ceil(count($entries)/5000);$i++)$xml.='<sitemap><loc>'.$escape(Seo::absolute('/sitemap.xml?part='.$i)).'</loc></sitemap>';$xml.='</sitemapindex>';}
  else{$part=max(1,(int)$request->query('part',1));abort_if($part>max(1,ceil(count($entries)/5000)),404);$xml.='<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">';foreach(array_slice($entries,($part-1)*5000,5000) as $entry){$xml.='<url><loc>'.$escape($entry['url']).'</loc>';if(isset($entry['modified']))$xml.='<lastmod>'.$escape($entry['modified']).'</lastmod>';if(!empty($entry['image']))$xml.='<image:image><image:loc>'.$escape($entry['image']).'</image:loc></image:image>';$xml.='</url>';}$xml.='</urlset>';}
  return response($xml,200,['Content-Type'=>'application/xml; charset=UTF-8']);
 }
}
