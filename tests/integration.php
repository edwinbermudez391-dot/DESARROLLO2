<?php
/** Pruebas sobre base efímera propia; nunca utilizan ni borran encuestas_cali. */
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
$name='encuestas_test_'.getmypid().'_'.bin2hex(random_bytes(3));
putenv('DB_NAME='.$name);
require_once __DIR__.'/../config/config.php';
$server=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function check(bool $condition,string $message): void {if(!$condition) throw new RuntimeException($message);echo "PASS $message\n";}
function rejects(callable $fn,int $status,string $label): void {
    try {$fn();} catch(HttpException $e) {check($e->getCode()===$status,$label);return;}throw new RuntimeException('Debió rechazar: '.$label);
}
try {
    $server->exec(str_replace('encuestas_cali',$name,file_get_contents(__DIR__.'/../DATABASE_MODELO_ENTREGA_1.sql')));
    $server->exec(str_replace('encuestas_cali',$name,file_get_contents(__DIR__.'/../SEEDERS_ENTREGA_1.sql')));
    require_once __DIR__.'/../scripts/create_demo_users.php';
    createDemoUsers($server,bin2hex(random_bytes(24)));
    require __DIR__.'/../scripts/migrate_entrega2.php';
    require __DIR__.'/../scripts/migrate_entrega2.php';
    require_once __DIR__.'/../app/Models/RequestModel.php';require_once __DIR__.'/../app/Models/OperationModel.php';
    require_once __DIR__.'/../core/PdfReport.php';
    $db=Database::getConnection();$requests=new RequestModel();$m=new OperationModel();$ids=[];
    $barrioId=(int)$db->query("SELECT id_barrio FROM barrios WHERE codigo_barrio='0101'")->fetchColumn();
    $data=['id_barrio'=>$barrioId,'nombres'=>'Prueba <img src=x onerror=alert(1)>','apellidos'=>'Integración','telefono'=>'0000000000','direccion'=>'Calle de prueba','barrio'=>'Sector de prueba','id_comuna'=>1,'id_sede'=>1,'latitud'=>3.45,'longitud'=>-76.53];
    $bad=$data;$bad['id_sede']=99999;check(!$requests->createSolicitud($bad,2)['success'],'rechaza sede inexistente');
    $bad=$data;$bad['longitud']=null;check(!$requests->createSolicitud($bad,2)['success'],'rechaza coordenadas incompletas');
    check((int)$db->query("SELECT COUNT(*) FROM comunas WHERE activo=1 AND tipo='Urbana'")->fetchColumn()===22,'22 comunas activas');
    check((int)$db->query("SELECT COUNT(*) FROM comunas WHERE activo=1 AND tipo='Corregimiento'")->fetchColumn()===15,'15 corregimientos activos');
    check((int)$db->query('SELECT COUNT(*) FROM barrios WHERE activo=1')->fetchColumn()===340,'340 barrios y sectores oficiales');
    $other=(int)$db->query("SELECT id_barrio FROM barrios WHERE codigo_barrio='0303'")->fetchColumn();
    $bad=$data;$bad['id_barrio']=$other;check(!$requests->createSolicitud($bad,2)['success'],'rechaza barrio de otra comuna');
    $bad=$data;unset($bad['id_barrio']);check(!$requests->createSolicitud($bad,2)['success'],'rechaza barrio urbano sin catalogo');
    $canonical=(new TerritoryModel())->validate($data);
    check($canonical['barrio']==='Terrón Colorado','normaliza el nombre desde el catalogo');
    $ruralId=(int)$db->query("SELECT id_comuna FROM comunas WHERE nombre_comuna='Pance' AND activo=1")->fetchColumn();
    $rural=(new TerritoryModel())->validate(['id_comuna'=>$ruralId,'barrio'=>'Vereda de prueba']);
    check($rural['corregimiento']==='Pance'&&$rural['id_barrio']===null,'acepta sector rural y deriva corregimiento');
    rejects(fn()=>$m->saveSite(1,['direccion'=>'Oficina de prueba','fuente_ubicacion'=>'Documento de prueba','latitud'=>200,'longitud'=>-76.5],1),400,'rechaza coordenadas de sede fuera de rango');
    $m->saveSite(1,['direccion'=>'Oficina de prueba','fuente_ubicacion'=>'Documento de prueba','latitud'=>3.39,'longitud'=>-76.53],1);
    require __DIR__.'/../scripts/migrate_territory.php';
    check((float)$db->query('SELECT latitud FROM sedes_atencion WHERE id_sede=1')->fetchColumn()===3.39,'migracion conserva sede configurada');
    check((int)$db->query("SELECT COUNT(*) FROM bitacora_auditoria WHERE accion='CONFIGURAR_SEDE'")->fetchColumn()===1,'configuracion de sede auditada');
    for($i=0;$i<20;$i++) {$r=$requests->createSolicitud($data,2);check($r['success'],'registro atómico '.($i+1));$ids[]=$r['data']['id_solicitud'];}
    check((int)$db->query('SELECT COUNT(DISTINCT numero_solicitud) FROM solicitudes')->fetchColumn()===20,'consecutivos únicos');
    $processes=[];
    for($i=0;$i<3;$i++) {
        $pipes=[];$process=proc_open([PHP_BINARY,'-d','display_errors=0',__DIR__.'/concurrent_request.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        $processes[]=[$process,$pipes];
    }
    $numbers=[];
    foreach($processes as [$process,$pipes]) {
        $numbers[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        check(proc_close($process)===0,'registro concurrente: '.$err);
    }
    check(count(array_unique($numbers))===3,'consecutivos concurrentes diferentes');
    check(count($m->catalogs()['sedes'])===8,'ocho sedes disponibles');
    check(count($m->pending())>=20,'solicitudes elegibles para ruta');
    $tomorrow=date('Y-m-d',strtotime('+1 day'));
    rejects(fn()=>$m->create(3,1,$tomorrow,array_slice($ids,0,19),5),400,'rechaza carga menor a 20');
    rejects(fn()=>$m->create(3,1,$tomorrow,array_fill(0,20,$ids[0]),5),400,'rechaza repetidos');
    rejects(fn()=>$m->create(3,1,date('Y-m-d'),$ids,5),400,'rechaza programación para hoy');
    rejects(fn()=>$m->create(2,1,$tomorrow,$ids,5),400,'rechaza usuario que no es encuestador');
    $id=$m->create(3,1,$tomorrow,$ids,5);$r=$m->route($id,3);
    check(count($r['solicitudes'])===20,'ruta con 20 asignaciones');
    check(count(array_filter($r['solicitudes'],fn($s)=>(int)$s['id_estado']===3))===20,'estado asignado consistente');
    rejects(fn()=>$m->route($id,999),404,'encuestador no puede consultar ruta ajena');
    rejects(fn()=>$m->create(3,1,date('Y-m-d',strtotime('+2 day')),$ids,5),409,'impide doble asignación');
    rejects(fn()=>$m->deliver($id,2,2),409,'rechaza entrega desde otra sede');
    rejects(fn()=>$m->deliver($id,1,2),409,'rechaza entrega anticipada');
    $db->prepare('UPDATE hojas_ruta SET fecha_visita=CURDATE() WHERE id_ruta=?')->execute([$id]);
    $afternoon=new OperationModel(new DateTimeImmutable('today 13:00'));
    rejects(fn()=>$afternoon->deliver($id,1,2),409,'rechaza entrega fuera de la manana');
    $morning=new OperationModel(new DateTimeImmutable('today 08:00'));
    $morning->deliver($id,1,2);check($m->route($id)['fecha_entrega']!==null,'entrega registrada en la manana');
    rejects(fn()=>$m->deliver($id,1,2),409,'impide entrega duplicada');
    check(!$requests->saveGeolocalizacion($ids[0],3.4,-76.5,2)['success'],'impide cambiar ubicación de solicitud asignada');
    check((int)$m->dashboard()['totales']['total']===23,'dashboard cuenta datos reales');
    $pdf=PdfReport::build('Informe de prueba',['Nombres con acentos: Cuenta de prueba',str_repeat('Texto largo ',50),...array_fill(0,90,'Fila de control')]);
    check(str_starts_with($pdf,'%PDF-1.4')&&str_contains($pdf,'/Count 3'),'PDF paginado');
    echo "Integración completada; datos de prueba eliminados al finalizar.\n";
} finally {
    if(!preg_match('/^encuestas_test_[0-9]+_[a-f0-9]{6}$/D',$name)) throw new RuntimeException('Nombre de prueba inválido');
    $server->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
