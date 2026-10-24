<title><?=e($seo['title'])?></title>
<meta name="description" content="<?=e($seo['description'])?>">
<meta name="robots" content="<?=e($seo['robots'])?>">
<?php if($seo['canonical']):?><link rel="canonical" href="<?=e($seo['canonical'])?>"><?php endif;?>
<?php foreach($seo['alternates'] as $code=>$href):?><link rel="alternate" hreflang="<?=e($code)?>" href="<?=e($href)?>"><?php endforeach;?>
<?php if(isset($seo['alternates']['vi'])):?><link rel="alternate" hreflang="x-default" href="<?=e($seo['alternates']['vi'])?>"><?php endif;?>
<meta property="og:type" content="<?=$post?'article':'website'?>">
<meta property="og:site_name" content="<?=e($brand)?>">
<meta property="og:title" content="<?=e($seo['title'])?>">
<meta property="og:description" content="<?=e($seo['description'])?>">
<meta property="og:url" content="<?=e($seo['canonical']?:$seo['self'])?>">
<meta property="og:locale" content="<?=e($lang==='vi'?'vi_VN':($lang==='en'?'en_US':str_replace('-','_',$lang)))?>">
<?php if($seo['image']):?><meta property="og:image" content="<?=e($seo['image'])?>"><meta property="og:image:alt" content="<?=e($post?field($post,'cover_alt'):($event?field($event,'cover_alt'):$seo['title']))?>"><?php endif;?>
<meta name="twitter:card" content="<?=$seo['image']?'summary_large_image':'summary'?>">
<meta name="twitter:title" content="<?=e($seo['title'])?>"><meta name="twitter:description" content="<?=e($seo['description'])?>">
<?php if($seo['image']):?><meta name="twitter:image" content="<?=e($seo['image'])?>"><?php endif;?>
<?php if(setting('seo_verification_google')):?><meta name="google-site-verification" content="<?=e(setting('seo_verification_google'))?>"><?php endif;?>
<?php if(setting('seo_verification_bing')):?><meta name="msvalidate.01" content="<?=e(setting('seo_verification_bing'))?>"><?php endif;?>
<?php if($seo['graph']):?><script type="application/ld+json"><?=json_encode(['@context'=>'https://schema.org','@graph'=>$seo['graph']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?></script><?php endif;?>
