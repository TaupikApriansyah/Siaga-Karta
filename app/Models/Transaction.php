<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
class Transaction extends Model
{
    protected $fillable=['code','type','category','amount','status','source','payer_name','payer_phone_encrypted','payer_phone_last4','payment_proof_path','payment_proof_hash','rejection_reason','program_id','description','transaction_date','created_by','verified_by','verified_at'];
    protected function casts(): array {return ['transaction_date'=>'date','verified_at'=>'datetime'];}
    public function program(){return $this->belongsTo(Program::class);}
    public function setPayerPhone(?string $phone): void { if(!$phone){$this->payer_phone_encrypted=null;$this->payer_phone_last4=null;return;} $this->payer_phone_encrypted=Crypt::encryptString($phone);$this->payer_phone_last4=substr($phone,-4); }
    public function getPayerPhone(): ?string { return $this->payer_phone_encrypted ? Crypt::decryptString($this->payer_phone_encrypted) : null; }
}
