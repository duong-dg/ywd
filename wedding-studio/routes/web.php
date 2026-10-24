<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\AdminController;
use App\Http\Middleware\StudioContext;
use App\Http\Middleware\SeoRouting;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\CustomAssetController;
Route::middleware([StudioContext::class,SeoRouting::class])->group(function(){
 Route::get('/',[SiteController::class,'index'])->name('home');
 Route::post('/',[SiteController::class,'contact'])->name('contact');
 Route::get('/blog',[SiteController::class,'index'])->defaults('kind','blog')->name('blog');
 Route::get('/blog/{slug}',[SiteController::class,'index'])->defaults('kind','post')->name('post');
 Route::get('/weddings/{slug}',[SiteController::class,'index'])->defaults('kind','event')->name('wedding');
 Route::get('/pages/{slug}',[SiteController::class,'index'])->defaults('kind','page')->name('page');
 Route::get('/sitemap.xml',[SeoController::class,'sitemap']);
 Route::get('/robots.txt',[SeoController::class,'robots']);
 Route::get('/custom-assets/{id}.{ext}',[CustomAssetController::class,'show'])->whereNumber('id')->whereIn('ext',['css','js'])->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,\Illuminate\Session\Middleware\StartSession::class,\Illuminate\View\Middleware\ShareErrorsFromSession::class]);
 Route::get('/admin',[AdminController::class,'index'])->name('admin');
 Route::post('/admin',[AdminController::class,'update'])->middleware('throttle:60,1');
 Route::fallback([SiteController::class,'index'])->defaults('kind','missing');
});
