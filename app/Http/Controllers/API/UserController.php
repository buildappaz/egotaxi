<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\DriverDocument;
use App\Models\Wallet;
use App\Models\UserDetail;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\DriverResource;
use Illuminate\Support\Facades\Password;
use App\Models\AppSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\DriverRequest;
// use App\Http\Controllers\API\SendMail;  
use Illuminate\Support\Facades\Mail;
use App\Notifications\CommonNotification;
use App\Notifications\SomeNotification;
use Illuminate\Notifications\FcmNotification;
use Benwilkins\FCM\FcmMessage; 
use Illuminate\Support\Facades\Http;

use App\Helpers\helper;
use App\Models\Setting;

 
  


class UserController extends Controller
{


   
   public function sendNotification()
{
  
    $deviceToken = 'cDigl0oESNCHUWrprR6qB2:APA91bGNl9YLFPN5R49QXJlru5R8XsXo0R3-5pHHzj06esgACkc7BF08IgLqjdixjLp2_7EZhW9rZpv2_jNl_QqM7ipYeqJOJ0glvLxHuf7gDVgjNK114Fc3bWFMO8MQvMnfH_Q6Oux3'; 






      $user=User::find(3);



    // $title = 'Başlık';
    // $body = 'İçerik';

    // // $notification = FcmNotification::create([
    // //     'device_token' => $deviceToken,
    // //     'title' => $title,
    // //     'body' => $body,
    // // ]);

    // $response = Http::withHeaders([
    //     'Authorization' => 'key=AAAACMPNeE0:APA91bFusF5H6zTmWUqVJ3UXhJLN_oUAjQdK8BFqpN7r5L2YjTroISZkyndMoLdCqo_I5MrzSpHgppn6drDm3X2TuG-xBnx_MuKy0l9eH3OrTuhdsk4zw9rG98NJr9wGv98tPSWUGjyz',
    //     'Content-Type' => 'application/json',
    // ])->post('https://fcm.googleapis.com/fcm/send', [
    //     'to' => $deviceToken,
    //     'notification' => [ 
    //         'title' => $title,
    //         'body' => $body,
    //     ],
    // ]);

    // if ($response->successful()) {
    //     // Bildirim başarıyla gönderildi
    //     // İstediğiniz işlemleri yapabilirsiniz
    // } else {
    //     // Bildirim gönderme hatası
    //     $error = $response->json('error');
    //     // Hata mesajını kullanabilir veya loglayabilirsiniz
    // }

      $response=["type" => "REQUEST" ,"user" => $user->uid];

      $response= sendNotify($deviceToken, "BEnim baslik", $response); 


    return $response;

 
 
}



    public function register(UserRequest $request)
    {
        $input = $request->all();
                
        $password = $input['password'];
        $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'rider';
        $input['password'] = Hash::make($password);

        if( in_array($input['user_type'],['driver']))
        {
            $input['status'] = isset($input['status']) ? $input['status']: 'pending';
        }

        $input['display_name'] = $input['first_name']." ".$input['last_name'];
        $user = User::create($input);
        $user->assignRole($input['user_type']);

        if( $request->has('user_detail') && $request->user_detail != null ) {
            $user->userDetail()->create($request->user_detail);
        }

        $message = __('message.save_form',['form' => __('message.'.$input['user_type']) ]);
        $user->api_token = $user->createToken('auth_token')->plainTextToken;
        $user->profile_image = getSingleMedia($user, 'profile_image', null);
        $response = [
            'message' => $message,
            'data' => $user
        ];
        return json_custom_response($response);
    }  

