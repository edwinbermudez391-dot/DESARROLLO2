<?php
/** Cuentas de demostración sin datos personales ni hashes publicados. No modifica cuentas existentes. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require_once __DIR__.'/../core/Database.php';
function createDemoUsers(PDO $db,string $password): void
{
    if(strlen($password)<16)throw new RuntimeException('DEMO_PASSWORD debe tener al menos 16 caracteres.');
    $names=[1=>'admin',2=>'ventanilla',3=>'encuestador',4=>'calidad',5=>'enrutador'];
    $db->beginTransaction();
    try {
        $find=$db->prepare('SELECT id_usuario FROM usuarios WHERE username=?');
        $insert=$db->prepare('INSERT INTO usuarios(id_rol,username,password_hash,nombres,apellidos,activo) VALUES(?,?,?,?,?,1)');
        foreach($names as $role=>$username){
            $find->execute([$username]);if($find->fetch())continue;
            $insert->execute([$role,$username,password_hash($password,PASSWORD_DEFAULT),'Cuenta de prueba',$username]);
        }
        $db->commit();
    }catch(Throwable $e){$db->rollBack();throw $e;}
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $db=Database::getConnection();
    $count=(int)$db->query("SELECT COUNT(*) FROM usuarios WHERE username IN ('admin','ventanilla','encuestador','calidad','enrutador')")->fetchColumn();
    if($count===5){echo "Las cuentas existentes se conservan.\n";return;}
    $password=getenv('DEMO_PASSWORD');
    if(!$password)throw new RuntimeException('Configura DEMO_PASSWORD localmente antes de crear cuentas.');
    createDemoUsers($db,$password);echo "Cuentas de demostración creadas; contraseña tomada del entorno local.\n";
}
