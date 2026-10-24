<?php
use App\Models\Content;
use App\Models\SiteSetting;
function setting(string $key,$default='') { return app(\App\Support\SettingBag::class)->get($key,$default); }
function put(string $key,$value): void { SiteSetting::updateOrCreate(['key'=>$key],['value'=>$value]);app(\App\Support\SettingBag::class)->clear(); }
function records(string $type): array {return Content::where('type',$type)->latest('id')->get()->map(fn($r)=>['id'=>$r->id,'_created_at'=>$r->created_at->toIso8601String(),'_updated_at'=>$r->updated_at->toIso8601String()]+$r->data)->all();}
function saveRecord(string $type,array $data,int $id=0): Content {unset($data['id'],$data['_created_at'],$data['_updated_at']);$record=$id?Content::where('type',$type)->findOrFail($id):new Content(['type'=>$type]);$record->data=$data;$record->save();return $record;}
function csrf(): string {return csrf_token();}
function site_url(array $params=[]): string {
 foreach(['wedding'=>'event','page'=>'page','post'=>'post'] as $key=>$type)if(isset($params[$key])){$path=\App\Services\Seo::path($type,$params[$key]);unset($params[$key]);return \App\Services\Seo::localizedUrl($path,app()->getLocale(),$params);}
 return '/?'.http_build_query(['lang'=>app()->getLocale()]+$params);
}
function t(string $key): string {$dict=setting('translations',[]);return (string)($dict[app()->getLocale()][$key]??$dict['vi'][$key]??$key);}
function field(array $item,string $name): string {return t((string)($item[$name]??''));}
function safeUrl(string $url): bool {return !preg_match('/[\x00-\x20]/',$url)&&preg_match('~^(https?://|/(?!/)|#)~i',$url)===1;}
function adminUrl(string $section,array $extra=[]): string {return '/admin?'.http_build_query(['section'=>$section]+$extra);}
function menuUrl(string $href): string {
 if(!safeUrl($href))return '#';
 if(!str_starts_with($href,'/')||str_starts_with($href,'//'))return $href;
 $parts=explode('#',$href,2);return $parts[0].(str_contains($parts[0],'?')?'&':'?').'lang='.rawurlencode(app()->getLocale()).(isset($parts[1])?'#'.$parts[1]:'');
}