    public function driverRegister(DriverRequest $request)
    {
        $input = $request->all();
        $password = $input['password'];
        $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'driver';
        $input['password'] = Hash::make($password);

        $input['status'] = isset($input['status']) ? $input['status']: 'pending';

        $input['display_name'] = $input['first_name']." ".$input['last_name'];
        $input['is_available'] = 1;
        $user = User::create($input);
        $user->assignRole($input['user_type']);

        if( $request->has('user_detail') && $request->user_detail != null ) {
            $user->userDetail()->create($request->user_detail);
        }
        
        if( $request->has('user_bank_account') && $request->user_bank_account != null ) {
            $user->userBankAccount()->create($request->user_bank_account);
        }
        $user->userWallet()->create(['total_amount' => 0 ]);

        $message = __('message.save_form',['form' => __('message.driver') ]);
        $user->api_token = $user->createToken('auth_token')->plainTextToken;
        $user->is_verified_driver = (int) $user->is_verified_driver;// DriverDocument::verifyDriverDocument($user->id);
        $user->profile_image = getSingleMedia($user, 'profile_image', null);
        $response = [
            'message' => $message,
            'data' => $user
        ];
        return json_custom_response($response);
    }    

    public function login(Request $request)
    {      
        if(Auth::attempt(['email' => request('email'), 'password' => request('password'), 'user_type' => request('user_type')])){
            
            $user = Auth::user();

            if( $user->status == 'banned' ) {
                $message = __('message.account_banned');
                return json_message_response($message,400);
            }

            if(request('player_id') != null){
                $user->player_id = request('player_id');
            }

            if(request('fcm_token') != null){
                $user->fcm_token = request('fcm_token');
            }
            
            $user->save();
            
            $success = $user;
            $success['api_token'] = $user->createToken('auth_token')->plainTextToken;
            $success['profile_image'] = getSingleMedia($user,'profile_image',null);
            $is_verified_driver = false;
            if($user->user_type == 'driver') {
                $is_verified_driver = $user->is_verified_driver; // DriverDocument::verifyDriverDocument($user->id);
            }
            $success['is_verified_driver'] = (int) $is_verified_driver;
            unset($success['media']);

            return json_custom_response([ 'data' => $success ], 200 );
        }
        else{
            $message = __('auth.failed');
            
            return json_message_response($message,400);
        }
    }

     public function loginRider(Request $request)
    {      
        if(Auth::attempt(['contact_number' => request('phone'), 'password' => request('password'), 'user_type' => request('user_type')])){
            
            $user = Auth::user();

            if( $user->status == 'banned' ) {
                $message = __('message.account_banned');
                return json_message_response($message,400);
            }

            if(request('player_id') != null){
                $user->player_id = request('player_id');
            }

            if(request('fcm_token') != null){
                $user->fcm_token = request('fcm_token');
            }
            
            $user->save();
            
            $success = $user; 
            $success['api_token'] = $user->createToken('auth_token')->plainTextToken;
            $success['profile_image'] = getSingleMedia($user,'profile_image',null);
            $is_verified_driver = false;
            if($user->user_type == 'driver') {
                $is_verified_driver = $user->is_verified_driver; // DriverDocument::verifyDriverDocument($user->id);
            }
            $success['is_verified_driver'] = (int) $is_verified_driver;
            unset($success['media']);

            return json_custom_response([ 'data' => $success ], 200 );
        }
        else{
            $message = __('auth.failed');
            
            return json_message_response($message,200);
        }
    }

        public function accountControlWithPhone(Request $request){    

         $result=User::where('contact_number',request('phone'))->first(); 

         if($result==null){
            return response()->json(['status' => 'error', 'message' => 'user_not_found'], 200);
         }else{
            return response()->json(['status' => 'success', 'message' => 'user_found'], 200);
          }
     

    }


// DRIVERS START



