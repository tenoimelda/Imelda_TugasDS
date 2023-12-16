<?php
// ini nama controller nya
namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
// ini nama model connect to database
use App\Models\pr;
use App\Models\biddings; // ini aku tambahkan lagi modul model nya guys
use Illuminate\Http\Request;
use Auth;
use App\Models\prdetail;
use DB;
use App\Models\prworkflow;
use App\Models\skemagbu;
use PDF;
use App\Models\uom;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportPR;
use App\Exports\ReportUsrPR;
use Mail;
use App\Models\typeproduct;
use Illuminate\Support\Facades\Storage;

class prsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 25;

        if (!empty($keyword)) {
            $prs = pr::where('tanggal', 'LIKE', "%$keyword%")
                ->orWhere('subject', 'LIKE', "%$keyword%")
                ->orWhere('users_id', 'LIKE', "%$keyword%")
                ->latest()->paginate($perPage);
        } else {
            $prs = pr::latest()->paginate($perPage);
        }

        return view('prs.index', compact('prs'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('prs.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
        
        $requestData = $request->all();
        
        pr::create($requestData);

        return redirect('prs')->with('flash_message', 'pr added!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $pr = pr::findOrFail($id);

        return view('prs.show', compact('pr'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $pr = pr::findOrFail($id);

        return view('prs.edit', compact('pr'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(Request $request, $id)
    {
        
        $requestData = $request->all();
        
        $pr = pr::findOrFail($id);
        $pr->update($requestData);

        return redirect('prs')->with('flash_message', 'pr updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {
        pr::destroy($id);

        return redirect('prs')->with('flash_message', 'pr deleted!');
    }

    public function masterprsadmin(Request $request){
        $keyword = $request->get('search');
        $perPage = 25;

        if (!empty($keyword)) {
            $pr = pr::where('subject', 'LIKE', "%$keyword%")
                ->orWhere('nopr', 'LIKE', "%$keyword%")
                ->paginate($perPage);
        } else {
            $pr = pr::orderBy('created_at','desc')->paginate($perPage);
        }
        return view('prs.masterpr-admin',compact('pr'));
    }

    public function masterprs(Request $request){
       
        $keyword = $request->get('search');
        $perPage = 25;

        if (!empty($keyword)) {
            $pr = pr::where('subject', 'LIKE', "%$keyword%")
                ->where('users_id',Auth::user()->id)
                ->paginate($perPage);
        } else {
            $pr = pr::where('users_id',Auth::user()->id)
            ->orderBy('created_at','desc')
            ->latest()->paginate($perPage);
        }

        return view('prs.master-data',compact('pr'));
    }

    public function sendpr(Request $request){

          $messages = [
            'required' => 'Data ini wajib di input !',
        ];

        $this->validate($request, [
            'tanggal'=> ['required'],
            'subject' => ['required'],
        ],$messages);

        $data = new pr();
        $data->nopr = '';
        $data->tanggal = $request->tanggal;
        $data->subject = $request->subject;
        $data->remarks = $request->remarks;
        $data->status = '0';
        $data->statusnotif = '0';
        $data->statuspo = '0';
        $data->note = $request->note;
        $data->users_id = Auth::id();

        if($request->attach == null){
            $request->attach = '';
        } else {
            $extensionx = $request->file('attach')->getClientOriginalName();
            $attach = $extensionx;
            $path = Storage::putFileAs('public/attach', $request->file('attach'),$attach);
            $data->attach = $attach;
        }

        $data->save();

        $skema = skemagbu::where('users_id',Auth::user()->id)->get();
        foreach ($skema as $key => $value) {
            $value->deptheads_id;
            $value->gms_id;
        }

        $flow = new prworkflow();
        $flow->seq = '0';
        $flow->lastaction = '1';
        $flow->users_id = Auth::id();
        $flow->prs_id = $data->id;
        $flow->save();

        $flow = new prworkflow();
        $flow->seq = '1';
        $flow->lastaction = '2';
        $flow->users_id = $value->deptheads_id;
        $flow->prs_id = $data->id;
        $flow->save(); 

        $flow = new prworkflow();
        $flow->seq = '2';
        $flow->lastaction = '0';
        $flow->users_id = $value->gms_id;
        $flow->prs_id = $data->id;
        $flow->save();


        return redirect('prdetail/'.$data->id);
    }

    public function prdetail($id){

        $pr = pr::findOrFail($id);
        $prdetail = prdetail::where('prs_id',$id)->paginate(10);
        $total  = prdetail::where('prs_id', $id)->sum(DB::raw('price*qty'));
        $hitung = prdetail::where('prs_id',$id)->count();
        $flow = prworkflow::where('prs_id',$id)->get();
        $type = typeproduct::all();
        $totalpr = prdetail::where('prs_id',$id)->count();
        $uom = uom::orderBy('namauom','asc')->get();

        return view('prs.pr-detail',compact('pr','prdetail','total','hitung','flow','uom','type','totalpr'));
    }

    public function senddetail(Request $request){

        $messages = [
            'required' => 'Data ini wajib di input !',
        ];

        $this->validate($request, [
        
            'description'=> ['required'],
            'qty' => ['required'],
            'price' => ['required'],
            'typeproducts_id' => ['required'],
            'uoms_id' => ['required'],
        ],$messages);

        $dtl = new prdetail();
        $dtl->description = $request->description;
        $dtl->brand = $request->brand;
        $dtl->qty = $request->qty;
        $dtl->typeproducts_id = $request->typeproducts_id;
        $dtl->tkdn = $request->tkdn;
        $dtl->percent = $request->percent;
        $dtl->uoms_id = $request->uoms_id;
        $dtl->price = str_replace('.', '', $request->price);
       
        $dtl->users_id = Auth::id();
        $dtl->prs_id = $request->prs_id;
        $dtl->save();


        return redirect()->back();
    }

    public function submitpr(Request $request){

        $pr = pr::findOrFail($request->id);
        $pr->status = '1';
        $pr->statusnotif = '1';
        $pr->nopr = 'PR00'.$pr->id;
        $pr->update();

        $prdetail = prdetail::where('prs_id',$pr->id)->get();

        $flow = prworkflow::where('prs_id',$pr->id)->where('seq',1)->get();
        foreach ($flow as $key => $value) {
                $value->user->name;
                $value->user->email;
            }    

        $data = [
            'to' => $value->user->name,
            'id' => $pr->id,
            'tgl' => $pr->tanggal,
            'nopr' => $pr->nopr,
            'subject' => $pr->subject,
            'remarks' => $pr->remarks,
            'creator' => $pr->user->name,
            'prdetail' => $prdetail
        ];

    $to = $value->user->email;
    \Mail::to($to)->cc($pr->user->email)
    ->bcc(['procurement@mmsresources.com','imelda.teno@mhucoal.co.id'])
    ->send(new \App\Mail\submitpr($data));

    return redirect('prdetail/'.$pr->id);
    }

    public function headapprove (Request $request){

        $app = prworkflow::findOrFail($request->id);
        $app->lastaction = '1';
        $app->updated_at = now();
        $app->update();

        $skema = prworkflow::where('seq',2)->where('prs_id',$app->prs_id)
                ->update(['lastaction'=>'2']);

        $pr = pr::findOrFail($app->prs_id);
        $prdetail = prdetail::where('prs_id',$pr->id)->get();

        $flow = prworkflow::where('prs_id',$pr->id)->where('seq',2)->get();
        foreach ($flow as $key => $value) {
                $value->user->name;
                $value->user->email;
            }   

        $data = [
            'to' => $value->user->name,
            'id' => $pr->id,
            'tgl' => $pr->tanggal,
            'nopr' => $pr->nopr,
            'subject' => $pr->subject,
            'remarks' => $pr->remarks,
            'creator' => $pr->user->name,
            'prdetail' => $prdetail
        ];

    $to = $value->user->email;
    \Mail::to($to)->cc($pr->user->email)->bcc(['procurement@mmsresources.com','imelda.teno@mhucoal.co.id'])->send(new \App\Mail\submitpr($data));

        return redirect()->back();
    }

    public function gmapprove (Request $request){

        $app = prworkflow::findOrFail($request->id);
        $app->lastaction = '1';
        $app->updated_at = now();
        $app->update();

        $pr = pr::findOrFail($app->prs_id);
        $pr->status = '2';
        $pr->update();

        $prdetail = prdetail::where('prs_id',$pr->id)->get();

        $flow = prworkflow::where('prs_id',$pr->id)->where('seq',0)->get();
        foreach ($flow as $key => $value) {
                $value->user->name;
                $value->user->email;
            }   

        $data = [
            'to' => $value->user->name,
            'id' => $pr->id,
            'tgl' => $pr->tanggal,
            'nopr' => $pr->nopr,
            'subject' => $pr->subject,
            'remarks' => $pr->remarks,
            'creator' => $pr->user->name,
            'prdetail' => $prdetail
        ];

    $to = $value->user->email;
    \Mail::to($to)->cc(['procurement@mmsresources.com','imelda.teno@mhucoal.co.id'])
    ->send(new \App\Mail\approvepr($data));

        return redirect()->back();
    }

    public function rejectpr(Request $request){

        $pr = pr::findOrFail($request->id);
        $pr->status = '4';
        $pr->note = $request->note;
        $pr->update();

        $skema = prworkflow::where('prs_id',$pr->id)
                ->update(['lastaction'=>'3']);

        $skemax = prworkflow::where('users_id',Auth::user()->id)->where('prs_id',$pr->id)
                ->update(['lastaction'=>'4']);

        $flow = prworkflow::where('prs_id',$pr->id)->where('seq',0)->get();
        foreach ($flow as $key => $value) {
            $value->user->name;
            $value->user->email;
        }

        $data = [
            'to' => $value->user->name,
            'id' => $pr->id,
            'tgl' => $pr->tanggal,
            'nopr' => $pr->nopr,
            'subject' => $pr->subject,
            'remarks' => $pr->remarks,
            'creator' => $pr->user->name,
            'note' => $pr->note
        ];

    $to = $value->user->email;
    \Mail::to($to)->bcc(['procurement@mmsresources.com','imelda.teno@mhucoal.co.id'])
    ->send(new \App\Mail\rejectpr($data));
       
        return redirect()->back();
    }

    public function cancelpr (Request $request){

        $messages = [
            'required' => 'Data ini wajib di input !',
        ];

        $this->validate($request, [
            'note'=> ['required'],
        ],$messages);

        $pr = pr::findOrFail($request->id);
        $pr->status = '0';
        $pr->statusnotif = '0';
        $pr->note = $request->note;
        $pr->update();

        $skema = prworkflow::where('seq',0)->where('prs_id',$pr->id)
        ->update(['lastaction'=>'1']);
        $skemax = prworkflow::where('seq',1)->where('prs_id',$pr->id)
        ->update(['lastaction'=>'2']);
        $skemay = prworkflow::where('seq',2)->where('prs_id',$pr->id)
        ->update(['lastaction'=>'0']);

        return redirect()->back();

    }

    public function printpr($id){

        $pr = pr::findOrFail($id);
        $prd = prdetail::where('prs_id',$id)->get();
        $total  = prdetail::where('prs_id', $id)->sum(DB::raw('price*qty'));
        $flow = prworkflow::where('prs_id',$id)->get();

        $pdf = PDF::loadView('prs.print-pr',['pr'=>$pr,'prd'=>$prd,'total'=>$total,'flow'=>$flow])
                ->setPaper('a4', 'landscape');
        return $pdf->stream('PR.pdf');
    }

    public function PageReportPR(Request $request){
        $prdetail = prdetail::paginate(15);
        return view('prs.reportadm-pr',compact('prdetail'));
    }

    public function PageUsrReportPR(Request $request){
        $prdetail = prdetail::where('users_id',Auth::user()->id)->paginate(15);
        return view('prs.reportpr-usr',compact('prdetail'));
    }

    public function ReportPR(Request $request ){
      return Excel::download(new ReportPR(), 'ReportPR.xlsx');
    }   

    public function ReportUsrPR(Request $request ){
      return Excel::download(new ReportUsrPR(), 'ReportPRUser.xlsx');
    }

    public function FormEditPr($id){
        $pr = pr::findOrFail($id);
        return view('prs.editpr',compact('pr'));
    }

    public function UpdatePR(Request $request){
        
        $pr = pr::findOrFail($request->id);
        $pr->nopr = $request->nopr;
        $pr->tanggal = $request->tanggal;
        $pr->subject = $request->subject;
        $pr->users_id = $request->users_id;
        $pr->status = $request->status;
        $pr->statusnotif = $request->statusnotif;
        $pr->statuspo = $request->statuspo;
        $pr->remarks = $request->remarks;
        $pr->note = $request->note;
        $pr->update();

        return redirect('prdetail/'.$pr->id);

    }

   public function deletepr(Request $request){
        $pr = pr::findOrFail($request->id);
        $pr->delete();
     return redirect()->back();
   }

   public function attach (Request $request, $file) {
        return response()->file(storage_path('app/public/attach/'.$file));
    }


}
