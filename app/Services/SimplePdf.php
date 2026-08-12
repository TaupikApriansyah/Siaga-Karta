<?php
namespace App\Services;

class SimplePdf
{
    public static function make(string $title, array $lines): string
    {
        $wrapped = [strtoupper($title), 'Dibuat: '.now()->format('d-m-Y H:i'), str_repeat('-', 90)];
        foreach ($lines as $line) {
            foreach (self::wrap((string)$line, 105) as $part) $wrapped[] = $part;
        }
        $pages = array_chunk($wrapped, 48);
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $pageIds=[]; $contentIds=[]; $next=4;
        foreach ($pages as $_) { $pageIds[]=$next++; $contentIds[]=$next++; }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds)).'] /Count '.count($pageIds).' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        foreach ($pages as $i=>$pageLines) {
            $stream="BT\n/F1 9 Tf\n40 800 Td\n12 TL\n";
            foreach ($pageLines as $line) {
                $safe=self::escape(self::latin($line));
                $stream.='('.$safe.") Tj\nT*\n";
            }
            $stream.="ET\n";
            $objects[$contentIds[$i]]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";
            $objects[$pageIds[$i]]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentIds[$i].' 0 R >>';
        }
        ksort($objects);
        $pdf="%PDF-1.4\n"; $offsets=[0];
        foreach ($objects as $id=>$obj) { $offsets[$id]=strlen($pdf); $pdf.=$id." 0 obj\n".$obj."\nendobj\n"; }
        $xref=strlen($pdf); $max=max(array_keys($objects));
        $pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";
        for($id=1;$id<=$max;$id++) $pdf.=sprintf("%010d 00000 n \n",$offsets[$id]??0);
        $pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
        return $pdf;
    }
    private static function wrap(string $line,int $width): array { $x=wordwrap($line,$width,"\n",true); return explode("\n",$x); }
    private static function escape(string $v): string { return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$v); }
    private static function latin(string $v): string { $x=@iconv('UTF-8','Windows-1252//TRANSLIT',$v); return $x===false?$v:$x; }
}
