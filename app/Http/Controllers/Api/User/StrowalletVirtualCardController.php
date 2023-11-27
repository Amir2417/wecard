<?php

namespace App\Http\Controllers\Api\User;

use Exception;
use App\Models\UserWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Admin\Currency;
use App\Models\VirtualCardApi;
use App\Models\UserNotification;
use App\Http\Helpers\Api\Helpers;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Constants\NotificationConst;
use App\Http\Controllers\Controller;
use App\Models\StrowalletVirtualCard;
use App\Constants\PaymentGatewayConst;
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
        $validator  = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error  =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $card_id    = $request->card_id;
        $user       = auth()->user();
        $status     = 'unfreeze';
        $card       = StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error  = ['error'=>['Sorry, invalid request!']];
            return Helpers::error($error);
        }
        $client         = new \GuzzleHttp\Client();
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

    //card buy
    public function cardBuy(Request $request){
        $user = auth()->user();
        if($user->strowallet_customer == null){
            $validator = Validator::make($request->all(), [
                'card_amount'       => 'required|numeric|gt:0',
                'first_name'        => 'required|string',
                'last_name'         => 'required|string',
                'house_number'      => 'required|string',
                'id_number'         => 'required|string',
                'customer_email'    => 'required|string',
                'phone'             => 'required|string',
                'date_of_birth'     => 'required|string',
                'line1'             => 'required|string',
                'state'             => 'required|string',
                'zip_code'          => 'required|string',
                'city'              => 'required|string',
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'card_amount'       => 'required|numeric|gt:0',
            ]);
        }
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $formData   = $request->all();
        
        
        $amount = $request->card_amount;
        $basic_setting = BasicSettings::first();
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            $error = ['error'=>['Wallet not found']];
            return Helpers::error($error);
        }
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            $error = ['error'=>['Default currency not setup yet']];
            return Helpers::error($error);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            $error = ['error'=>['Please follow the transaction limit']];
            return Helpers::error($error);
        }
        //charge calculations
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            $error = ['error'=>['Sorry, insufficient balance']];
            return Helpers::error($error);
        }
        

        if($user->strowallet_customer == null){
            $createCustomer     = stro_wallet_create_user($user,$formData,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);
            
            if( $createCustomer['status'] == false){
                $error = ['error'=>["Customer doesn't created properly,Contact Follow the instruction"]];
                return Helpers::error($error);
            }
            $user->strowallet_customer =   (object)$createCustomer['data'];
            $user->save();
            $customer = $user->strowallet_customer;
            
        }else{
            $customer = $user->strowallet_customer;
        }

        // $created_card = create_strowallet_virtual_card($user,$request->card_amount,$customer,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);
        // if($created_card['status'] == false){
        //     return back()->with(['error' => [$created_card['message'] ?? "Card Creation Failed!"]]);
        // }
        // $card_id    = $created_card['data']->card_id;
        $card_id        = "11ac4e7e-8d28-435e-ac03-a91c60f087bf";
        $card_details   = card_details($card_id,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);
        if( $card_details['status'] == false){
            $error = ['error'=>["No Card Found!"]];
            return Helpers::error($error);
        }
        
        $strowallet_card                            = new StrowalletVirtualCard();
        $strowallet_card->user_id                   = $user->id;
        $strowallet_card->name_on_card              = $card_details['data']['card_detail']['card_holder_name'];
        $strowallet_card->card_id                   = $card_details['data']['card_detail']['card_id'];
        $strowallet_card->card_created_date         = $card_details['data']['card_detail']['card_created_date'];
        $strowallet_card->card_type                 = $card_details['data']['card_detail']['card_type'];
        $strowallet_card->card_brand                = $card_details['data']['card_detail']['card_brand'];
        $strowallet_card->card_user_id              = $card_details['data']['card_detail']['card_user_id'];
        $strowallet_card->reference                 = $card_details['data']['card_detail']['reference'];
        $strowallet_card->card_status               = $card_details['data']['card_detail']['card_status'];
        $strowallet_card->customer_id               = $card_details['data']['card_detail']['customer_id'];
        $strowallet_card->card_name                 = $customer->card_brand;
        $strowallet_card->card_number               = $card_details['data']['card_detail']['card_number'];
        $strowallet_card->last4                     = $card_details['data']['card_detail']['last4'];
        $strowallet_card->cvv                       = $card_details['data']['card_detail']['cvv'];
        $strowallet_card->expiry                    = $card_details['data']['card_detail']['expiry'];
        $strowallet_card->customer_email            = $card_details['data']['card_detail']['customer_email'];
        $strowallet_card->balance                   = $card_details['data']['card_detail']['balance'];
        $strowallet_card->save();

        
        $trx_id =  'CB'.getTrxNum();
        try{
            $sender = $this->insertCardBuy( $trx_id,$user,$wallet,$amount, $strowallet_card ,$payable);
            $this->insertBuyCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$strowallet_card->card_number);
            $message =  ['success'=>['Card Successfully Buy']];
            return Helpers::onlysuccess($message);
            
        }catch(Exception $e){
            
            $error =  ['error'=>['Something Went Wrong! Please Try Again.']];
            return Helpers::error($error);
        }
    }
    public function insertCardBuy( $trx_id,$user,$wallet,$amount, $strowallet_card ,$payable) {
        $trx_id = $trx_id;
        $authWallet = $wallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'card_info' =>   $strowallet_card??''
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::VIRTUALCARD,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::CARDBUY," ")),
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::RECEIVED,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }
    public function insertBuyCardCharge($fixedCharge,$percent_charge, $total_charge,$user,$id,$card_number) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      => $fixedCharge,
                'total_charge'      => $total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'           => "Buy Card ",
                'message'         => "Buy card successful ".$card_number,
                'image'           => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::CARD_BUY,
                'user_id'   => $user->id,
                'message'   => $notification_content,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
    //update user balance
    public function updateSenderWalletBalance($authWallet,$afterCharge) {
        $authWallet->update([
            'balance'   => $afterCharge,
        ]);
    }
    /**
     * Card Fund
     */
    public function cardFundConfirm(Request $request){
        $validator = Validator::make($request->all(), [
            'card_id' => 'required',
            'fund_amount' => 'required|numeric|gt:0',
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $user = auth()->user();
        $myCard =  StrowalletVirtualCard::where('user_id',$user->id)->where('card_id',$request->card_id)->first();
        
        if(!$myCard){
            $error = ['error'=>['Your Card not found']];
            return Helpers::error($error);
        }

        $amount = $request->fund_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            $error = ['error'=>['Wallet not found']];
            return Helpers::error($error);
        }
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            $error = ['error'=>['Default currency not setup yet']];
            return Helpers::error($error);
        }
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            $error = ['error'=>['Please follow the transaction limit']];
            return Helpers::error($error);
        }
        
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;

        $client = new \GuzzleHttp\Client();

        $response               = $client->request('POST', $base_url.'fund-card/', [
            'headers'           => [
                'accept'        => 'application/json',
            ],
            'form_params'       => [
                'card_id'       => $myCard->card_id,
                'amount'        => $amount,
                'public_key'    => $public_key,
            ],
        ]);

        $result         = $response->getBody();
        $decodedResult  = json_decode($result, true);
       
        if(!empty($decodedResult['success'])  && $decodedResult['success'] == "success"){
            //added fund amount to card
            $myCard->balance += $amount;
            $myCard->save();
            $trx_id = 'CF'.getTrxNum();
            $sender = $this->insertCardFund( $trx_id,$user,$wallet,$amount, $myCard ,$payable);
            $this->insertFundCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$myCard->card_number,$amount);
            $message =  ['success'=>['Card Funded Successfully']];
            return Helpers::onlysuccess($message);

        }else{
            
            $error = ['error'=>[@$decodedResult['message']??'Please wait a moment & try again later.']];
            return Helpers::error($error);
        }

    }
    //card fund helper
    public function insertCardFund( $trx_id,$user,$wallet,$amount, $myCard ,$payable) {
        $trx_id = $trx_id;
        $authWallet = $wallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'card_info' =>   $myCard??''
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::VIRTUALCARD,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::CARDFUND," ")),
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::RECEIVED,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }
    public function insertFundCardCharge($fixedCharge,$percent_charge, $total_charge,$user,$id,$card_number,$amount) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      =>$fixedCharge,
                'total_charge'      =>$total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>"Card Fund ",
                'message'       => "Card fund successful card: ".$card_number.' '.getAmount($amount,2).' '.get_default_currency_code(),
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::CARD_FUND,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }


}
