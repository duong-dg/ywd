<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SeoRedirect extends Model {
 protected $guarded=[];
 protected function casts(): array {return ['status'=>'integer'];}
}
