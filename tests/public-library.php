<?php
define('ABSPATH', '/tmp/'); define('ARRAY_A', 'ARRAY_A');
function __($s,$domain=null){return $s;} function current_time($s){return 'now';}
function netctrl_get_pdf_url($id){return '/pdf/'.$id;}
class FakeDB {
 public $prefix='wp_'; public $total=43;
 function prepare($sql,...$args){foreach($args as $v)$sql=preg_replace('/%[sd]/',is_int($v)?(string)$v:"'".$v."'",$sql,1);return $sql;}
 function get_var($sql){return $this->total;}
 function get_results($sql,$format){
  if(strpos($sql,'netctrl_entries')!==false)return [];
  if(strpos($sql,"'open'")!==false)return [['id'=>99,'status'=>'open']];
  if(!preg_match('/LIMIT (\d+) OFFSET (\d+)/',$sql,$m))throw new Exception('Unbounded archive query');
  if(strpos($sql,'id DESC')===false)throw new Exception('Missing stable tiebreaker');
  $rows=[];for($i=1;$i<=$this->total;$i++)$rows[]=['id'=>$i,'status'=>'closed'];return array_slice($rows,(int)$m[2],(int)$m[1]);
 }
}
$wpdb=new FakeDB(); require __DIR__.'/../netctrl/includes/db.php';require __DIR__.'/../netctrl/includes/helpers.php';
function check($x,$why){if(!$x)throw new Exception($why);}
$ids=[];foreach([1,2,3] as $p){$x=netctrl_get_public_sessions_payload(20,$p);check(count($x['open_sessions'])===1,'Live sessions missing');$ids=array_merge($ids,array_column($x['closed_sessions'],'id'));}
check(count($ids)===43 && count(array_unique($ids))===43,'Archive has missing or duplicate rows');
check(netctrl_get_public_sessions_payload(20,999)['pagination']['page']===3,'Page clamp');
check(netctrl_get_public_sessions_payload(20,-10)['pagination']['page']===1,'Negative page');
check(netctrl_get_public_sessions_payload(999,1)['pagination']['per_page']===50,'Unbounded page size');
$wpdb->total=0;check(netctrl_get_public_sessions_payload()['pagination']['pages']===1,'Empty library');
echo "PASS: full archive traversal, bounded pages, stable order, live sessions and empty library.\n";
