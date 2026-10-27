<?php
/** Two independent PHP processes against a disposable MariaDB database. */
require dirname(__DIR__) . '/classes/TrustConfiguration.php';
define('_DB_PREFIX_', 'test_shop_'); define('_DB_NAME_', 'trust_test');
$dsn = getenv('MARCLAND_TEST_DSN');
if (!$dsn || strpos($dsn, 'dbname=trust_test') === false) { throw new RuntimeException('Use disposable trust_test only.'); }
$pdo = new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function pSQL($value, $html = false) {global $pdo;return substr($pdo->quote($value),1,-1);}
class RaceDb {
    public function execute($sql) {global $pdo;return $pdo->exec($sql)!==false;}
    public function executeS($sql,...$args) {global $pdo;return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);}
    public function getValue($sql,...$args) {global $pdo;return $pdo->query($sql)->fetchColumn();}
    public function Insert_ID() {global $pdo;return $pdo->lastInsertId();}
    public function insert($table,$data,...$args) {
        $sets=[];foreach($data as $key=>$value){$sets[]='`'.$key.'`='.($value===null?'NULL':"'".$value."'");}
        return $this->execute('INSERT INTO `'._DB_PREFIX_.$table.'` SET '.implode(',',$sets));
    }
}
$store=new MarclandTrustConfiguration(new RaceDb(),sys_get_temp_dir());
if (($argv[1]??'')==='--worker') {
    $worker=$argv[2];$barrier=$argv[3];
    $revision=MarclandTrustConfiguration::revision($store->load());
    $post=['trust_signals_present'=>'1','trust_payload_complete'=>'1','trust_revision'=>$revision,'trust_signals'=>[]];
    for($i=0;$i<3;$i++){$post['trust_signals'][]=[
        'id_zone'=>'','position'=>(string)($i+1),'active'=>$i===0?'0':'1','icon_type'=>'predefined','icon_name'=>'truck',
        'custom_icon_path'=>'','link_type'=>'none','link_target'=>'','upload_selected'=>'0','text'=>[17=>'Worker '.$worker,29=>''],
    ];}
    $configuration=MarclandTrustConfiguration::validate($post,[17,29],static function(){return false;});
    touch($barrier.'.'.$worker);$deadline=microtime(true)+10;
    while(!is_file($barrier)&&microtime(true)<$deadline){usleep(10000);}
    try{$store->save($configuration,[]);echo 'COMMITTED';}
    catch(InvalidArgumentException $expected){echo 'STALE';}
    exit;
}
if($store->load()['zones']!==[]){throw new RuntimeException('Requires empty disposable module tables.');}
$barrier=sys_get_temp_dir().'/trust-race-'.bin2hex(random_bytes(6));$workers=[];
try {
    foreach(['A','B'] as $id){
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--worker',$id,$barrier],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process)){throw new RuntimeException('Unable to launch race worker.');}
        fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $deadline=microtime(true)+10;
    while((!is_file($barrier.'.A')||!is_file($barrier.'.B'))&&microtime(true)<$deadline){usleep(10000);}
    if(!is_file($barrier.'.A')||!is_file($barrier.'.B')){throw new RuntimeException('Workers failed to reach barrier.');}
    touch($barrier);$results=[];
    foreach($workers as [$process,$pipes]){
        $results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0){throw new RuntimeException('Worker failed: '.$error);}
    }
    sort($results);$saved=$store->load();
    if($results!==['COMMITTED','STALE']||count($saved['zones'])!==3||count(array_unique(array_column($saved['translations'],'text')))!==1){throw new RuntimeException('Concurrent save integrity failure.');}
    echo "PASS: simultaneous three-zone saves: one committed, one stale; three coherent rows including an inactive zone\n";
} finally {
    foreach([$barrier,$barrier.'.A',$barrier.'.B'] as $file){@unlink($file);}
}
