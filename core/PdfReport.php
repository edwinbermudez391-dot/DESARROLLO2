<?php
/** Exportador PDF de texto paginado, sin servicios externos. Fuente Helvetica WinAnsi. */
declare(strict_types=1);
class PdfReport
{
    public static function build(string $title, array $lines): string {
        $encoded=[];
        foreach ($lines as $line) {
            $text=iconv('UTF-8','Windows-1252//TRANSLIT',(string)$line);
            foreach (explode("\n",wordwrap($text===false?'':$text,92,"\n",true)) as $part) $encoded[]=$part;
        }
        // Mantener juntos los bloques separados por linea vacia (cada visita de una ruta).
        $pages=[];$page=[];$block=[];
        $flush=static function() use (&$pages,&$page,&$block): void {
            if (!$block) return;
            if (count($block)<=44 && count($page)+count($block)>44 && $page) {$pages[]=$page;$page=[];}
            foreach($block as $line) {if(count($page)===44){$pages[]=$page;$page=[];}$page[]=$line;}
            $block=[];
        };
        foreach($encoded as $line) {$block[]=$line;if($line==='') $flush();}
        $flush();if($page) $pages[]=$page;if(!$pages) $pages=[['Sin registros.']];
        $objects=[];$kids=[];
        $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $escape=static fn(string $s): string=>str_replace(['\\','(',')',"\r"],["\\\\","\\(","\\)",''],$s);
        $heading=iconv('UTF-8','Windows-1252//TRANSLIT',$title) ?: 'Informe';
        foreach($pages as $index=>$linesOnPage) {
            $pageId=4+$index*2;$contentId=$pageId+1;$kids[]="$pageId 0 R";
            $stream="BT /F1 16 Tf 45 795 Td (".$escape($heading).") Tj /F1 10 Tf 0 -26 Td ";
            foreach($linesOnPage as $line) $stream.='('.$escape($line).") Tj 0 -16 Td ";
            $stream.="ET BT /F1 9 Tf 45 28 Td (Encuestas Cali | ".date('Y-m-d H:i')." | Pagina ".($index+1).'/'.count($pages).") Tj ET";
            $objects[$pageId]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents $contentId 0 R >>";
            $objects[$contentId]='<< /Length '.strlen($stream).">>\nstream\n".$stream."\nendstream";
        }
        $objects[2]='<< /Type /Pages /Count '.count($pages).' /Kids ['.implode(' ',$kids).'] >>';ksort($objects);
        $pdf="%PDF-1.4\n";$offsets=[0];
        foreach($objects as $id=>$object) {$offsets[$id]=strlen($pdf);$pdf.="$id 0 obj\n$object\nendobj\n";}
        $start=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++) $pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
        return $pdf.'trailer << /Size '.(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$start\n%%EOF\n";
    }
    public static function download(string $filename,string $title,array $lines): void {
        $pdf=self::build($title,$lines);
        http_response_code(200);
        header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.strlen($pdf));echo $pdf;
    }
}
