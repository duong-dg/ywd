<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class SeoSeeder extends Seeder {
 public function run(): void {
  $dict=setting('translations',[]);$values=[
   'vi'=>['nav.blog'=>'Blog & cảm hứng','blog.title'=>'Chuyện cưới & cảm hứng','blog.description'=>'Những ý tưởng, kinh nghiệm và góc nhìn để chuẩn bị một ngày thật đáng nhớ.','blog.categories'=>'Chuyên mục','blog.read'=>'Đọc bài viết','blog.empty'=>'Những câu chuyện mới đang được viết.','blog.empty_description'=>'Ghé lại sớm để tìm cảm hứng cho ngày của bạn.','blog.minutes'=>'phút đọc','blog.contents'=>'Trong bài viết','blog.related'=>'ĐỌC THÊM CÙNG CHUYÊN MỤC','blog.preview'=>'Bản xem trước · Nội dung chưa xuất bản hoặc chưa đến lịch, không lập chỉ mục.'],
   'en'=>['nav.blog'=>'Journal','blog.title'=>'Stories & inspiration','blog.description'=>'Ideas, practical guides, and thoughtful perspectives for a day worth remembering.','blog.categories'=>'Categories','blog.read'=>'Read the story','blog.empty'=>'New stories are being written.','blog.empty_description'=>'Visit again soon for inspiration for your day.','blog.minutes'=>'min read','blog.contents'=>'In this story','blog.related'=>'MORE FROM THIS CATEGORY','blog.preview'=>'Preview · Unpublished or scheduled content, excluded from indexing.']
  ];foreach($values as $locale=>$items)$dict[$locale]=($dict[$locale]??[])+$items;put('translations',$dict);
  if(setting('seo_allow_indexing',null)===null)put('seo_allow_indexing',false);
  if(!array_filter(records('menu'),fn($r)=>($r['url']??'')==='/blog'))saveRecord('menu',['label'=>'nav.blog','url'=>'/blog','placement'=>'header','position'=>50,'published'=>true]);
 }
}
