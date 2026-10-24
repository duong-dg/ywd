<?php
namespace App\Support;
use App\Models\SiteSetting;
class SettingBag
{
 private ?array $values=null;
 public function get(string $key,$default=''){
  $this->values ??= SiteSetting::all()->mapWithKeys(fn($row)=>[$row->key=>$row->value])->all();
  return $this->values[$key]??$default;
 }
 public function clear(): void {$this->values=null;}
}
