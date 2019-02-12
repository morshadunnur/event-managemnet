<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\UserRegistration;
use Illuminate\Support\Facades\Auth;
use Session;
use View;
use Input;
use Illuminate\Support\Facades\Redirect;
use OwnLibrary;
use Illuminate\Support\Facades\Validator;
use DB;
use QrCode;
use DNS1D;
use DNS2D;
use Excel;

require 'vendor/phpmailer/phpmailer/PHPMailerAutoload.php';

class UserRegistrationController extends Controller {

    private $moduleId = 34;

    public function __construct() {

    }

   public function index(Request $request) {
       OwnLibrary::validateAccess($this->moduleId,1);

       if(Session::get('searchValue') && !empty(Session::get('searchValue'))){

            $usrRegistrations = UserRegistration::
                              where('registration_number','=',Session::get('searchValue'))
                            ->orWhere('mobile','=',Session::get('searchValue'))
                            ->orWhere('email','=',Session::get('searchValue'))->paginate(trans('english.PAGINATION_COUNT'));

            Session::flash('serValForFormInp', Session::get('searchValue'));
        
       }
       // Only for Event Search=========================================================
       if(Session::get('eventId') && !empty(Session::get('eventId'))){
            $usrRegistrations = UserRegistration::
                              where('event_id','=',Session::get('eventId'))->paginate(trans('english.PAGINATION_COUNT'));
            Session::flash('firstSearch1', Session::get('eventId'));
            
       }

       // Event Wise Seminer Search======================================================
       if(Session::get('eventIdForT2') && !empty(Session::get('eventIdForT2'))){
        $usrRegistrations = UserRegistration::
                          where('event_id','=',Session::get('eventIdForT2'))->where('seminer_id',Session::get('seminerIdForT2'))->paginate(trans('english.PAGINATION_COUNT'));
        Session::flash('secondSearch2', Session::get('eventIdForT2'));
        Session::flash('secondSearchSemId', Session::get('seminerIdForT2'));
       }

        // Event Wise pseticepnet search======================================================
       if(Session::get('eventIdForT3') && !empty(Session::get('eventIdForT3'))){

        $userIds = array_map('current',\App\UserEventAttendance::select('user_id')->where('event_id','=',Session::get('eventIdForT3'))->get()->toArray());
        //echo "<pre>"; print_r($userIds); exit;
        $usrRegistrations = UserRegistration::
                          whereIn('id',$userIds)->paginate(trans('english.PAGINATION_COUNT'));

        Session::flash('thirdSearch3', $userIds);
       }

       // Event Wise Seminer pseticepnet Search==========================================
       if(Session::get('eventIdForT4') && !empty(Session::get('eventIdForT4'))){
            $userIdss = array_map('current',\App\UserSeminarAttendance::select('user_id')
                                    ->where('event_id','=',Session::get('eventIdForT4'))
                                    ->where('seminar_id','=',Session::get('seminerIdForT4'))
                                    ->get()->toArray());
            $usrRegistrations = UserRegistration::whereIn('id',$userIdss)->paginate(trans('english.PAGINATION_COUNT'));
        Session::flash('fourthSearch4', $userIdss);
       }

       // Event Wise pre-registration Search==========================================
       if(Session::get('eventIdForT5') && !empty(Session::get('eventIdForT5'))){

        $userIdofAgents = array_map('current',\App\User::select('id')
                                ->where('role_id','=',9)
                                ->get()->toArray());

        $usrRegistrations = UserRegistration::whereNotIn('created_by',$userIdofAgents)->where('event_id','=',Session::get('eventIdForT5'))->paginate(trans('english.PAGINATION_COUNT'));
        Session::flash('fifthSearch5', Session::get('eventIdForT5'));
        Session::flash('fifthSearchAgentId', $userIdofAgents);
       }

       // Event Wise On Spot Registration Search==========================================
       if(Session::get('eventIdForT6') && !empty(Session::get('eventIdForT6'))){

        $userIdofAgents = array_map('current',\App\User::select('id')
                                ->where('role_id','=',9)
                                ->get()->toArray());

        $usrRegistrations = UserRegistration::whereIn('created_by',$userIdofAgents)->where('event_id','=',Session::get('eventIdForT6'))->paginate(trans('english.PAGINATION_COUNT'));
        Session::flash('sixthSearch6', Session::get('eventIdForT6'));
        Session::flash('sixthSearchAgentId', $userIdofAgents);
       }

       // Only Get Function
        if(empty(Session::get('searchValue')) && empty(Session::get('eventId')) && empty(Session::get('eventIdForT2')) && empty(Session::get('eventIdForT3')) && empty(Session::get('eventIdForT4')) && empty(Session::get('eventIdForT5')) && empty(Session::get('eventIdForT6'))){

            if($request->segment(1)=='user-registration'){
                $usrRegistrations = UserRegistration::where('id','!=','')->paginate(trans('english.PAGINATION_COUNT'));
            }else{
                if($request->segment(1)=='user-registration-today'){
                $usrRegistrations = UserRegistration::whereDate('created_at', date('Y-m-d'))->paginate(trans('english.PAGINATION_COUNT'));
                }
                if($request->segment(1)=='user-registration-thisMonth'){
                $usrRegistrations = UserRegistration::whereBetween('created_at', [date('Y-m-01'), date('Y-m-t')])->paginate(trans('english.PAGINATION_COUNT'));
                }
                if($request->segment(1)=='user-registration-previousMonth'){
                $usrRegistrations = UserRegistration::whereBetween('created_at', [date('Y-m-01', strtotime('last month')), date('Y-m-t', strtotime('last month'))])->paginate(trans('english.PAGINATION_COUNT'));
                }
            }
            
        }

       return View::make('user_registration.index')->with(compact('usrRegistrations'));
    }

