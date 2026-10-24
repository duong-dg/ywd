<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
class StudioSeeder extends Seeder { public function run(): void {
if(!setting('seeded',false)){
 put('brand','Vow & Verse');put('email','');put('phone','');put('address','');put('languages',['vi'=>'Tiếng Việt','en'=>'English']);
 put('translations',[
 'vi'=>['nav.home'=>'Trang chủ','nav.stories'=>'Chuyện tình yêu','nav.about'=>'Về chúng tôi','nav.contact'=>'Liên hệ','hero.eyebrow'=>'WEDDING JOURNAL & CREATIVE STUDIO','hero.title'=>'Khoảnh khắc qua đi.','hero.italic'=>'Cảm xúc ở lại.','hero.description'=>'Một không gian lưu giữ những ngày thật đẹp. Khám phá những câu chuyện được kể bằng tình yêu và từng chi tiết nhỏ.','hero.cta'=>'Khám phá những đám cưới','stories.eyebrow'=>'THE WEDDING COLLECTION','stories.title'=>'Mỗi tình yêu, một câu chuyện.','stories.description'=>'Những ý tưởng được chăm chút để trở thành kỷ niệm.','all'=>'Tất cả','discover'=>'Xem câu chuyện','about.title'=>'Đẹp trong từng chi tiết.','about.body'=>'Chúng tôi tin rằng một đám cưới đáng nhớ bắt đầu từ câu chuyện của hai người. Từ không gian, sắc hoa đến ánh sáng, mỗi chi tiết đều có một lý do để hiện diện.','contact.title'=>'Cùng viết nên ngày của bạn.','contact.body'=>'Chia sẻ điều bạn đang hình dung. Chúng tôi sẽ liên hệ để cùng bạn bắt đầu.','contact.name'=>'Tên của bạn','contact.email'=>'Email','contact.phone'=>'Số điện thoại','contact.message'=>'Bạn đang hình dung một đám cưới như thế nào?','contact.submit'=>'Gửi lời nhắn','footer.line'=>'Những ngày đẹp, những câu chuyện còn mãi.','footer.copyright'=>'All love stories deserve to be remembered.','demo.label'=>'Bộ sưu tập minh họa · Thay bằng dự án thực tế trong quản trị','event.one'=>'An & Minh','event.two'=>'Linh & Khoa','event.three'=>'Hà & Nam','event.story'=>'Một ngày ngập nắng, những sắc hoa dịu dàng và lời hẹn ước giữa những người thân yêu. Đây là nội dung minh họa, có thể thay bằng câu chuyện thực tế trong trang quản trị.','category.garden'=>'Garden wedding','category.destination'=>'Destination wedding','category.intimate'=>'Intimate wedding','back'=>'Về bộ sưu tập','empty'=>'Những câu chuyện mới đang được chuẩn bị.'],
 'en'=>['nav.home'=>'Home','nav.stories'=>'Love stories','nav.about'=>'Our studio','nav.contact'=>'Get in touch','hero.eyebrow'=>'WEDDING JOURNAL & CREATIVE STUDIO','hero.title'=>'Moments pass.','hero.italic'=>'Feelings stay.','hero.description'=>'A home for the days worth remembering. Discover stories told through love and the smallest details.','hero.cta'=>'Explore the weddings','stories.eyebrow'=>'THE WEDDING COLLECTION','stories.title'=>'Every love, its own story.','stories.description'=>'Thoughtful ideas, made into lasting memories.','all'=>'All','discover'=>'Read the story','about.title'=>'Beauty in every detail.','about.body'=>'We believe a memorable wedding begins with your story. From the setting and flowers to the light, every detail has a reason to be there.','contact.title'=>'Let’s write your day together.','contact.body'=>'Tell us what you have in mind. We will be in touch to begin.','contact.name'=>'Your name','contact.email'=>'Email','contact.phone'=>'Phone number','contact.message'=>'What does your dream wedding look like?','contact.submit'=>'Send a note','footer.line'=>'Beautiful days. Stories that stay.','footer.copyright'=>'All love stories deserve to be remembered.','demo.label'=>'Illustrative collection · Replace with real projects in admin','event.story'=>'A sunlit day, soft flowers, and a promise made among loved ones. This is sample content; replace it with your own wedding story in admin.','back'=>'Back to collection','empty'=>'New stories are on their way.']]);
 foreach([['nav.home','/'],['nav.stories','/#stories'],['nav.about','/#about'],['nav.contact','/#contact']] as $i=>$m)saveRecord('menu',['label'=>$m[0],'url'=>$m[1],'position'=>$i,'published'=>true]);
 foreach([
 ['an-minh','event.one','category.garden','Đà Lạt','2026-03-14','photo-1519741497674-611481863552','editorial'],
 ['linh-khoa','event.two','category.destination','Ninh Thuận','2026-04-18','photo-1519225421980-715cb0215aed','romance'],
 ['ha-nam','event.three','category.intimate','Sài Gòn','2026-05-09','photo-1511285560929-80b456fea0bc','minimal']
 ] as $a)saveRecord('event',['slug'=>$a[0],'title'=>$a[1],'category'=>$a[2],'location'=>$a[3],'date'=>$a[4],'cover'=>'https://images.unsplash.com/'.$a[5].'?auto=format&fit=crop&w=1800&q=85','story'=>'event.story','gallery'=>'','theme'=>$a[6],'published'=>true,'demo'=>true]);
 put('seeded',true);
}

$dict=setting('translations',[]);
$extra=[
 'vi'=>['site.descriptor'=>'WEDDINGS & STORIES','hero.tagline'=>'NGHỆ THUẬT KỂ CHUYỆN TÌNH YÊU','hero.scroll'=>'KHÁM PHÁ TIẾP ↓','hero.caption'=>'MỘT KHỞI ĐẦU THẬT ĐẸP','marquee.one'=>'TÌNH YÊU TRONG TỪNG CHI TIẾT','marquee.two'=>'NHỮNG CÂU CHUYỆN ĐÁNG NHỚ','about.eyebrow'=>'ĐIỀU CHÚNG TÔI TIN','contact.eyebrow'=>'CÂU CHUYỆN BẮT ĐẦU TỪ ĐÂY','event.subtitle'=>'Một ngày để nhớ.','error.not_found'=>'Trang không tồn tại'],
 'en'=>['site.descriptor'=>'WEDDINGS & STORIES','hero.tagline'=>'THE ART OF CELEBRATING LOVE','hero.scroll'=>'SCROLL TO EXPLORE ↓','hero.caption'=>'A BEAUTIFUL BEGINNING','marquee.one'=>'LOVE, IN EVERY DETAIL','marquee.two'=>'STORIES WORTH KEEPING','about.eyebrow'=>'OUR PHILOSOPHY','contact.eyebrow'=>'YOUR STORY STARTS HERE','event.subtitle'=>'A day to remember.','error.not_found'=>'Page not found']
];
foreach($extra as $locale=>$values)$dict[$locale]=($dict[$locale]??[])+$values;
put('translations',$dict);
if(!setting('demo_gallery_seeded',false)){
 $photos=array_map(fn($id)=>'https://images.unsplash.com/'.$id.'?auto=format&fit=crop&w=1400&q=80',['photo-1519741497674-611481863552','photo-1519225421980-715cb0215aed','photo-1511285560929-80b456fea0bc']);
 foreach(records('event') as $r)if(!empty($r['demo'])&&empty($r['gallery'])){$r['gallery']=implode("\n",$photos);saveRecord('event',$r,$r['id']);}
 put('demo_gallery_seeded',true);
}
}}
