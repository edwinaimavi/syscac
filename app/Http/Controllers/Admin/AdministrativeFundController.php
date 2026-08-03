<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeFundMovement;
use App\Models\CashMovement;
use App\Models\Member;
use App\Services\ShareCashMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class AdministrativeFundController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:admin.fondo-administrativo.index')->only(['index','list','summary','nextCode']);
        $this->middleware('can:admin.fondo-administrativo.create')->only('store');
        $this->middleware('can:admin.fondo-administrativo.show')->only('show');
        $this->middleware('can:admin.fondo-administrativo.edit')->only(['edit','update']);
        $this->middleware('can:admin.fondo-administrativo.anular')->only('annul');
        $this->middleware('can:admin.fondo-administrativo.voucher')->only('voucher');
    }
    public function index()
    {
        return view('admin.administrative-fund.index', [
            'members'=>Member::where('status','vigente')->orderBy('full_name')->get(['id','code','dni','full_name']),
            'nextCode'=>AdministrativeFundMovement::nextCode(),
            'paymentMethods'=>$this->paymentMethods(),
        ]);
    }
    public function list(Request $request)
    {
        $query=AdministrativeFundMovement::with('member')
            ->when($request->filled('type'),fn($q)=>$q->where('type',$request->type))
            ->when($request->filled('member_id'),fn($q)=>$q->where('member_id',$request->integer('member_id')))
            ->when($request->filled('status'),fn($q)=>$q->where('status',$request->status))
            ->when($request->filled('payment_method'),fn($q)=>$q->where('payment_method',$request->payment_method))
            ->when($request->filled('date_from'),fn($q)=>$q->whereDate('movement_date','>=',$request->date_from))
            ->when($request->filled('date_to'),fn($q)=>$q->whereDate('movement_date','<=',$request->date_to))
            ->latest('movement_date')->latest('id');
        return DataTables::of($query)->addIndexColumn()
            ->editColumn('movement_date',fn($m)=>$m->movement_date?->format('d/m/Y')??'-')
            ->editColumn('type',fn($m)=>'<span class="badge badge-'.($m->type==='ingreso'?'success':'danger').'">'.ucfirst($m->type).'</span>')
            ->addColumn('member_name',fn($m)=>$m->member?->full_name??'-')
            ->editColumn('payment_method',fn($m)=>$this->paymentMethods()[$m->payment_method]??'-')
            ->editColumn('amount',fn($m)=>'S/ '.number_format((float)$m->amount,2))
            ->editColumn('status',fn($m)=>'<span class="badge badge-'.($m->status==='anulado'?'danger':'success').'">'.ucfirst($m->status).'</span>')
            ->addColumn('acciones',fn($m)=>view('admin.administrative-fund.partials.actions',['movement'=>$m])->render())
            ->rawColumns(['type','status','acciones'])->make(true);
    }
    public function summary(Request $request)
    {
        $q=AdministrativeFundMovement::where('status','registrado')
            ->when($request->filled('date_from'),fn($q)=>$q->whereDate('movement_date','>=',$request->date_from))
            ->when($request->filled('date_to'),fn($q)=>$q->whereDate('movement_date','<=',$request->date_to));
        $income=(float)(clone $q)->where('type','ingreso')->sum('amount');
        $expense=(float)(clone $q)->where('type','egreso')->sum('amount');
        return response()->json(['balance'=>number_format($income-$expense,2),'income'=>number_format($income,2),'expense'=>number_format($expense,2),
            'month_movements'=>AdministrativeFundMovement::where('status','registrado')->whereYear('movement_date',now()->year)->whereMonth('movement_date',now()->month)->count()]);
    }
    public function nextCode() { return response()->json(['code'=>AdministrativeFundMovement::nextCode()]); }
    public function store(Request $request)
    {
        $data=$this->validated($request);
        $movement=DB::transaction(function()use($request,$data){
            $this->ensureBalance($data);
            if($request->hasFile('voucher_path'))$data['voucher_path']=$request->file('voucher_path')->store('administrative-fund','public');
            $movement=AdministrativeFundMovement::create($data+['code'=>AdministrativeFundMovement::nextCode(),'status'=>'registrado','created_by'=>auth()->id(),'updated_by'=>auth()->id()]);
            $this->syncCash($movement);
            return $movement;
        });
        return response()->json(['message'=>'Movimiento del fondo administrativo registrado correctamente.','id'=>$movement->id]);
    }
    public function show(AdministrativeFundMovement $fondo_administrativo) { return response()->json($this->payload($fondo_administrativo)); }
    public function edit(AdministrativeFundMovement $fondo_administrativo)
    {
        if($fondo_administrativo->source_type)return response()->json(['message'=>'Este movimiento se administra desde el aporte de origen.'],422);
        return response()->json($this->payload($fondo_administrativo));
    }
    public function update(Request $request, AdministrativeFundMovement $fondo_administrativo)
    {
        if($fondo_administrativo->source_type||$fondo_administrativo->status==='anulado')return response()->json(['message'=>'Este movimiento no puede editarse directamente.'],422);
        $data=$this->validated($request);
        DB::transaction(function()use($request,$data,$fondo_administrativo){
            $this->ensureBalance($data,$fondo_administrativo);
            if($request->hasFile('voucher_path'))$data['voucher_path']=$request->file('voucher_path')->store('administrative-fund','public');
            $fondo_administrativo->update($data+['updated_by'=>auth()->id()]);
            $this->syncCash($fondo_administrativo);
        });
        return response()->json(['message'=>'Movimiento actualizado correctamente.']);
    }
    public function annul(Request $request, AdministrativeFundMovement $fondo_administrativo)
    {
        if($fondo_administrativo->source_type)return response()->json(['message'=>'Anule el aporte de origen.'],422);
        $request->validate(['cancellation_reason'=>['required','string','max:500']]);
        DB::transaction(function()use($request,$fondo_administrativo){
            $fondo_administrativo->update(['status'=>'anulado','cancellation_reason'=>$request->cancellation_reason,'cancelled_at'=>now(),'cancelled_by'=>auth()->id(),'updated_by'=>auth()->id()]);
            CashMovement::whereKey($fondo_administrativo->cash_movement_id)->update(['status'=>'anulado','annulled_at'=>now(),'annulled_by'=>auth()->id(),'updated_by'=>auth()->id()]);
            app(ShareCashMovementService::class)->recalculateBalances();
        });
        return response()->json(['message'=>'Movimiento anulado correctamente.']);
    }
    public function voucher(AdministrativeFundMovement $fondo_administrativo)
    {
        abort_unless($fondo_administrativo->voucher_path&&Storage::disk('public')->exists($fondo_administrativo->voucher_path),404);
        return response()->file(Storage::disk('public')->path($fondo_administrativo->voucher_path));
    }
    private function validated(Request $r):array{return $r->validate([
        'movement_date'=>['required','date'],'type'=>['required',Rule::in(['ingreso','egreso'])],'member_id'=>['nullable','exists:members,id'],
        'concept'=>['required','string','max:255'],'amount'=>['required','numeric','gt:0'],'payment_method'=>['required',Rule::in(array_keys($this->paymentMethods()))],
        'payment_reference'=>['nullable','string','max:100'],'voucher_path'=>['nullable','file','mimes:jpg,jpeg,png,webp,pdf','max:4096'],'observation'=>['nullable','string'],
    ]);}
    private function ensureBalance(array $data,?AdministrativeFundMovement $ignore=null):void
    {
        if($data['type']!=='egreso')return;
        $q=AdministrativeFundMovement::where('status','registrado');if($ignore)$q->whereKeyNot($ignore->id);
        $balance=(float)(clone $q)->where('type','ingreso')->sum('amount')-(float)(clone $q)->where('type','egreso')->sum('amount');
        if((float)$data['amount']>$balance)throw ValidationException::withMessages(['amount'=>['No hay saldo suficiente en el fondo administrativo para registrar este egreso.']]);
    }
    private function syncCash(AdministrativeFundMovement $m):void
    {
        $cash=CashMovement::find($m->cash_movement_id)??new CashMovement(['movement_number'=>CashMovement::nextCode(),'created_by'=>$m->created_by]);
        $cash->fill(['movement_date'=>$m->movement_date,'type'=>$m->type,'category'=>'fondo_administrativo','concept'=>$m->concept,'amount'=>$m->amount,
            'payment_method'=>$m->payment_method,'reference'=>$m->payment_reference,'voucher_path'=>$m->voucher_path,'related_type'=>AdministrativeFundMovement::class,
            'related_id'=>$m->id,'observation'=>$m->observation,'status'=>'registrado','updated_by'=>auth()->id()])->save();
        $m->forceFill(['cash_movement_id'=>$cash->id])->save();app(ShareCashMovementService::class)->recalculateBalances();
    }
    private function payload(AdministrativeFundMovement $m):array
    {
        $m->load(['member','cashMovement','creator','canceller']);
        return ['id'=>$m->id,'code'=>$m->code,'movement_date'=>$m->movement_date?->format('Y-m-d'),'movement_date_formatted'=>$m->movement_date?->format('d/m/Y'),
            'type'=>$m->type,'type_label'=>ucfirst($m->type),'member_id'=>$m->member_id,'member_name'=>$m->member?->full_name,'member_dni'=>$m->member?->dni,
            'concept'=>$m->concept,'amount'=>number_format((float)$m->amount,2,'.',''),'amount_formatted'=>'S/ '.number_format((float)$m->amount,2),
            'payment_method'=>$m->payment_method,'payment_method_label'=>$this->paymentMethods()[$m->payment_method]??'-','payment_reference'=>$m->payment_reference,
            'observation'=>$m->observation,'status'=>$m->status,'source_label'=>$m->source_type?'Aporte':'Manual','is_automatic'=>(bool)$m->source_type,
            'cash_movement_number'=>$m->cashMovement?->movement_number,'voucher_url'=>$m->voucher_path?route('admin.fondo-administrativo.voucher',$m):null,
            'created_at'=>$m->created_at?->format('d/m/Y H:i'),'created_by_name'=>$m->creator?->name,'cancelled_at'=>$m->cancelled_at?->format('d/m/Y H:i'),
            'cancelled_by_name'=>$m->canceller?->name,'cancellation_reason'=>$m->cancellation_reason];
    }
    private function paymentMethods():array{return ['efectivo'=>'Efectivo','yape'=>'Yape','plin'=>'Plin','transferencia'=>'Transferencia','otro'=>'Otro'];}
}
