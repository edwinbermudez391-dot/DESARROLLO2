<?php
/** Matriz de permisos de APIs. Peticiones de escritura sin datos: no crean registros. */
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit;
$endpoints=[
 'guardar-sede'=>['POST',[1],400], 'territorio'=>['GET',[1,2,3,4,5],200],
 'catalogos'=>['GET',[1,2,3,4,5],200], 'pendientes'=>['GET',[1,5],200],
 'rutas'=>['GET',[1,2,3,5],200], 'dashboard'=>['GET',[1,4,5],200],
 'reporte'=>['GET',[1],200], 'ruta-pdf'=>['GET',[1,2,3,5],400],
 'crear-ruta'=>['POST',[1,5],400], 'entregar-ruta'=>['POST',[1,2],400]
];
$total=0;
foreach([null,1,2,3,4,5] as $role) foreach($endpoints as $endpoint=>[$method,$allowed,$success]) {
 $status=$role===null?401:(in_array($role,$allowed,true)?$success:403);
 $code='require "core/Auth.php";new Auth();$_SESSION=[];';
 if($role!==null) $code.='$_SESSION=["logged_in"=>true,"user_id"=>3,"user_rol"=>'.$role.'];';
 $route=$endpoint==='territorio'?'api/territorio':'api/operacion/'.$endpoint;
 $code.='$_GET["route"]="'.$route.'";$_SERVER["REQUEST_METHOD"]="'.$method.'";ob_start();require "public/index.php";$body=ob_get_clean();$status=http_response_code();';
 $code.='$ok=$status==='.$status.';';
 if($status===200 && $endpoint==='reporte') $code.='$ok=$ok&&str_starts_with($body,"%PDF-");';
 else $code.='$ok=$ok&&is_array(json_decode($body,true));';
 $code.='$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_destroy();if(!$ok){fwrite(STDERR,"HTTP ".$status." ".$body);exit(1);}';
 $p=proc_open([PHP_BINARY,'-d','display_errors=0','-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
 $output=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
 if($exit!==0) throw new RuntimeException('rol='.($role??'anonimo').' endpoint='.$endpoint.' '.$err.$output);
 $total++;
}
echo "PASS $total combinaciones de permisos API, JSON y descarga PDF.\n";
