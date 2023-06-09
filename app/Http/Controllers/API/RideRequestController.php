<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RideRequest;
use App\Models\Service; 
use App\Models\User; 
use App\Models\RideRequestRating;
use App\Models\Coupon;
use App\Models\EgotaxiRideRequest;
use App\Http\Resources\RideRequestResource;
use App\Http\Resources\ComplaintResource;
use Carbon\Carbon;
use App\Models\Payment;
use App\Jobs\NotifyViaMqtt;
use Illuminate\Support\Facades\Http;
use Validator;

use App\Helpers\helper;
use App\Models\Setting;  

class RideRequestController extends Controller
{


    public function calculateRideRequestPrice(Request $request)
    {

        $startLat=$request->startLat??0.0;
        $startLong=$request->startLong??0.0;
        $drop1Lat=$request->drop1Lat??0.0;
        $drop1Long=$request->drop1Long??0.0;
        $drop2Lat=$request->drop2Lat??0.0;
        $drop2Long=$request->drop2Long??0.0;
        $drop3Lat=$request->drop3Lat??0.0;
        $drop3Long=$request->drop3Long??0.0;

        $destinationDistance_1=0;
        $destinationDistance_2=0;
        $destinationDistance_3=0;

    
    // $matrix =   mighty_get_distance_matrix(40.45552,49.74692,40.26335,49.74159);

    // $matrix =   mighty_get_distance_matrix($startLat,$startLong,$drop1Lat,$drop1Long); 

      if($startLat!=0.0 && $drop1Lat!=0.0){

      // $matrixDistance_1 =   egotaxi_get_distance_matrix($startLat,$startLong,$drop1Lat,$drop1Long);
      // $destinationDistance_1= distance_value_from_distance_matrix($matrixDistance_1);

      $matrixDistance_1 =   egotaxi_get_distance_between($startLat,$startLong,$drop1Lat,$drop1Long);
      $destinationDistance_1= $matrixDistance_1;
      }



      if($drop1Lat!=0.0 && $drop2Lat!=0.0){
      // $matrixDistance_2 =   egotaxi_get_distance_matrix($drop1Lat,$drop1Long,$drop2Lat,$drop2Long);
      // $destinationDistance_2= distance_value_from_distance_matrix($matrixDistance_2);

      $matrixDistance_2 =   egotaxi_get_distance_between($drop1Lat,$drop1Long,$drop2Lat,$drop2Long);
      $destinationDistance_2=$matrixDistance_2;

      }

      if($drop2Lat!=0.0 && $drop3Lat!=0.0){

      // $matrixDistance_3 =   egotaxi_get_distance_matrix($drop2Lat,$drop2Long,$drop3Lat,$drop3Long);
      //  $destinationDistance_3= distance_value_from_distance_matrix($matrixDistance_3);

      $matrixDistance_3 =   egotaxi_get_distance_between($drop2Lat,$drop2Long,$drop3Lat,$drop3Long);
       $destinationDistance_3=$matrixDistance_3;



      }
      

      $finalDistance=$destinationDistance_1+$destinationDistance_2+$destinationDistance_3;

      $distanceInMeters =  $finalDistance; // Metre cinsinden mesafe

// Metreden kilometreye dönüştürme
// $distanceInKilometers = $distanceInMeters / 1000;

// Sonucu istediğiniz format olan 40.68 şeklinde düzenleme
// $distanceFormatted = number_format($distanceInKilometers, 2, '.', '');

$distanceFormatted = number_format($distanceInMeters, 2, '.','');




$serviceList=Service::all();

$services=[];

foreach($serviceList as $list){

    $serviceImage="";

if($list['id']==1){

$serviceImage="https://egotaxi.buildappaz.com/images/1/vehicle_type_economy.png";

}

if($list['id']==2){

$serviceImage="https://egotaxi.buildappaz.com/images/2/vehicle_type_premium.png";

}

if($list['id']==3){

$serviceImage="https://egotaxi.buildappaz.com/images/3/vehicle_type_vip.png"; 

}

$finalPRice=number_format($list['per_distance']*$distanceFormatted, 2, '.','');


$services[]=array(
    'id' => $list['id'],
    'name' => $list['name'], 
    'price' => $finalPRice,  
    'distance' => $distanceFormatted,
    'per_distance' =>  $list['per_distance'], 
    'base_fare' => $list['base_fare'],
    'minimum_fare' => $list['minimum_fare'],
    'minimum_distance' => $list['minimum_distance'],
    'service_image' => $serviceImage, 
    'price_per_km' => '0.0'

     );

}


$data=[
    // 'data'=>"trsfsdjksjjh",
    // 'data'=>$matrixDistance_1,
    // 'data2'=>$matrixDistance_2,
    // 'distance1' => $matrixDistance_1['rows'][0]['elements'][0]['distance']['value'],
    // 'distance2' => $finalDistance,
    "services" => $services,
    "final_distance" => $distanceFormatted,  
    'services-all' => $serviceList,

];

return response()->json($data, 200);

    }