  public function loginDriver(Request $request)
    {      


        if(Auth::attempt(['contact_number' => request('phone'), 'password' => request('password'), 'user_type' => "driver"])){
            
            $user = Auth::user();

            if( $user->status == 'banned' ) {
                $message = __('message.account_banned');
                return json_message_response($message,400);
            }

            if(request('player_id') != null){
                $user->player_id = request('player_id');
            }

            if(request('fcm_token') != null){
                $user->fcm_token = request('fcm_token');  
            }
            
            $user->save();
            
            $success = $user;
            $success['api_token'] = $user->createToken('auth_token')->plainTextToken;
            $success['profile_image'] = getSingleMedia($user,'profile_image',null);
            $is_verified_driver = false;
            if($user->user_type == 'driver') {
                $is_verified_driver = $user->is_verified_driver; // DriverDocument::verifyDriverDocument($user->id);
            }
            $success['is_verified_driver'] = (int) $is_verified_driver;
            unset($success['media']);

            return json_custom_response([ 'data' => $success ], 200 );
        }
        else{
            $message = __('auth.failed');
            
              return json_custom_response([ 'data' => null ], 200 );
            // return json_message_response($message,400);
        }

$message=["data" => null,  "requests" => $request->all()];

return response($message,200);
// return json_message_response($message,200);

    }

  
  public function updateDriverLocation(Request $request){
    $currentDateTime = Carbon::now();
   $user=User::where("uid", $request->uid)->first();

   $user->latitude=$request->latitude;
   $user->longitude=$request->longitude;
   $user->last_location_update_at=$currentDateTime;
   $user->save();

   if($user){
    $data=["result" =>"success", "message" => "position updated"];
   }else{
    $data=["result" => null, "message" => "position not updated"]; 
   }


// $data=["result" =>"success"];
   return response()->json($data,200);

  }
    


  public function sendNewRequest(){

    $userLatitude="40.45665";
    $userLongitude="49.746952";


    // $distance=Setting::get(); 
    $distanceRadius=Setting::where("type","DISTANCE")->first();


    $nearestUsers = User::select('id', 'latitude', 'longitude')
    ->selectRaw(
        '(6371 * acos(cos(radians(' . $userLatitude . ')) * cos(radians(latitude)) * cos(radians(longitude) - radians(' . $userLongitude . ')) + sin(radians(' . $userLatitude . ')) * sin(radians(latitude)))) as distance'
    )
     ->whereNotNull('latitude')
    ->whereNotNull('longitude')
    ->where('is_online', 1)
    ->where('is_available', 1) 
    ->orderBy('distance', 'asc')
    ->limit(10)
    ->get();



$driverList=[];

foreach($nearestUsers as $drivers){
    $distance = $drivers->distance;  // Hesaplanan mesafeyi al
    if($distance < $distanceRadius->value) {
$driverList[]=[
    "id" => $drivers->id,
    "latitude"=> $drivers->latitude,
    "longitude" => $drivers->longitude, 
    "distance"=> number_format($drivers->distance, 2, '.',''),
    "radius" => $distanceRadius->value
];
}

}

$data=[
// "result" => $nearestUsers, 
"distance-radius" => Count($driverList),
"drivers" => $driverList 

];

return response()->json($data,200);


// En yakın kullanıcıları listele
// foreach ($nearestUsers as $user) {
//     // echo "Kullanıcı ID: " . $user->id . ", Mesafe: " . $user->distance  . " km <br>";
// }

// if($nearestUsers!=null){

// }else{
//     echo "nearest car not found";
// }

   }






    public function registerDriver(Request $request)
    {

     
    

   $driver=new User;


  $driverControl=User::where('email',$request->mail)->where('user_type','driver')->get();


    if(count($driverControl)==0){

    $test="Email not found";
 

     $driver->uid=mt_rand(11111111,99999999);
     $driver->first_name=$request->name;
     $driver->user_type="driver";
     $driver->last_name=$request->surname;
     $driver->contact_number=$request->phone;
     $driver->email=$request->mail;
     $driver->password=bcrypt($request->password);
     // $driver->username="";
     $driver->service_id=1;
     $driver->gender="male";
     $driver->email_verified_at=null;
     $driver->status="pending";
     $driver->is_online=0;
     $driver->is_available=0;  
     $driver->is_verified_driver=0;  
     $driver->save();


    }else{
$test="Email found";
$driver=null;
    }
  


$message=[
    "test" => $test,
    "result" => $driver,

    "requests" => $request->all()];

return response($message,200);
// return json_message_response($message,200);

 




    }


function getTokenRemaining($token)
{
    $remaining = substr($token, 7);
    return $remaining;
}

function joinTokenRemaining($token)
{  
    $defaultPrefix="$2y$10$";

    $remaining = $defaultPrefix.$token;
    return $remaining;
}

