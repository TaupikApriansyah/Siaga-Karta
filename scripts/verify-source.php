<?php
$root=dirname(__DIR__);
$errors=[];
$data=require $root.'/database/data/bandung_regions.php';
$districts=count($data['districts']);
$villages=array_sum(array_map(fn($d)=>count($d['villages']),$data['districts']));
$codes=[];
foreach($data['districts'] as $d){
    $codes[]=$d['code'];
    foreach($d['villages'] as [$suffix,$name]) $codes[]=$d['code'].'.'.$suffix;
}
if($districts!==30)$errors[]="Kecamatan={$districts}, expected 30";
if($villages!==151)$errors[]="Kelurahan={$villages}, expected 151";
if(count($codes)!==count(array_unique($codes)))$errors[]='Kode wilayah duplikat';
$validation=file_get_contents($root.'/lang/id/validation.php');
if(!str_contains($validation,"'string' => ':attribute minimal harus :min karakter.'"))$errors[]='Terjemahan validation.min.string belum tersedia';
$api=file_get_contents($root.'/routes/api.php');
foreach(['/exports/pelayanan.csv','/exports/pelayanan.pdf'] as $route) if(!str_contains($api,$route))$errors[]="Route hilang: {$route}";
$permissions=require $root.'/config/permissions.php';
foreach(['kecamatan','kelurahan'] as $role){
    foreach(['dashboard.view','operations.view','reports.input','regions.local.manage'] as $perm){
        if(!in_array($perm,$permissions['roles'][$role]??[],true))$errors[]="{$role} kehilangan {$perm}";
    }
}
if($errors){
    fwrite(STDERR,"SOURCE VERIFY FAILED\n- ".implode("\n- ",$errors)."\n");
    exit(1);
}
echo "SOURCE VERIFY OK: {$districts} kecamatan, {$villages} kelurahan, permission & validation checks passed.\n";
