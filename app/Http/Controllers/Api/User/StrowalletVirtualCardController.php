<?php

namespace App\Http\Controllers\Api\User;

use App\Models\UserWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\VirtualCardApi;
use App\Http\Helpers\Api\Helpers;
use App\Models\Admin\BasicSettings;
use App\Http\Controllers\Controller;
use App\Models\StrowalletVirtualCard;
use App\Models\Admin\TransactionSetting;
use Illuminate\Support\Facades\Validator;

class StrowalletVirtualCardController extends Controller
{
    protected $api;
    public function __construct()
    {
        $cardApi = VirtualCardApi::first();
        $this->api =  $cardApi;
    }
    public function index()
    {
        $user = auth()->user();
        
        $basic_settings = BasicSettings::first();
        $card_basic_info = [
            'card_back_details' => @$this->api->card_details,
            'card_bg' => get_image(@$this->api->image,'card-api'),
            'site_title' =>@$basic_settings->site_name,
            'site_logo' =>get_logo(@$basic_settings,'dark'),
            'site_fav' =>get_fav($basic_settings,'dark'),
        ];
        $myCards = StrowalletVirtualCard::where('user_id',$user->id)->orderBy('id','DESC')->get()->map(function($data){
            $basic_settings = BasicSettings::first();
            $statusInfo = [
                "block" =>      0,
                "unblock" =>     1,
                ];
            return[
                'id' => $data->id,
                'name' => $data->name_on_card,
                'card_id' => $data->card_id,
                'expiry' => $data->expiry,
                'cvv' => $data->cvv,
                'balance' => getAmount($data->balance,2),
                'card_back_details' => @$this->api->card_details,
                'site_title' =>@$basic_settings->site_name,
                'site_logo' =>get_logo(@$basic_settings,'dark'),
                'site_fav' =>get_fav($basic_settings,'dark'),
                'status' => $data->is_active,
                'status_info' =>(object)$statusInfo ,
            ];
        });
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->get()->map(function($data){

            return [
                'id' => $data->id,
                'slug' => $data->slug,
                'title' => $data->title,
                'fixed_charge' => getAmount($data->fixed_charge,2),
                'percent_charge' => getAmount($data->percent_charge,2),
                'min_limit' => getAmount($data->min_limit,2),
                'max_limit' => getAmount($data->max_limit,2),
            ];
        })->first();
        $transactions = Transaction::auth()->virtualCard()->latest()->get()->map(function($item){
            $statusInfo = [
                "success" =>      1,
                "pending" =>      2,
                "rejected" =>     3,
                ];
                
            return[
                'id' => $item->id,
                'trx' => $item->trx_id,
                'transactin_type' => "Virtual Card".'('. @$item->remark.')',
                'request_amount' => getAmount($item->request_amount,2).' '.get_default_currency_code() ,
                'payable' => getAmount($item->payable,2).' '.get_default_currency_code(),
                'total_charge' => getAmount($item->charge->total_charge,2).' '.get_default_currency_code(),
                'card_amount' => getAmount(@$item->details->card_info->balance,2).' '.get_default_currency_code(),
                'card_number' => $item->details->card_info->card_number ,
                'current_balance' => getAmount($item->available_balance,2).' '.get_default_currency_code(),
                'status' => $item->stringStatus->value ,
                'date_time' => $item->created_at ,
                'status_info' =>(object)$statusInfo ,

            ];
        });
        $userWallet = UserWallet::where('user_id',$user->id)->get()->map(function($data){
            return[
                'balance' => getAmount($data->balance,2),
                'currency' => get_default_currency_code(),
            ];
            })->first();

        $data =[
            'base_curr' => get_default_currency_code(),
            'card_basic_info' =>(object) $card_basic_info,
            'myCards'=> $myCards,
            'user'=>   $user,
            'userWallet'=>  (object)$userWallet,
            'cardCharge'=>(object)$cardCharge,
            'transactions'   => $transactions,
            ];
            $message =  ['success'=>['Virtual Card']];
            return Helpers::success($data,$message);
    }
    //charge
    public function charges(){
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->get()->map(function($data){

            return [
                'id' => $data->id,
                'slug' => $data->slug,
                'title' => $data->title,
                'fixed_charge' => getAmount($data->fixed_charge,2),
                'percent_charge' => getAmount($data->percent_charge,2),
                'min_limit' => getAmount($data->min_limit,2),
                'max_limit' => getAmount($data->max_limit,2),
            ];
        })->first();
        $data =[
            'base_curr' => get_default_currency_code(),
            'cardCharge'=>(object)$cardCharge
            ];
            $message =  ['success'=>['Fess & Charges']];
            return Helpers::success($data,$message);

    }
    //card details
    public function cardDetails(){
        $validator = Validator::make(request()->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = request()->card_id;
        $user = auth()->user();
        $myCard = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$myCard){
            $error = ['error'=>['Sorry, card not found!']];
            return Helpers::error($error);
        }
        $myCards = StrowalletVirtualCard::where('card_id',$card_id)->where('user_id',$user->id)->get()->map(function($data){
            $basic_settings = BasicSettings::first();
            $statusInfo = [
                "block" =>      0,
                "unblock" =>    1,
                ];
                
            return[
                'id' => $data->id,
                'name' => $data->name_on_card,
                'card_id' => $data->card_id,
                'card_brand' => $data->card_brand,
                'card_user_id' => $data->card_user_id,
                'expiry' => $data->expiry,
                'cvv' => $data->cvv,
                'card_type' => ucwords($data->card_type),
                'city' => $data->user->strowallet_customer->city,
                'state' => $data->user->strowallet_customer->state,
                'zip_code' => $data->user->strowallet_customer->zipCode,
                'amount' => getAmount($data->balance,2),
                'card_back_details' => @$this->api->card_details,
                'site_title' =>@$basic_settings->site_name,
                'site_logo' =>get_logo(@$basic_settings,'dark'),
                'status' => $data->is_active,
                'status_info' =>(object)$statusInfo ,
            ];
        })->first();
        $data =[
            'base_curr' => get_default_currency_code(),
            'myCards'=> $myCards,
            'user'=>   $user,

            ];
            $message =  ['success'=>['Virtual Card Details']];
            return Helpers::success($data,$message);
    }
    // card transactions
    public function cardTransaction() {
        $validator = Validator::make(request()->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = request()->card_id;
        $user = auth()->user();
        $card = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        $id = $card->card_id;
        $emptyMessage  = 'No Transaction Found!';
        $start_date = date("Y-m-d", strtotime( date( "Y-m-d", strtotime( date("Y-m-d") ) ) . "-12 month" ) );
        $end_date = date('Y-m-d');
        $curl = curl_init();
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;

        curl_setopt_array($curl, [
        CURLOPT_URL => $base_url . "card-transactions/",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode([
            'public_key' => $public_key,
            'card_id' => $card->card_id,
        ]),
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "content-type: application/json"
        ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $result  = json_decode($response, true);

        $message = ['success' => ['Virtual Card Transactions']];
        return Helpers::success($result['response'], $message);
    }
    //card block
    public function cardBlock(Request $request){
        $validator = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = $request->card_id;
        $user = auth()->user();
        $status = 'freeze';
        $card = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error = ['error'=>['Sorry, invalid request!']];
            return Helpers::error($error);
        }

        $client = new \GuzzleHttp\Client();
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;

        $response = $client->request('POST', $base_url.'action/status/?action='.$status.'&card_id='.$card->card_id.'&public_key='.$public_key, [
        'headers' => [
            'accept' => 'application/json',
        ],
        ]);
        
        $result = $response->getBody();
        $data  = json_decode($result, true);
        
        if (isset($data)) {
            if ($data['status'] == 'true') {
                $card->is_active = 0;
                $card->save();
                $message =  ['success'=>['Card block successfully!']];
                return Helpers::onlysuccess($message);
            }
        }

    }
    //unblock card
    public function cardUnBlock(Request $request){
        $validator = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id = $request->card_id;
        $user = auth()->user();
        $status = 'unfreeze';
        $card = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error = ['error'=>['Sorry, invalid request!']];
            return Helpers::error($error);
        }
        $client = new \GuzzleHttp\Client();
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;

        $response = $client->request('POST', $base_url.'action/status/?action='.$status.'&card_id='.$card->card_id.'&public_key='.$public_key, [
        'headers' => [
            'accept' => 'application/json',
        ],
        ]);
        
        $result = $response->getBody();
        $data  = json_decode($result, true);
        
        if (isset($data['status'])) {
            $card->is_active = 1;
            $card->save();
            $message =  ['success'=>['Card unblock successfully!']];
            return Helpers::onlysuccess($message);
        }else{
            $error = ['error' => $data['message']];
            return Helpers::error($error,null,200);
        }
        

    }
}
