<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Transaction;
use App\Services\SimplePdf;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    private function safeCell(mixed $value): mixed
    {
        if (!is_string($value)) return $value;
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        // Prevent spreadsheet formula execution from user-controlled CSV values.
        if ($value !== '' && preg_match('/^[=+\-@\t]/u', $value)) return "'".$value;
        return $value;
    }

    private function putRow($handle, array $row): void
    {
        fputcsv($handle, array_map(fn($v) => $this->safeCell($v), $row));
    }

    public function ambulanceCsv()
    {
        return response()->streamDownload(function(){
            $o=fopen('php://output','w'); fwrite($o,"\xEF\xBB\xBF");
            $this->putRow($o,['Kode','Tanggal','Nama','Jenis','Sumber','Status','Mulai Layanan','Selesai Rencana/Aktual','Lokasi','Ambulans','Pengemudi']);
            Report::where('category','ambulans')->with(['citizen:id,name','ambulance:id,code,plate_number','driver:id,name'])
                ->lazyByIdDesc(500)->each(function($r)use($o){
                    $this->putRow($o,[$r->code,$r->created_at->format('Y-m-d H:i'),$r->citizen?->name,$r->type,$r->source,$r->status,$r->service_start_at?->format('Y-m-d H:i'),$r->service_end_at?->format('Y-m-d H:i'),$r->pickup_location,$r->ambulance?->code,$r->driver?->name]);
                });
            fclose($o);
        },'laporan-operasional-ambulans-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }

    public function serviceCsv()
    {
        return response()->streamDownload(function(){
            $o=fopen('php://output','w'); fwrite($o,"\xEF\xBB\xBF");
            $this->putRow($o,['Kode','Tanggal','Kategori','Jenis','Nama','Sumber','Status','Lokasi/Kejadian','Keterangan','Jadwal','Ambulans','Pengemudi']);
            Report::with(['citizen:id,name','ambulance:id,code,plate_number','driver:id,name'])
                ->lazyByIdDesc(500)->each(function($r)use($o){
                    $this->putRow($o,[$r->code,$r->created_at->format('Y-m-d H:i'),$r->category,$r->type,$r->citizen?->name,$r->source,$r->status,$r->pickup_location,$r->medical_condition,$r->scheduled_at?->format('Y-m-d H:i'),$r->ambulance?->code,$r->driver?->name]);
                });
            fclose($o);
        },'laporan-pelayanan-warga-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }

    public function servicePdf()
    {
        $rows=Report::with(['citizen:id,name','ambulance:id,code','driver:id,name'])->latest()->limit(1500)->get();
        $lines=$rows->map(fn($r)=>sprintf('%s | %s | %s | %s | %s | %s | %s',$r->code,$r->created_at->format('d-m-Y H:i'),strtoupper($r->category),$r->citizen?->name,$r->status,$r->pickup_location,$r->ambulance?->code??'-'))->all();
        $pdf=SimplePdf::make('Laporan Pelayanan Warga - Ambulans, BPJS, Bencana',$lines);
        return response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="laporan-pelayanan-warga-'.now()->format('Ymd-His').'.pdf"','Cache-Control'=>'private, no-store']);
    }

    public function financeCsv()
    {
        return response()->streamDownload(function(){
            $o=fopen('php://output','w'); fwrite($o,"\xEF\xBB\xBF");
            $this->putRow($o,['Kode','Tanggal','Tipe','Kategori','Nominal','Status','Sumber','Nama Pembayar','Kontak Akhir','Program','Keterangan']);
            Transaction::with('program:id,name')->lazyByIdDesc(500)->each(function($t)use($o){
                $this->putRow($o,[$t->code,$t->transaction_date->format('Y-m-d'),$t->type,$t->category,$t->amount,$t->status,$t->source,$t->payer_name,$t->payer_phone_last4?('****'.$t->payer_phone_last4):null,$t->program?->name,$t->description]);
            });
            fclose($o);
        },'laporan-keuangan-'.now()->format('Ymd-His').'.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
    }

    public function ambulancePdf()
    {
        $rows=Report::where('category','ambulans')->with(['citizen:id,name','ambulance:id,code','driver:id,name'])->latest()->limit(1000)->get();
        $lines=$rows->map(fn($r)=>sprintf('%s | %s | %s | %s | %s | %s - %s | Unit: %s | Pengemudi: %s',$r->code,$r->created_at->format('d-m-Y H:i'),$r->citizen?->name,$r->type,$r->status,$r->service_start_at?->format('d-m-Y H:i')??'-',$r->service_end_at?->format('d-m-Y H:i')??'-',$r->ambulance?->code??'-',$r->driver?->name??'-'))->all();
        $pdf=SimplePdf::make('Laporan Operasional Ambulans',$lines);
        return response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="laporan-ambulans-'.now()->format('Ymd-His').'.pdf"','Cache-Control'=>'private, no-store']);
    }

    public function financePdf(Request $request)
    {
        abort_unless(in_array($request->attributes->get('api_user')->role,['admin','petugas'],true),403);
        $rows=Transaction::latest('transaction_date')->latest('id')->limit(1500)->get();
        $saldo=(int)Transaction::where('status','verified')->selectRaw("coalesce(sum(case when type='pemasukan' then amount else -amount end),0) s")->value('s');
        $lines=['SALDO TERVERIFIKASI: Rp '.number_format($saldo,0,',','.')];
        foreach($rows as $t)$lines[]=sprintf('%s | %s | %s | %s | Rp %s | %s | %s | %s',$t->code,$t->transaction_date->format('d-m-Y'),$t->type,$t->category,number_format($t->amount,0,',','.'),$t->status,$t->source,$t->payer_name??'-');
        $pdf=SimplePdf::make('Laporan Keuangan dan Kas Infaq',$lines);
        return response($pdf,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="laporan-keuangan-'.now()->format('Ymd-His').'.pdf"','Cache-Control'=>'private, no-store']);
    }
}
