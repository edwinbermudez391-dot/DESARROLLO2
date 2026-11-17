<?php
/** Auxiliar de la prueba concurrente, restringido a la base temporal. */
declare(strict_types=1);
if(PHP_SAPI!=='cli' || !preg_match('/^encuestas_test_[0-9]+_[a-f0-9]{6}$/D',getenv('DB_NAME')?:'')) exit(1);
require_once __DIR__.'/../app/Models/RequestModel.php';
$r=(new RequestModel())->createSolicitud(['id_barrio'=>(int)Database::getConnection()->query("SELECT id_barrio FROM barrios WHERE codigo_barrio='0101'")->fetchColumn(),'nombres'=>'Concurrente','apellidos'=>'Prueba','telefono'=>'0000000000','direccion'=>'Calle de prueba','barrio'=>'Sector','id_comuna'=>1,'id_sede'=>1,'latitud'=>3.45,'longitud'=>-76.53],2);
if(!$r['success']) {fwrite(STDERR,json_encode($r));exit(1);}echo $r['data']['numero_solicitud'];