    public function create() {
        OwnLibrary::validateAccess($this->moduleId,2);

        $event = \App\EventManagement::where('status','=',1)->pluck('title','id')->toArray();

        // spont registration or ont
        if(Auth::user()->role_id==9){
            $registrationWay = \App\RegistrationWay::where('id','=',2)->where('registration_way','=','On Spot')->pluck('registration_way','id')->toArray();
        }else{
            $registrationWay = \App\RegistrationWay::all()->pluck('registration_way','id')->toArray();
            //echo "<pre>"; print_r($registrationWay); exit;
        }
        
        $visitoTypes = \App\RegistrationType::all()->pluck('registration_type', 'id')->toArray();

        $namePrefix = \App\NamePrefix::all()->pluck('name_prefix', 'id')->toArray();

        return View::make('user_registration.create')->with(compact('visitoTypes','registrationWay','event','$visitoTypes','namePrefix'));
    }

    public function store(Request $request) {
        \OwnLibrary::validateAccess($this->moduleId,2);

    $this->middleware('csrf', array('on' => 'post'));

    $v = Validator::make($request->all(), [
           'registration_way' => 'required',
            'visitor_type' => 'required',
            'name_prefix' => 'required|array',
            'first_name' => 'required|array',
            'mobile' => 'required|array',
            'email' => 'required|array',
            'job_title' => 'required|array',
        ],
        $messages = [
                    'registration_way.required' => 'The Registration Type field is required',
                    'visitor_type.required' => 'The Visitor Type field is required',
                    'name_prefix.required' => 'The Name Prefix field is required',
                    'first_name.required' => 'The First Name field is required',
                    'mobile.required' => 'The Mobile field is required',
                    'email.required' => 'Email field is required',
                    'job_title.required' => 'Job Title field is required'
                    ]
    );

        if ($v->fails()) {
            return redirect('user-registration/create')->withErrors($v->errors())->withInput();
        }else {
            

        //===================================================================================
            function sendEmail($email,$emaiSendname,$RegistrationID,$password){

$emailHtml = '
<body style="width: 100% !important; height: 100%; background: #f8f8f8; margin: 0; padding: 0; font-size: 100%; font-family: "Avenir Next", "Helvetica Neue", "Helvetica", Helvetica, Arial, sans-serif; line-height: 1.65;">
<table class="body-wrap" style="width: 100% !important; height: 100%; background: #f8f8f8;">
    <tr>
        <td class="container" style="display: block !important; clear: both !important; margin: 0 auto !important; max-width: 580px !important;">

            <!-- Message start -->
            <table>
                <tr>
                    <td align="center" class="masthead" style="padding: 30px 0; background: #71bc37; color: white; ">

                        <h1 style="margin-bottom: 20px; line-height: 1.25; font-size: 26px; margin: 0 auto !important; max-width: 90%; text-transform: uppercase;">AHCAB</h1>

                    </td>
                </tr>
                <tr>
                    <td class="content" style="background: white; padding: 10px 35px;">

                        <h3>Hi {{username}},</h3>

                        <p style=" font-size: 16px; font-weight: normal; margin-bottom: 15px;">Welcome to AHCAB. Your Registration Informations:</p>
                        <span>Registration ID: </span><span>{{RegistrationID}}</span><br>
                        <span>Email: </span><span>{{UserEmail}}</span><br>
                        <span>Password: </span><span><span>{{password}}</span>

                        <table>
                            <tr>
                                <td align="center">
                                    <p style=" font-size: 16px; font-weight: normal; margin-bottom: 15px;">
                                        <a href="#" class="button" style="display: inline-block; color: white; background: #71bc37; border: solid #71bc37; border-width: 10px 20px 8px; font-weight: bold; border-radius: 4px; text-decoration: none;">
                                          Share the Awesomeness
                                        </a>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <p style=" font-size: 16px; font-weight: normal; margin-bottom: 20px;">By the way, if you are wondering where you can find more of this fine meaty filler, visit.</p>

                        <p style=" font-size: 16px; font-weight: normal; margin-bottom: 20px;"><em>– Regards<br/>AHCAB</em></p>

                    </td>
                </tr>
            </table>

        </td>
    </tr>
    <tr>
      
    </tr>
</table>
</body>

';

                $appInfo = \App\Settings::all();

                $data = array(
                'username' => $emaiSendname,
                'company_name' => $appInfo[0]->site_title,
                'from' => $appInfo[0]->email,
                'RegistrationID' => $RegistrationID,
                'UserEmail' => $email,
                'password' => $password
                );

                foreach($data as $key => $value)
                {
                    $emailHtml = str_replace('{{'.$key.'}}', $value, $emailHtml);
                }

                $body = $emailHtml;

                $mail=new \PHPMailer();

                $mail->setFrom($appInfo[0]->email, $appInfo[0]->site_title);
                $mail->addAddress($email, $emaiSendname);  
                $mail->isHTML(true);           

                $mail->Subject = 'Registration';
                $mail->Body    = $body;

                if(!$mail->send()) {
                    //dd('not send');
                } else {
                    //dd('send');
                }

            }
            
        //====================================================================================

                
                for ($i=0; $i < count($request->first_name); $i++){
                    if(!empty($request->first_name[$i])){
 
                        $userReg = new UserRegistration();

                        $userReg->event_id            = $request->event_id;
                        $userReg->registration_way    = $request->registration_way;
                        //$userReg->registration_number = 
                        $userReg->visitor_type = $request->visitor_type;
                        $userReg->name_prefix = $request->name_prefix[$i];
                        $userReg->first_name = $request->first_name[$i];
                        $userReg->last_name = $request->last_name[$i];
                        $userReg->email = $request->email[$i];
                        $userReg->telephone = $request->telephone;
                        $userReg->mobile = $request->mobile[$i];
                        $userReg->company_name = $request->company_name;
                        $userReg->job_title = $request->job_title[$i];
                        $userReg->country = $request->country;
                        $userReg->post_code = $request->post_code;
                        $userReg->address = $request->address;
                        $userReg->status = $request->status;

                        if ($userReg->save()) {

                            $registrationNumber = date('y').$request->event_id.$userReg->id;
                            $updateRegNo = UserRegistration::find($userReg->id);
                            $updateRegNo->registration_number=$registrationNumber;

                            if($i==0 && count($request->first_name)>1){
                                $updateRegNo->team_leader = $registrationNumber;
                            }
                            if($updateRegNo->save()){
                                //dd('email will be send');
                                $nameOfEmailSnd = $userReg->first_name.' '.$userReg->last_name;

                                sendEmail($userReg->email,$nameOfEmailSnd,$updateRegNo->registration_number,'123456');
                                $userInfo = UserRegistration::find($userReg->id);
                                return View::make('user_registration.idcard')->with(compact('userInfo'));
                            }
                            
                        }
                    }

                }
                Session::flash('success', Input::get('name') . trans('english.HAS_BEEN_CREATED_SUCESSFULLY'));
                    return Redirect::to('user-registration');
        }

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id) {
        
    }

