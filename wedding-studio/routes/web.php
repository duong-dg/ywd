<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\AdminController;
use App\Http\Middleware\StudioContext;
Route::middleware(StudioContext::class)->group(function(){
 Route::get('/',[SiteController::class,'index'])->name('home');
 Route::post('/',[SiteController::class,'contact'])->name('contact');
 Route::get('/admin',[AdminController::class,'index'])->name('admin');
 Route::post('/admin',[AdminController::class,'update'])->middleware('throttle:60,1');
});
