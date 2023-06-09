<?php  

use Illuminate\Support\Facades\Http;
 
  
  
 function sendNotify($deviceToken_,$myTitle,$myBody){

   
   $deviceToken=$deviceTOken_;


    $title = $myTitle;
    $body = $myBody; 

    $response = Http::withHeaders([
        'Authorization' => 'key=AAAACMPNeE0:APA91bFusF5H6zTmWUqVJ3UXhJLN_oUAjQdK8BFqpN7r5L2YjTroISZkyndMoLdCqo_I5MrzSpHgppn6drDm3X2TuG-xBnx_MuKy0l9eH3OrTuhdsk4zw9rG98NJr9wGv98tPSWUGjyz',
        'Content-Type' => 'application/json',
    ])->post('https://fcm.googleapis.com/fcm/send', [
        'to' => $deviceToken,
        'notification' => [  
            'title' => $title,
            'body' => $body,   
        ],
    ]);

    if ($response->successful()) {
        // Bildirim başarıyla gönderildi
        // İstediğiniz işlemleri yapabilirsiniz
    } else {
        // Bildirim gönderme hatası
        $error = $response->json('error');
        // Hata mesajını kullanabilir veya loglayabilirsiniz
    }


    return $response;




}


 