    public function update(Request $request,$id) {

    }

    public function userRegistrationDelete($id){

        OwnLibrary::validateAccess($this->moduleId,4);
        $userReg = UserRegistration::find($id);
        //$event->delete();
        if ($userReg->delete()) {
                Session::flash('success', 'User Deleted Successfully');
                return Redirect::to('user-registration');
            } else {
                Session::flash('error', 'User Not Found');
                return Redirect::to('user-registration');
            }
    }

    public function userPreRegistration(){
        return View::make('user_registration.pre_registration');
    }

    public function userPreRegisterSearch(Request $request){

        $activeEnentId = \App\EventManagement::where('status','=',1)->value('id'); 
        $getInput = $request->searchPreRegsterUser;
        $getPreRegisterUser = UserRegistration::select('id')->where('event_id','=', $activeEnentId)

        ->where(function($query) use($getInput){
                $query->where('registration_number','=',$getInput);
                $query->orWhere('mobile','=',$getInput);
                $query->orWhere('email','=',$getInput);
        })->get();

        if(count($getPreRegisterUser)>0){

            $getPreRegisterUserMaxId = UserRegistration::select('id')->where('event_id','=', $activeEnentId)
                ->where(function($query) use($getInput){
                $query->where('registration_number','=',$getInput);
                $query->orWhere('mobile','=',$getInput);
                $query->orWhere('email','=',$getInput);
            })->max('id');

            $userInfo = UserRegistration::find($getPreRegisterUserMaxId);

            return View::make('user_registration.idcard')->with(compact('userInfo'));
        }else{
            Session::flash('error', 'User Registration Number Does Not Found');
                return Redirect::to('user-pre-registration');
        }
        
    }
    
