<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;

Artisan::command('studio:admin',function(){
 $name=$this->ask('Tên đăng nhập (ít nhất 3 ký tự)');$password=$this->secret('Mật khẩu (ít nhất 12 ký tự)');
 if(strlen($name??'')<3||strlen($password??'')<12){$this->error('Tên hoặc mật khẩu quá ngắn.');return 1;}
 $existing=User::first();if($existing&&!$this->confirm('Cập nhật tài khoản quản trị hiện tại?'))return 1;
 $user=$existing??new User(['email'=>'admin@studio.local']);$user->name=$name;$user->password=Hash::make($password);$user->save();$this->info('Đã lưu tài khoản quản trị.');
})->purpose('Tạo hoặc đặt lại tài khoản quản trị');