 public function forgetPasswordDriver(Request $request)
    {  

        $request->validate([
            'email' => 'required|email',
        ]);

        $getUser=User::where('email',$request->email)->where('user_type','driver')->first();


        if($request->type=="send"){

            if($getUser){

            $confirmationCode=mt_rand(111111,999999);  

        $userEmail = $request->email;   //'azad.habibullayev@gmail.com';
        $mailBody = "Təsdiqləmə kodu: ".$confirmationCode;

          Mail::raw($mailBody, function ($message) use ($userEmail) {
                  $message->to($userEmail)
                  ->subject('EgoTaxi Reset password'); 

           });

          $currentToken=$confirmationCode;
 

          $getUser->password_reset_token=$currentToken;
          $getUser->save(); 

          $data=["result" => "success",  ];
        }else{


        $data=["result" => null, "token" => null, "user" => $getUser ];

        } 



        }

        if($request->type=="confirm"){


            if($getUser->password_reset_token==$request->code){    


            $data=["result" => "success", 
             // "reset_sql" => $getUser->password_reset_token, 
             // "user-code" => $request->code,  
        ];   



            }else{
            
            $data=[
                "result" => null,  
                // "reset_sql" => $getUser->password_reset_token, 
                //  "user-code" => $request->code, 

                 ];

            }




         

        }


        if($request->type=="update-password"){


         $getUser->password=bcrypt($request->password);
         $getUser->save(); 

         if($getUser){
           
           $data=[  "result" => "success" ]; 

         }else{

           $data=[  "result" => null ];
         } 


        } 

      
          return response()->json($data,200); 



          // $data=["result" => null, "data" =>"" ];

          // return response()->json($data,200);











        // return $response == Password::RESET_LINK_SENT
        //     ? response()->json(['message' => __($response), 'status' => true], 200)
        //     : response()->json(['message' => __($response), 'status' => false], 400);


  // $data="dsfkjslkfjalksdfjakj asdj fkaslkdf asdf asd ";     

        // $userEmail =$request->email;
        // Mail::to($userEmail)->send(new sendMail);

    // $userEmail = 'azad.habibullayev@gmail.com';
    //     Mail::to($userEmail)->send(new SendMail());    



// $userEmail = 'azad.habibullayev@gmail.com';
// $mailBody = "Merhaba, Hoş geldiniz!";

// Mail::raw($mailBody, function ($message) use ($userEmail) {
//     $message->to($userEmail)
//             ->subject('Hoş Geldiniz');
// });

//         return "Welcome e-mail sent!"; 
  
    }







// change status

    public function changeDriverStatus(Request $request)
    { 
        $user=User::where("uid",$request->uid)->first();  
        if($user){ 
          $user->is_online=$request->status=="online"?1:0;
          $user->save();
          if($user){ 
            $data=["result" => "success", "response" => $request->status];
          }else{ 
            $data=["result" => null, "response" => $request->status];
          } 
        }else{ 
         $data=["result" => null, "response" =>  $request->status];  
        } 
        return response()->json($data,200);  
    }









// get details
    public function getDriverDetils(Request $request)
    { 
        $user=User::where("uid",$request->uid)->first();  
        
        if($user){  
          $wallet=Wallet::where("user_id",$user->id)->first();
          $vehicle=UserDetail::where("user_id",$user->id)->first();
          
            $data=["result" => "success", "user" => $user, "wallet" =>  $wallet, "vehicle" => $vehicle ];
          
        }else{ 
         $data=["result" => null, "user" => null, "wallet" =>  null,  "vehicle" => null];    
        } 

        // $data=["requests" => $request->all(), "user" => $user ]; 
        return response()->json($data,200);  
    }






// send notification