    public function userRegistrationView($id){
        //$userInfo = UserRegistration::where('id','=',$id)->get()->toArray();
        $userInfo = UserRegistration::find($id);
        //echo "<pre>"; print_r($userInfo); exit;
        return View::make('user_registration.each-user-view',compact('userInfo'));
    }

    public function eachUserInfoPrint($id){

         $columnsName = new UserRegistration;
         $columns = $columnsName->getTableColumns();

         $columnsCustom = array('Id','Event Name','Registration Way','Registration Number','Visitor Type','Name Prefix','First Name','Last Name','Email','Telephone','Mobile','Company Name','Job Title','Country','Post Code','Address','Team Leader','Status');

        //$eachUsersArr = UserRegistration::where('id','=',$id)->get()->toArray();

        $eachUsersArr = UserRegistration::
           join('event_management','user_registrations.event_id','=','event_management.id')
         ->join('visitor_registration_way','user_registrations.registration_way','=','visitor_registration_way.id')
         ->join('visitor_registration_type','user_registrations.visitor_type','=','visitor_registration_type.id')
         ->join('set_name_prefix','user_registrations.name_prefix','=','set_name_prefix.id')
         ->where('user_registrations.id',$id)
         ->select('user_registrations.id','event_management.title','visitor_registration_way.registration_way','user_registrations.registration_number','visitor_registration_type.registration_type','set_name_prefix.name_prefix','user_registrations.first_name','user_registrations.last_name','user_registrations.email','user_registrations.telephone','user_registrations.mobile','user_registrations.company_name','user_registrations.job_title','user_registrations.country','user_registrations.post_code','user_registrations.address','user_registrations.status')
         ->get()->toArray(); 

        Excel::create('User Profile', function($excel) use ($eachUsersArr) {

            $excel->setTitle('User Profile');
            $excel->sheet('FirstSheet', function($sheet) use($eachUsersArr) {
                $sheet->fromArray($eachUsersArr);
            });

        })->export('xls');

    }

