<?php
/** Entorno CLI separado de las sesiones de Apache. */
if(PHP_SAPI!=='cli') exit;
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../core/PdfReport.php';
$lines=['Ruta #1 | Fecha programada: 2026-11-18','Encuestador: Cuenta de prueba','Punto de entrega: Oficina principal','Entrega: Pendiente',''];
for($i=1;$i<=20;$i++) {
 $lines[]="$i. SOL-20261117-".sprintf('%04d',$i).' | Persona de prueba';
 $lines[]='Celular: 0000000000 | Dirección sintética de prueba';
 $lines[]='Sector de prueba / Comuna 17 | 3.4516000, -76.5320000';
 $lines[]='Confirmación: __________ Visita: __________ Observación: __________________';$lines[]='';
}
file_put_contents($argv[1],PdfReport::build('Hoja de ruta',$lines));