    // public function sendNotification(){  

    //   // $user=User::find(3);
    //   //  $status="test data";
    //   //   $notification_data = [
    //   //       'id'   => "idjkashdkajhsdljk",
    //   //       'type' => $status,
    //   //       'subject' => __('message.withdrawrequest'),
    //   //       'message' => "messagesasdad",
    //   //   ];    

    //   // $user->notify(new CommonNotification($notification_data['type'], $notification_data));  




 

 
    // $message = new FcmMessage();
    // $message->to('device_token')
    //         ->content([
    //             'title' => 'Başlık',
    //             'body' => 'Bildirim içeriği',
    //         ])
    //         ->priority(FcmMessage::PRIORITY_HIGH)
    //         ->timeToLive(0);

    // FcmNotification::send($message);
 

    // }




// DRIVERS END







    // 


    public function updateRiderInfo(Request $request){ 

      $driver=User::where('contact_number',$request->phone)->where('user_type','rider')->first();
      
      $result=["result"=> $driver];
 
      if($result["result"]!=null){   
      // $driver->first_name=$request->first_name;
      // $driver->last_name=$request->last_name;
      // $driver->save();
       
 
      }else{
      
      $newDriver=new User;   
      $newDriver->contact_number=$request->phone;
      $newDriver->first_name=$request->first_name;
      $newDriver->last_name=$request->last_name; 
      $newDriver->user_type="rider";
      $newDriver->username="";
      $newDriver->email=$request->email;
      $newDriver->password=bcrypt("12345678");
      $newDriver->save();  

        return response()->json(["result" => $newDriver],200); 
      }    
      
      // $driver->first_name=$request->first_name;
      // $driver->last_name=$request->last_name;
      // $driver->save();
  
      return response()->json($result,200);  

    }