    public function allUserInformation(){

        //$eachUsersArr = UserRegistration::where('id','=',$id)->get()->toArray();

        $eachUsersArr = UserRegistration::
           join('event_management','user_registrations.event_id','=','event_management.id');
         $eachUsersArr->join('visitor_registration_way','user_registrations.registration_way','=','visitor_registration_way.id');
         $eachUsersArr->join('visitor_registration_type','user_registrations.visitor_type','=','visitor_registration_type.id');
         $eachUsersArr->join('set_name_prefix','user_registrations.name_prefix','=','set_name_prefix.id');
         if(Session::has('serValForFormInp')){
            $eachUsersArr->where('user_registrations.registration_number','=',Session::get('serValForFormInp'));
            $eachUsersArr->orWhere('mobile','=',Session::get('serValForFormInp'));
            $eachUsersArr->orWhere('email','=',Session::get('serValForFormInp'));
         }
         if(Session::has('firstSearch1')){
            $eachUsersArr->where('user_registrations.event_id','=',Session::get('firstSearch1'));
         }
         if(Session::has('secondSearch2')){
            $eachUsersArr->where('user_registrations.event_id','=',Session::get('secondSearch2'));
            $eachUsersArr->where('user_registrations.seminer_id',Session::get('secondSearchSemId'));
         }
         if(Session::has('thirdSearch3')){
            $eachUsersArr->whereIn('user_registrations.id',Session::get('thirdSearch3'));
         }
         if(Session::has('fourthSearch4')){
            $eachUsersArr->whereIn('user_registrations.id',Session::get('fourthSearch4'));
         }
         if(Session::has('fifthSearch5')){
            $eachUsersArr->whereNotIn('user_registrations.created_by',Session::get('fifthSearchAgentId'));
            $eachUsersArr->where('user_registrations.event_id','=',Session::get('fifthSearch5'));
         }

         if(Session::has('sixthSearch6')){
            $eachUsersArr->whereIn('user_registrations.created_by',Session::get('sixthSearchAgentId'));
            $eachUsersArr->where('user_registrations.event_id','=',Session::get('sixthSearch6'));
         }
        
         $eachUsersArr->select('user_registrations.id as Id','event_management.title as Event Name','visitor_registration_way.registration_way as Registration Way','user_registrations.registration_number as Registration Number','visitor_registration_type.registration_type as Visitor Type','set_name_prefix.name_prefix as Name Prefix','user_registrations.first_name as First Name','user_registrations.last_name as Last Name','user_registrations.email as Email','user_registrations.telephone as Telephone','user_registrations.mobile as Mobile','user_registrations.company_name as Company Name','user_registrations.job_title as Job Title','user_registrations.country as Country','user_registrations.post_code as Post Code','user_registrations.address as Address','user_registrations.status as Status');
         $eachUsersArr = $eachUsersArr->get()->toArray(); 

        Excel::create('User Profile', function($excel) use ($eachUsersArr) {

            $excel->setTitle('User Profile');
            $excel->sheet('FirstSheet', function($sheet) use($eachUsersArr) {
                $sheet->fromArray($eachUsersArr);
            });

        })->export('xls');
        // })->export('xls',$columnsCustom);
    }

    public function getEventsIds(Request $request){
        // return $request->selected_serch_id;
        $selected_serch_id = $request->selected_serch_id;

        if($request->selected_serch_id && $request->id==''){
            $events = \App\EventManagement::all();
            return $events;
        }

        if($request->id && $request->selected_serch_id==2){ 
            $seminer = \App\SeminarManagement::where('event_id','=',$request->id)->get();
            $data['searchType'] = $request->selected_serch_id;
            $data['common'] = $seminer;
            return $data;
        }

        if($request->id && $request->selected_serch_id==3){ 
            $seminer = \App\SeminarManagement::all();
            $data['searchType'] = $request->selected_serch_id;
            $data['common'] = $seminer;
            return $data;
        }

        if($request->id && $request->selected_serch_id==4){ 
            $seminer = \App\SeminarManagement::all();
            $data['searchType'] = $request->selected_serch_id;
            $data['common'] = $seminer;
            return $data;
        }
    }

    public function searchFromIndex (Request $request){
       
       $getInput = $request->searchInputFmIndex;
       Session::flash('searchValue',$getInput);
        return redirect('user-registration');

    }

     public function searchEventOrOthers (Request $request){
       
       $searchType = $request->searchRegisterBy;
       //$downloadExcel = $request->downloadExcel;
       if($searchType==1){
            $eventId = $request->event_id_append;
            Session::flash('eventId',$eventId);
            // if($downloadExcel==1){
            //     Session::flash('firstExDlod','yes');
            // }
       }
       // seminer wise search
       if($searchType==2){
            $eventIdForT2 = $request->event_id_append;
            Session::flash('eventIdForT2',$eventIdForT2);
            $seminerIdForT2 = $request->common_id_append; 
            Session::flash('seminerIdForT2',$seminerIdForT2);
       }
       // particepent wise event search
       if($searchType==3){
            $eventIdForT3 = $request->event_id_append;
            Session::flash('eventIdForT3',$eventIdForT3);
       }

       // particepent wise event and Seminer search
       if($searchType==4){
            $eventIdForT4 = $request->event_id_append;
            Session::flash('eventIdForT4',$eventIdForT4);
            $seminerIdForT4 = $request->common_id_append;
            Session::flash('seminerIdForT4',$seminerIdForT4);
       }

       // PreRegistration
       if($searchType==5){
            $eventIdForT5 = $request->event_id_append;
            Session::flash('eventIdForT5',$eventIdForT5);
       }

       // On Spot Registration
       if($searchType==6){
            $eventIdForT6 = $request->event_id_append;
            Session::flash('eventIdForT6',$eventIdForT6);
       }
       
        return redirect('user-registration');

    }
    

    //***************************************  Thumbnails Generating Functions :: End each-user-view *****************************
}

?>