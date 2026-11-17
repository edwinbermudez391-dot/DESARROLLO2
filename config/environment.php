<?php
/** Configuración privada opcional. Las variables ya definidas por el proceso tienen prioridad. */
declare(strict_types=1);
$localEnv=__DIR__.'/../.env';
if(is_file($localEnv)){
    foreach(file($localEnv,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line){
        if(str_starts_with(trim($line),'#')||!str_contains($line,'='))continue;
        [$key,$value]=explode('=',$line,2);
        if(preg_match('/^[A-Z][A-Z0-9_]*$/D',$key)&&getenv($key)===false)putenv($key.'='.$value);
    }
}