    public function userList(Request $request)
    {
        $user_type = isset($request['user_type']) ? $request['user_type'] : 'rider';
        
        $user_list = User::query();
        
        $user_list->when(request('user_type'), function ($q) use($user_type) {
            return $q->where('user_type', $user_type);
        });

        $user_list->when(request('fleet_id'), function ($q) {
            return $q->where('fleet_id', request('fleet_id'));
        });

        if( $request->has('is_online') && isset($request->is_online) )
        {
            $user_list = $user_list->where('is_online',request('is_online'));
        }
        
        if( $request->has('status') && isset($request->status) )
        {
            $user_list = $user_list->where('status',request('status'));
        }

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page))
        {
            if(is_numeric($request->per_page)){
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $user_list->count();
            }
        }
        
        $user_list = $user_list->paginate($per_page);

        if( $user_type == 'driver' ) {
            $items = DriverResource::collection($user_list);
        } else {
            $items = UserResource::collection($user_list);
        }

        $response = [
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ];
        
        return json_custom_response($response);
    }

    public function userDetail(Request $request)
    {
        $id = $request->id;

        $user = User::where('id',$id)->first();
        if(empty($user))
        {
            $message = __('message.user_not_found');
            return json_message_response($message,400);   
        }

        $response = [
            'data' => null,
        ];
        if( $user->user_type == 'driver') {
            $user_detail = new DriverResource($user);

            $response = [
                'data' => $user_detail,
                'required_document' => driver_required_document($user),
            ];
        } else {
            $user_detail = new UserResource($user);
            $response = [
                'data' => $user_detail
            ];
        }

        return json_custom_response($response);

    }

    public function changePassword(Request $request){
        $user = User::where('id',Auth::user()->id)->first();

        if($user == "") {
            $message = __('message.user_not_found');
            return json_message_response($message,400);   
        }
           
        $hashedPassword = $user->password;

        $match = Hash::check($request->old_password, $hashedPassword);

        $same_exits = Hash::check($request->new_password, $hashedPassword);
        if ($match)
        {
            if($same_exits){
                $message = __('message.old_new_pass_same');
                return json_message_response($message,400);
            }

			$user->fill([
                'password' => Hash::make($request->new_password)
            ])->save();
            
            $message = __('message.password_change');
            return json_message_response($message,200);
        }
        else
        {
            $message = __('message.valid_password');
            return json_message_response($message,400);
        }
    }

    public function updateProfile(UserRequest $request)
    {   
        $user = Auth::user();
        if($request->has('id') && !empty($request->id)){
            $user = User::where('id',$request->id)->first();
        }
        if($user == null){
            return json_message_response(__('message.no_record_found'),400);
        }

        $user->fill($request->all())->update();

        if(isset($request->profile_image) && $request->profile_image != null ) {
            $user->clearMediaCollection('profile_image');
            $user->addMediaFromRequest('profile_image')->toMediaCollection('profile_image');
        }

        $user_data = User::find($user->id);
        
        if($user_data->userDetail != null && $request->has('user_detail') ) {
            $user_data->userDetail->fill($request->user_detail)->update();
        } else if( $request->has('user_detail') && $request->user_detail != null ) {
            $user_data->userDetail()->create($request->user_detail);
        }
        
        if($user_data->userBankAccount != null && $request->has('user_bank_account')) {
            $user_data->userBankAccount->fill($request->user_bank_account)->update();
        } else if( $request->has('user_bank_account') && $request->user_bank_account != null ) {
            $user_data->userBankAccount()->create($request->user_bank_account);
        }
        
        $message = __('message.updated');
        // $user_data['profile_image'] = getSingleMedia($user_data,'profile_image',null);
        unset($user_data['media']);

        if( $user_data->user_type == 'driver') {
            $user_resource = new DriverResource($user_data);
        } else {
            $user_resource = new UserResource($user_data);
        }

        $response = [
            'data' => $user_resource,
            'message' => $message
        ];
        return json_custom_response( $response );
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if($request->is('api*')){
            $clear = request('clear');
            if( $clear != null ) {
                $user->$clear = null;
            }
            $user->save();
            return json_message_response('Logout successfully');
        }
    }

    public function forgetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $response = Password::sendResetLink(
            $request->only('email')
        );

        return $response == Password::RESET_LINK_SENT
            ? response()->json(['message' => __($response), 'status' => true], 200)
            : response()->json(['message' => __($response), 'status' => false], 400);
    }
    
    public function socialLogin(Request $request)
    {
        $input = $request->all();

        if($input['login_type'] === 'mobile'){
            $user_data = User::where('username', $input['username'])->where('login_type','mobile')->first();
        } else {
            $user_data = User::where('email',$input['email'])->first();
        }
        
        if( $user_data != null ) {
            if( !in_array($user_data->user_type, ['admin',request('user_type')] )) {
                $message = __('auth.failed');
                return json_message_response($message,400);
            }

            if( $user_data->status == 'banned' ) {
                $message = __('message.account_banned');
                return json_message_response($message,400);
            }
        
            if( !isset($user_data->login_type) || $user_data->login_type  == '' )
            {
                if($request->login_type === 'google')
                {
                    $message = __('validation.unique',['attribute' => 'email' ]);
                } else {
                    $message = __('validation.unique',['attribute' => 'username' ]);
                }
                return json_message_response($message,400);
            }
            $message = __('message.login_success');
        } else {

            if($request->login_type === 'google')
            {
                $key = 'email';
                $value = $request->email;
            } else {
                $key = 'username';
                $value = $request->username;
            }

            if($request->login_type === 'mobile' && $user_data == null ){
                $otp_response = [
                    'status' => true,
                    'is_user_exist' => false
                ];
                return json_custom_response($otp_response);
            }
            
            $validator = Validator::make($input,[
                'email' => 'required|email|unique:users,email',
                'username'  => 'required|unique:users,username',
                'contact_number' => 'max:20|unique:users,contact_number',
            ]);

            if ( $validator->fails() ) {
                $data = [
                    'status' => false,
                    'message' => $validator->errors()->first(),
                    'all_message' =>  $validator->errors()
                ];
    
                return json_custom_response($data, 422);
            }

            $password = !empty($input['accessToken']) ? $input['accessToken'] : $input['email'];

            $input['display_name'] = $input['first_name']." ".$input['last_name'];
            $input['password'] = Hash::make($password);
            $input['user_type'] = isset($input['user_type']) ? $input['user_type'] : 'rider';
            $user = User::create($input);
            if($user->userWallet == null) {
                $user->userWallet()->create(['total_amount' => 0 ]);
            }
            $user->assignRole($input['user_type']);

            $user_data = User::where('id',$user->id)->first();
            $message = __('message.save_form',['form' => $input['user_type'] ]);
        }

        $user_data['api_token'] = $user_data->createToken('auth_token')->plainTextToken;
        $user_data['profile_image'] = getSingleMedia($user_data, 'profile_image', null);

        $is_verified_driver = false;
        if($user_data->user_type == 'driver') {
            $is_verified_driver = $user_data->is_verified_driver; // DriverDocument::verifyDriverDocument($user_data->id);
        }
        $user_data['is_verified_driver'] = (int) $is_verified_driver;
        $response = [
            'status' => true,
            'message' => $message,
            'data' => $user_data
        ];
        return json_custom_response($response);
    }

    public function updateUserStatus(Request $request)
    {
        $user_id = $request->id ?? auth()->user()->id;
        
        $user = User::where('id',$user_id)->first();

        if($user == "") {
            $message = __('message.user_not_found');
            return json_message_response($message,400);
        }
        if($request->has('status')) {
            $user->status = $request->status;
        }
        if($request->has('is_online')) {
            $user->is_online = $request->is_online;
        }
        if($request->has('is_available')) {
            $user->is_available = $request->is_available;
        }
        if($request->has('latitude')) {
            $user->latitude = $request->latitude;
        }
        if($request->has('longitude')) {
            $user->longitude = $request->longitude;
        }
        if($request->has('latitude') && $request->has('longitude') ) {
            $user->last_location_update_at = date('Y-m-d H:i:s');
        }
        if($request->has('player_id')) {
            $user->player_id = $request->player_id;
        }
        $user->save();

        if( $user->user_type == 'driver') {
            $user_resource = new DriverResource($user);
        } else {
            $user_resource = new UserResource($user);
        }
        $message = __('message.update_form',['form' => __('message.status') ]);
        $response = [
            'data' => $user_resource,
            'message' => $message
        ];
        return json_custom_response($response);
    }

    public function updateAppSetting(Request $request)
    {
        $data = $request->all();
        AppSetting::updateOrCreate(['id' => $request->id],$data);
        $message = __('message.save_form',['form' => __('message.app_setting') ]);
        $response = [
            'data' => AppSetting::first(),
            'message' => $message
        ];
        return json_custom_response($response);
    }

    public function getAppSetting(Request $request)
    {
        if($request->has('id') && isset($request->id)){
            $data = AppSetting::where('id',$request->id)->first();
        } else {
            $data = AppSetting::first();
        }

        return json_custom_response($data);
    }

    public function deleteUserAccount(Request $request)
    {
        $id = auth()->id();
        $user = User::where('id', $id)->first();
        $message = __('message.not_found_entry',['name' => __('message.account') ]);

        if( $user != '' ) {
            $user->delete();
            $message = __('message.account_deleted');
        }
        
        return json_custom_response(['message'=> $message, 'status' => true]);
    }
}