    public function saveNewRideRequest(Request $request)
    {

        // $startLat=$request->startLat??0.0;
        // $startLong=$request->startLong??0.0;
        // $drop1Lat=$request->drop1Lat??0.0;
        // $drop1Long=$request->drop1Long??0.0;
        // $drop2Lat=$request->drop2Lat??0.0;
        // $drop2Long=$request->drop2Long??0.0;
        // $drop3Lat=$request->drop3Lat??0.0;
        // $drop3Long=$request->drop3Long??0.0;


    $newRideRequest=new EgotaxiRideRequest; 
    $newRideRequest->rider_id=2;
    $newRideRequest->service_id=$request->service_id;
    $newRideRequest->ride_request_id=mt_rand(11111111,99999999);
    $newRideRequest->destinations=json_encode($request->ride_destinations);
    $newRideRequest->distance=$request->distance;
    $newRideRequest->price=$request->price;
    $newRideRequest->status="waiting"; 
    $newRideRequest->save();


   


    if($newRideRequest){
     
      $nearestResult= $this->searchNearestDirvers($request->ride_destinations[0]['lat'], $request->ride_destinations[0]['long']);
    
    
    //   // $nearestResult->ride_request_id;


          $data=[
        "result" => "success",
        "near_drivers" => $nearestResult,  
        "request_status" => $newRideRequest,
        // "data" => EgotaxiRideRequest::get(),
        // "result" => $request->all(),

    ];

     $user=User::find($nearestResult['drivers'][0]['id']);


    $response=["type" => "REQUEST","request_status" => 'ADDED', "user" => $user->uid, "request_detail" => $newRideRequest  ];

      $response= sendNotify($user->fcm_token, "EgoTAxi Driver", $response); 

    }else{

    $data=[
        "result" => null,
        "request_status" => $newRideRequest,
        // "data" => EgotaxiRideRequest::get(),
        // "result" => $request->all(),

    ];


    }
  
     //   $data=[
     //    "result" => "success",
     //    "request_status" => $newRideRequest,
     //    // "data" => EgotaxiRideRequest::get(),
     //    // "result" => $request->all(),
     // ];

    return response()->json($data,200);






    }


  

  public function searchNearestDirvers($lat,$lng){


     $userLatitude=$lat; //"40.45665";        
    $userLongitude=$lng; //"49.746952";        


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
"drivers" => $driverList,
"selected_driver_id" => Count($driverList)>0?$driverList[0]['id']:0,
"selected_driver" => User::find($driverList[0]['id']),

];

   return $data;     


  }

public function changeRiderequestStatusInDriver(Request $request)
    {
 
     $rideRequest=EgotaxiRideRequest::where("ride_request_id",$request->request_id)->first();

     if($request->method=="CANCELL"){

        $rideRequest->status="CANCELLED";
        $rideRequest->save();
        
        if($rideRequest){
            $data=[
            'requestStatus' => $request->method,
            "result" => "success",
            "driver_id" => $request->driver_id
            ];
             
             // if($request->driver_id!="0"){
             //   $user=User::find($request->driver_id);
             //   if($user){
             //    $response=["type" => "REQUEST","request_status" => 'CANCELL', "user" => $user->uid];
             //   $response= sendNotify($user->fcm_token, "EgoTAxi Driver", $response); 
             //   }
             // } 
        }else{
            $data=[
            'requestStatus' => $request->method,
            "result" => null,
            ];
        } 

     
     }
     if($request->method=="ACCEPT"){

        $rideRequest->status="ACCEPTED";
        $rideRequest->save();
        
        if($rideRequest){
            $data=[
            'requestStatus' => $request->method,
            "result" => "success",
            "driver_id" => $request->driver_id
            ];
             
             // if($request->driver_id!="0"){
             //   $user=User::find($request->driver_id);
             //   if($user){
             //    $response=["type" => "REQUEST","request_status" => 'CANCELL', "user" => $user->uid];
             //   $response= sendNotify($user->fcm_token, "EgoTAxi Driver", $response); 
             //   }
             // } 
        }else{
            $data=[
            'requestStatus' => $request->method,
            "result" => null,
            ];
        } 

     
     }


     else{

        $data=[
            'requestStatus' => $request->method,
            "result" => null];
     } 
    return response()->json($data,200); 
    }

    public function changeRiderequestStatus(Request $request)
    {
 
     $rideRequest=EgotaxiRideRequest::where("ride_request_id",$request->riderequest_id)->first();

     if($request->method=="CANCELL"){

        $rideRequest->status="CANCELLED";
        $rideRequest->save();
        
        if($rideRequest){
            $data=[
            "result" => "success",
            "driver_id" => $request->driver_id
            ];
             
             if($request->driver_id!="0"){
               $user=User::find($request->driver_id);
               if($user){
                $response=["type" => "REQUEST","request_status" => 'CANCELL', "user" => $user->uid];
               $response= sendNotify($user->fcm_token, "EgoTAxi Driver", $response); 
               }
             } 
        }else{
            $data=[
            "result" => null,
            ];
        } 
     }else{

        $data=["result" => null];
     } 
    return response()->json($data,200); 
    }



