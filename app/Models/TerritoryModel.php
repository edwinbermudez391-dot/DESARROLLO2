<?php
/** Catálogo oficial versionado; las referencias de mapa nunca sustituyen el domicilio del ciudadano. */
declare(strict_types=1);
require_once __DIR__.'/../../core/Database.php';
class TerritoryModel
{
    public function catalog(): array
    {
        $db=Database::getConnection();
        return [
            'version'=>'IDESC-mapas-2026',
            'sectores'=>$db->query("SELECT id_comuna,codigo_comuna,nombre_comuna,tipo FROM comunas WHERE activo=1 ORDER BY tipo,CAST(codigo_comuna AS UNSIGNED),nombre_comuna")->fetchAll(),
            'barrios'=>$db->query('SELECT id_barrio,codigo_barrio,nombre_barrio,id_comuna,latitud_referencia,longitud_referencia,fuente FROM barrios WHERE activo=1 ORDER BY codigo_barrio')->fetchAll(),
        ];
    }
    public function validate(array $data): array
    {
        $db=Database::getConnection();
        $id=filter_var($data['id_comuna']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if ($id===false) return ['errors'=>['Selecciona una comuna o corregimiento válido.']];
        $q=$db->prepare('SELECT * FROM comunas WHERE id_comuna=? AND activo=1');$q->execute([$id]);$sector=$q->fetch();
        if (!$sector) return ['errors'=>['El sector no está disponible en el catálogo territorial.']];
        if ($sector['tipo']!=='Urbana') {
            $name=trim((string)($data['barrio']??''));
            if ($name==='') return ['errors'=>['Especifica la vereda o sector rural.']];
            return ['id_comuna'=>$id,'id_barrio'=>null,'barrio'=>$name,'corregimiento'=>$sector['nombre_comuna']];
        }
        if (trim((string)($data['corregimiento']??''))!=='') return ['errors'=>['No combines una comuna urbana con un corregimiento.']];
        $barrio=filter_var($data['id_barrio']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if ($barrio===false) return ['errors'=>['Selecciona un barrio del catálogo oficial.']];
        $q=$db->prepare('SELECT nombre_barrio FROM barrios WHERE id_barrio=? AND id_comuna=? AND activo=1');$q->execute([$barrio,$id]);$name=$q->fetchColumn();
        if ($name===false) return ['errors'=>['El barrio seleccionado no pertenece a la comuna indicada.']];
        return ['id_comuna'=>$id,'id_barrio'=>$barrio,'barrio'=>$name,'corregimiento'=>null];
    }
}
