<?php
declare(strict_types=1);
require_once __DIR__.'/../../core/Controller.php';
require_once __DIR__.'/../../core/Auth.php';
require_once __DIR__.'/../Models/OperationModel.php';
require_once __DIR__.'/../../core/PdfReport.php';
class OperationController extends Controller
{
    private function auth(array $roles): Auth {$a=new Auth();$a->requireRole($roles);return $a;}
    private function positive($value): int {
        $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if ($id===false) throw new HttpException('Identificador inválido.',400);return $id;
    }
    public function catalogs(): void {
        $this->auth([1,2,3,4,5]);$data=(new OperationModel())->catalogs();
        // Los nombres de encuestadores son necesarios solo para planear cargas.
        if (!in_array((int)($_SESSION['user_rol']??0),[1,5],true)) $data['encuestadores']=[];
        $this->json(['success'=>true]+$data);
    }
    public function pending(): void {$this->auth([1,5]);$this->json(['success'=>true,'data'=>(new OperationModel())->pending()]);}
    public function routes(): void {
        $a=$this->auth([1,2,3,5]);$m=new OperationModel();$owner=$a->getUserRole()===3?$a->getUserId():null;
        $data=isset($_GET['id'])?$m->route($this->positive($_GET['id']),$owner):$m->routes($owner);
        $this->json(['success'=>true,'data'=>$data]);
    }
    public function create(): void {
        $a=$this->auth([1,5]);$d=$this->input();
        if (!isset($d['solicitudes']) || !is_array($d['solicitudes']) || !is_string($d['fecha_visita']??null)) throw new HttpException('Envía solicitudes y fecha de visita.',400);
        $ids=array_map(fn($v)=>$this->positive($v),$d['solicitudes']);
        $id=(new OperationModel())->create($this->positive($d['id_encuestador']??null),$this->positive($d['id_sede']??null),$d['fecha_visita'],$ids,(int)$a->getUserId());
        $this->json(['success'=>true,'id_ruta'=>$id],201);
    }
    public function deliver(): void {
        $a=$this->auth([1,2]);$d=$this->input();(new OperationModel())->deliver($this->positive($d['id_ruta']??null),$this->positive($d['id_sede']??null),(int)$a->getUserId());$this->json(['success'=>true]);
    }
    public function saveSite(): void {
        $a=$this->auth([1]);$data=$this->input();
        (new OperationModel())->saveSite($this->positive($data['id_sede']??null),$data,(int)$a->getUserId());
        $this->json(['success'=>true]);
    }
    public function dashboard(): void {$this->auth([1,4,5]);$this->json(['success'=>true,'data'=>(new OperationModel())->dashboard()]);}
    public function report(): void {
        $this->auth([1]);$data=(new OperationModel())->dashboard();$lines=['Resumen general de solicitudes',''];
        foreach(['total'=>'Total de solicitudes','hoy'=>'Radicadas hoy','vencidas'=>'Vencidas sin cierre','por_vencer'=>'Vencen en los próximos 3 días'] as $k=>$label) $lines[]=$label.': '.$data['totales'][$k];
        $lines[]='';$lines[]='Distribución por estado';foreach($data['estados'] as $r) $lines[]=$r['nombre_estado'].': '.$r['total'];
        $lines[]='';$lines[]='Radicaciones por sede';foreach($data['sedes'] as $r) $lines[]=$r['nombre_sede'].': '.$r['total'];
        $lines[]='';$lines[]='Hojas de ruta: '.$data['rutas']['total'].' | Entregadas: '.$data['rutas']['entregadas'];
        PdfReport::download('informe-solicitudes.pdf','Informe de gestión',$lines);
    }
    public function routePdf(): void {
        $a=$this->auth([1,2,3,5]);$r=(new OperationModel())->route($this->positive($_GET['id']??null),$a->getUserRole()===3?$a->getUserId():null);
        $lines=['Ruta #'.$r['id_ruta'].' | Fecha programada: '.$r['fecha_visita'],'Encuestador: '.$r['encuestador'],'Punto de entrega: '.$r['nombre_sede'],
            'Entrega: '.($r['fecha_entrega']? $r['fecha_entrega'].' por '.$r['entregado_por_nombre']:'Pendiente'),
            'Carga de 20 solicitudes. Confirmar citas antes de la visita.','La carga no implica completar las 20 visitas en una sola jornada.',''];
        foreach($r['solicitudes'] as $s) {
            $lines[]=$s['orden'].'. '.$s['numero_solicitud'].' | '.$s['nombres'].' '.$s['apellidos'];
            $lines[]='Celular: '.$s['telefono'].' | '.$s['direccion'];
            $lines[]=$s['barrio'].' / '.$s['nombre_comuna'].' | '.$s['latitud'].', '.$s['longitud'];
            $lines[]='Confirmación: __________ Visita: __________ Observación: __________________';$lines[]='';
        }
        PdfReport::download('ruta-'.$r['id_ruta'].'.pdf','Hoja de ruta',$lines);
    }
}