public function currentRiderequestStatus(Request $request)
    {

        // $startLat=$request->startLat??0.0;
        // $startLong=$request->startLong??0.0;
        // $drop1Lat=$request->drop1Lat??0.0;
        // $drop1Long=$request->drop1Long??0.0;
        // $drop2Lat=$request->drop2Lat??0.0;
        // $drop2Long=$request->drop2Long??0.0;
        // $drop3Lat=$request->drop3Lat??0.0;
        // $drop3Long=$request->drop3Long??0.0;



    $data=[

        "result" => $request->all(),

    ];

    

    return response()->json($data,200);






    }



    public function getList(Request $request)
    {
        $riderequest = RideRequest::query();

        $riderequest->when(request('service_id'), function ($q) {
            return $q->where('service_id', request('service_id'));
        });

        $riderequest->when(request('is_schedule'), function ($q) {
            return $q->where('is_schedule', request('is_schedule'));
        });

        $riderequest->when(request('rider_id'), function ($q) {
            return $q->where('rider_id',request('rider_id'));
        });

        $riderequest->when(request('driver_id'), function ($query) {
            return $query->whereHas('driver',function ($q) {
                $q->where('driver_id',request('driver_id'));
            });
        });
        $order = 'desc';
        $riderequest->when(request('status'), function ($query) {
            if( request('status') == 'upcoming' ) {
                return $query->where('datetime', '>=', Carbon::now()->format('Y-m-d H:i:s'));
            } else if( request('status') == 'canceled' ) {
                return $query->whereIn('status',['canceled']);
            } else {
                return $query->where('status', request('status'));
            }
        });

        if( request('from_date') != null && request('to_date') != null ){
            $riderequest = $riderequest->whereBetween('datetime',[ request('from_date'), request('to_date')]);
        }

        $per_page = config('constant.PER_PAGE_LIMIT');
        if( $request->has('per_page') && !empty($request->per_page)){
            if(is_numeric($request->per_page))
            {
                $per_page = $request->per_page;
            }
            if($request->per_page == -1 ){
                $per_page = $riderequest->count();
            }
        }
        if( request('status') == 'upcoming' ) {
            $order = 'asc';
        }
        $riderequest = $riderequest->orderBy('datetime',$order)->paginate($per_page);
        $items = RideRequestResource::collection($riderequest);

        $response = [
            'pagination' => json_pagination_response($items),
            'data' => $items,
        ];
        
        return json_custom_response($response);
    }

    public function getDetail(Request $request)
    {
        $id = $request->id;
        $riderequest = RideRequest::where('id',$id)->first();
        
        if( $riderequest == null )
        {
            return json_message_response( __('message.not_found_entry',['name' => __('message.riderequest') ]) );
        }
        $ride_detail = new RideRequestResource($riderequest);

        $ride_history = optional($riderequest)->rideRequestHistory;
        $rider_rating = optional($riderequest)->rideRequestRiderRating();
        $driver_rating = optional($riderequest)->rideRequestDriverRating();

        $current_user = auth()->user();
        if(count($current_user->unreadNotifications) > 0 ) {
            $current_user->unreadNotifications->where('data.id',$id)->markAsRead();
        }

        $complaint = null;  
        if($current_user->hasRole('driver')) {
            $complaint = optional($riderequest)->rideRequestDriverComplaint();
        }

        if($current_user->hasRole('rider')) {
            $complaint = optional($riderequest)->rideRequestRiderComplaint();
        }

        $response = [
            'data' => $ride_detail,
            'ride_history' => $ride_history,
            'rider_rating' => $rider_rating,
            'driver_rating' => $driver_rating,
            'complaint' => isset($complaint) ? new ComplaintResource($complaint) : null,
            'payment' => optional($ride_detail)->payment,
            // 'region' => optional($ride_detail)->service_data['region'] 
        ];

        return json_custom_response($response);
    }

    public function completeRideRequest(Request $request)
    {
        $id = $request->id;
        $ride_request = RideRequest::where('id',$id)->first();
        // \Log::info('riderequest:'.json_encode($request->all()));
        if( $ride_request == null ) {
            return json_message_response( __('message.not_found_entry',['name' => __('message.riderequest') ]) );
        }

        if( $ride_request->status == 'completed' ) {
            return json_message_response( __('message.ride.completed'));
        }

        $ride_request->update([
            'end_latitude'  => $request->end_latitude,
            'end_longitude' => $request->end_longitude,
            'end_address'   => $request->end_address,
            'extra_charges' => $request->extra_charges,
            'extra_charges_amount'  => $request->extra_charges_amount
        ]);

        $distance_unit = $ride_request->distance_unit ?? 'km';
        $distance = $request->distance;

        if( $distance_unit == 'mile' ) {
            $distance = km_to_mile($distance);
        }
        $service = $ride_request->service;

        $start_datetime = $ride_request->rideRequestHistory()->where('history_type', 'in_progress')->pluck('datetime')->first();
        
        $duration = calculateRideDuration($start_datetime);

        $arrived_datetime = $ride_request->riderequest_history_data('arrived');

        $waiting_time = calculateRideDuration($start_datetime, $arrived_datetime);

        $waiting_time = $waiting_time - ($service->waiting_time_limit ?? 0);
        $waiting_time = $waiting_time < 0 ? 0 : $waiting_time;

        
        $ride_request->update([
            'status' => 'completed',
            'distance' => $distance,
            'duration' => $duration,
            'service_data' => $service,
        ]);

        $history_data = [
            'history_type'      => 'completed',
            'ride_request_id'   => $ride_request->id,
            'ride_request'      => $ride_request,
        ];

        $current_date = Carbon::today()->toDateTimeString();
        $coupon = Coupon::where('id', $ride_request->coupon_code)->where('start_date', '<=',$current_date)->where('end_date', '>=',$current_date)->first();
        $extra_charges_amount = $request->has('extra_charges_amount') ? request('extra_charges_amount') : 0;
        $ridefee = $this->calculateRideFares($service, $distance, $duration, $waiting_time, $extra_charges_amount, $coupon);

        $ridefee['waiting_time_limit'] = $service->waiting_time_limit;
        $ridefee['per_minute_drive'] = $service->per_minute_drive;
        $ridefee['per_minute_waiting'] = $service->per_minute_wait;
        if( $ride_request->is_ride_for_other == 1 ) {
            $ridefee['is_rider_rated'] = true;
        }
        $ride_request->update($ridefee);

        $payment_data = [
            'rider_id'          => $ride_request->rider_id,
            'ride_request_id'   => $ride_request->id,
            'payment_type'      => $ride_request->payment_type ?? 'cash',
            'datetime'          => date('Y-m-d H:i:s'),
            'payment_status'    => 'pending',
            'total_amount'      => $ride_request->subtotal, // discount
        ];

        Payment::create($payment_data);

        saveRideHistory($history_data);
        // update driver is_available
        $ride_request->driver->update(['is_available' => 1]);
        return json_message_response( __('message.ride.completed'));
    }

    public function calculateRideFares($service, $distance, $duration, $waiting_time, $extra_charges_amount, $coupon )
    {
        // distance price
        $per_minute_drive_charge = 0;

        $per_minute_drive_charge = $duration * $service->per_minute_drive;
        if( $distance > $service->minimum_distance ) {
            $distance = $distance - $service->minimum_distance;
        }
        $per_distance_charge = $distance * $service->per_distance;

        $per_minute_waiting_charge = $waiting_time * $service->per_minute_wait;
        
        $base_fare = $service->base_fare;
        $total_amount = $base_fare + $per_distance_charge + $per_minute_drive_charge + $per_minute_waiting_charge + $extra_charges_amount ;

        if( $service->commission_type == 'fixed' ) {
            $commission = $service->admin_commission + $service->fleet_commission;
            if( $total_amount <= $commission) {
                $total_amount += $commission;
            }
        }
        $subtotal = $total_amount;

        // Check for coupon data
        $discount_amount = 0;
        if ($coupon) {
            if ($coupon->minimum_amount < $total_amount) {
                
                if( $coupon->discount_type == 'percentage' ) {
                    $discount_amount = $total_amount * ($coupon->discount/100);
                } else {
                    $discount_amount = $coupon->discount;
                }

                if ($coupon->maximum_discount > 0 && $discount_amount > $coupon->maximum_discount) {
                    $discount_amount = $coupon->maximum_discount;
                }
                $subtotal = $total_amount - $discount_amount;
            }
        }

        return [
            'base_fare'                 => $base_fare,
            'minimum_fare'              => $service->minimum_fare,
            'base_distance'             => $service->minimum_distance,
            'per_distance'              => $service->per_distance,
            'per_distance_charge'       => $per_distance_charge,
            'per_minute_drive_charge'   => $per_minute_drive_charge,
            'waiting_time'              => $waiting_time,
            'per_minute_waiting_charge' => $per_minute_waiting_charge,
            'subtotal'                  => $subtotal,
            'total_amount'              => $total_amount,
            'extra_charges_amount'      => $extra_charges_amount,
            'coupon_discount'           => $discount_amount,
        ];
    }

    public function verifyCoupon(Request $request)
    {
        $coupon_code = $request->coupon_code;

        $coupon = Coupon::where('code', $coupon_code)->first();
        $status = isset($coupon_code) ? 400 : 200;
        
        if($coupon != null) {
            $status = Coupon::isValidCoupon($coupon);
        }
        
        $response = couponVerifyResponse($status);

        return json_custom_response($response,$status);
    }

    public function rideRating(Request $request)
    {
        $ride_request = RideRequest::where('id',request('ride_request_id'))->first();

        $message = __('message.not_found_entry', ['name' => __('message.riderequest')]);

        if($ride_request == '') {
            return json_message_response( $message );
        }
        $data = $request->all();

        $data['rider_id'] = auth()->user()->user_type == 'driver' ? $ride_request->rider_id : null;
        $data['driver_id'] = auth()->user()->user_type == 'rider' ? $ride_request->driver_id : null;

        $data['rating_by'] = auth()->user()->user_type;
        RideRequestRating::updateOrCreate([ 'id' => $request->id ], $data);
        
        if(auth()->user()->hasRole('rider')) {
            $ride_request->update(['is_rider_rated' => true]);
            $msg = __('message.rated_successfully', ['form' => __('message.rider')]);
        }
        if(auth()->user()->hasRole('driver')) {
            $ride_request->update(['is_driver_rated' => true]);
            $msg = __('message.rated_successfully', ['form' => __('message.driver')]);
        }
        if($ride_request->payment->payment_status == 'pending' && $request->has('tips') && request('tips') != null) {
            $ride_request->update(['tips' => request('tips')]);
        }

        $notify_data = new \stdClass();
        $notify_data->success = true;
        $notify_data->success_type = 'rating';
        $notify_data->success_message = $msg;
        $notify_data->result = new RideRequestResource($ride_request);

        if( auth()->user()->hasRole('driver') ) {
            dispatch(new NotifyViaMqtt('ride_request_status_'.$ride_request->rider_id, json_encode($notify_data)));
        }

        if( auth()->user()->hasRole('rider') ) {
            dispatch(new NotifyViaMqtt('ride_request_status_'.$ride_request->driver_id, json_encode($notify_data)));
        }

        $message = __('message.save_form',[ 'form' => __('message.rating') ] );
        
        return json_message_response($message);
    }
    
    public function placeAutoComplete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search_text' => 'required',
            'language' => 'required'
        ]);

        if ( $validator->fails() ) {
            $data = [
                'status' => 'false',
                'message' => $validator->errors()->first(),
                'all_message' =>  $validator->errors()
            ];

            return json_custom_response($data,400);
        }
        
        $google_map_api_key = env('GOOGLE_MAP_KEY');
        
        $response = Http::withHeaders([
            'Accept-Language' => request('language'),
        ])->get('https://maps.googleapis.com/maps/api/place/autocomplete/json?input='.request('search_text').'&key='.$google_map_api_key);

        return $response->json();
    }

    public function placeDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'placeid' => 'required',
        ]);

        if ( $validator->fails() ) {
            $data = [
                'status' => 'false',
                'message' => $validator->errors()->first(),
                'all_message' =>  $validator->errors()
            ];

            return json_custom_response($data,400);
        }
        
        $google_map_api_key = env('GOOGLE_MAP_KEY');
        $response = Http::get('https://maps.googleapis.com/maps/api/place/details/json?placeid='.$request->placeid.'&key='.$google_map_api_key);

        return $response->json();
    }
}
