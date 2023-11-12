<?php

namespace App\Http\Controllers\Api\User;

use App\Constants\NotificationConst;
use App\Constants\PaymentGatewayConst;
use App\Http\Controllers\Controller;
use App\Http\Helpers\Api\Helpers;
use App\Models\Admin\BasicSettings;
use App\Models\Admin\Currency;
use App\Models\Admin\TransactionSetting;
use App\Models\SudoVirtualCard;
use App\Models\Transaction;
use App\Models\UserNotification;
use App\Models\UserWallet;
use App\Models\VirtualCardApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SudoVirtualCardController extends Controller
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
        $myCards = SudoVirtualCard::where('user_id',$user->id)->orderBy('id','DESC')->get()->map(function($data){
            $basic_settings = BasicSettings::first();
            $statusInfo = [
                "block" =>      0,
                "unblock" =>     1,
                ];
            return[
                'id' => $data->id,
                'card_id' => $data->card_id,
                'amount' => getAmount($data->amount,2),
                'currency' => $data->currency,
                'card_holder' => $data->name,
                'brand' => $data->brand,
                'type' => $data->type,
                'card_pan' => $data->maskedPan,
                'expiry_month' => $data->expiryMonth,
                'expiry_year' => $data->expiryYear,
                'cvv' => "***",
                'card_back_details' => @$this->api->card_details,
                'site_title' =>@$basic_settings->site_name,
                'site_logo' =>get_logo(@$basic_settings,'dark'),
                'site_fav' =>get_fav($basic_settings,'dark'),
                'status' => $data->status,
                'is_default' => $data->is_default,
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
        $transactions = Transaction::auth()->virtualCard()->latest()->take(10)->get()->map(function($item){
            $statusInfo = [
                "success" =>      1,
                "pending" =>      2,
                "rejected" =>     3,
                ];
            return[
                'id' => $item->id,
                'trx' => $item->trx_id,
                'transaction_type' => "Virtual Card".'('. @$item->remark.')',
                'request_amount' => getAmount($item->request_amount,2).' '.get_default_currency_code() ,
                'payable' => getAmount($item->payable,2).' '.get_default_currency_code(),
                'total_charge' => getAmount($item->charge->total_charge,2).' '.get_default_currency_code(),
                'card_amount' => getAmount(@$item->details->card_info->amount,2).' '.get_default_currency_code(),
                'card_number' => $item->details->card_info->card_pan??$item->details->card_info->maskedPan,
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
            'myCard'=> $myCards,
            'userWallet'=>  (object)$userWallet,
            'cardCharge'=>(object)$cardCharge,
            'transactions'   => $transactions,
            ];
            $message =  ['success'=>['Virtual Card Sudo']];
            return Helpers::success($data,$message);
    }
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
            'cardCharge'=>(object)$cardCharge,
            ];
            $message =  ['success'=>['Fess & Charges']];
            return Helpers::success($data,$message);
    }
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
        $myCard = SudoVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$myCard){
            $error = ['error'=>['Sorry, card not found!']];
            return Helpers::error($error);
        }
        $myCards = SudoVirtualCard::where('card_id',$card_id)->where('user_id',$user->id)->get()->map(function($data){
            $basic_settings = BasicSettings::first();
            $statusInfo = [
                "block" =>      0,
                "unblock" =>     1,
                ];

            return[
                'id' => $data->id,
                'card_id' => $data->card_id,
                'amount' => getAmount($data->amount,2),
                'currency' => $data->currency,
                'card_holder' => $data->name,
                'brand' => $data->brand,
                'type' => $data->type,
                'card_pan' => $data->maskedPan,
                'expiry_month' => $data->expiryMonth,
                'expiry_year' => $data->expiryYear,
                'cvv' => "***",
                'card_back_details' => @$this->api->card_details,
                'site_title' =>@$basic_settings->site_name,
                'site_logo' =>get_logo(@$basic_settings,'dark'),
                'site_fav' =>get_fav($basic_settings,'dark'),
                'status' => $data->status,
                'is_default' => $data->is_default,
                'status_info' =>(object)$statusInfo ,
            ];
        })->first();

        $cardToken = getCardToken($this->api->config->sudo_api_key,$this->api->config->sudo_url,$myCard->card_id);
        if($cardToken['statusCode'] == 200){
            $cardToken = $cardToken['data']['token'];
        }else{
            $cardToken = '';
        }
        $card_secure_date =[
            'api_mode' =>  $this->api->config->sudo_mode,
            'api_vault_id' =>  $this->api->config->sudo_vault_id,
            'card_token' =>  $cardToken,
        ];
        $data =[
            'base_curr' => get_default_currency_code(),
            'card_secure_date'=> (object)$card_secure_date,
            'card_details'=> $myCards,
            ];
            $message =  ['success'=>['Virtual Card Details']];
            return Helpers::success($data,$message);
    }
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
        $card = SudoVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error = ['error'=>['Sorry, Card Not Found!']];
            return Helpers::error($error);
        }
        $card_truns =  getCardTransactions($this->api->config->sudo_api_key,$this->api->config->sudo_url,$card->card_id);
        $data = [
            'cardTransactions' => $card_truns
        ];

        $message = ['success' => ['Virtual Card Transactions']];
        return Helpers::success($data, $message);


    }
    public function makeDefaultOrRemove(Request $request) {
        $validator = Validator::make($request->all(), [
            'card_id'     => "required|string",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $validated = $validator->validate();
        $user = auth()->user();
        $targetCard =  SudoVirtualCard::where('card_id',$validated['card_id'])->where('user_id',$user->id)->first();
        if(!$targetCard){
            $error = ['error'=>['Something Is Wrong In Your Card']];
            return Helpers::error($error);
        };
        $withOutTargetCards =  SudoVirtualCard::where('id','!=',$targetCard->id)->where('user_id',$user->id)->get();
        try{
            $targetCard->update([
                'is_default'         => $targetCard->is_default ? 0 : 1,
            ]);
            if(isset(  $withOutTargetCards)){
                foreach(  $withOutTargetCards as $card){
                    $card->is_default = false;
                    $card->save();
                }
            }
            $message =  ['success'=>['Status Updated Successfully']];
            return Helpers::onlysuccess($message);

        }catch(Exception $e) {
            $error = ['error'=>['Something Went Wrong! Please Try Again']];
            return Helpers::error($error);
        }
    }
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
        $status = 'inactive';
        $card = SudoVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error = ['error'=>['Something Is Wrong In Your Card']];
            return Helpers::error($error);
        }
        if($card->status == false){
            $error = ['error'=>['Sorry,This Card Is Already Blocked']];
            return Helpers::error($error);
        }
        $result = cardUpdate($this->api->config->sudo_api_key,$this->api->config->sudo_url,$card->card_id,$status);
        if(isset($result['statusCode'])){
            if($result['statusCode'] == 200){
                $card->status = false;
                $card->save();
                $message =  ['success'=>['Card Block Successfully!']];
                return Helpers::onlysuccess($message);
            }elseif($result['statusCode'] != 200){
                $error = ['error'=>[$result['message']??"Something Is Wrong"]];
                return Helpers::error($error);
            }
        }

    }
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
        $status = 'active';
        $card = SudoVirtualCard::where('user_id',$user->id)->where('card_id',$card_id)->first();
        if(!$card){
            $error = ['error'=>['Something Is Wrong In Your Card']];
            return Helpers::error($error);
        }
        if($card->status == true){
            $error = ['error'=>['Sorry,This Card Is Already Unblocked']];
            return Helpers::error($error);
        }
        $result = cardUpdate($this->api->config->sudo_api_key,$this->api->config->sudo_url,$card->card_id,$status);
        if(isset($result['statusCode'])){
            if($result['statusCode'] == 200){
                $card->status = true;
                $card->save();
                $message =  ['success'=>['Card Unblock Successfully!']];
                return Helpers::onlysuccess($message);
            }elseif($result['statusCode'] != 200){
                $error = ['error'=>[$result['message']??"Something Is Wrong"]];
                return Helpers::error($error);
            }
        }

    }
    public function cardBuy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_amount' => 'required|numeric|gt:0',
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return Helpers::validation($error);
        }
        $basic_setting = BasicSettings::first();
        $user = auth()->user();
        if($basic_setting->kyc_verification){
            if( $user->kyc_verified == 0){
                $error = ['error'=>['Please submit kyc information!']];
                return Helpers::error($error);
            }elseif($user->kyc_verified == 2){
                $error = ['error'=>['Please wait before admin approved your kyc information']];
                return Helpers::error($error);
            }elseif($user->kyc_verified == 3){
                $error = ['error'=>['Admin rejected your kyc information, Please re-submit again']];
                return Helpers::error($error);
            }
        }
        $amount = $request->card_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            $error = ['error'=>['Wallet Not Found']];
            return Helpers::error($error);
        }
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            $error = ['error'=>['Default Currency Not Setup Yet']];
            return Helpers::error($error);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            $error = ['error'=>['Please Follow The Transaction Limit']];
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
        $currency = $baseCurrency->code;
        $funding_sources =  get_funding_source( $this->api->config->sudo_api_key,$this->api->config->sudo_url);
        if(isset( $funding_sources['statusCode'])){
            if($funding_sources['statusCode'] == 403){
                $error = ['error'=>[$funding_sources['message']]];
                return Helpers::error($error);
            }elseif($funding_sources['statusCode'] == 404){
                $error = ['error'=>[$funding_sources['message']]];
                return Helpers::error($error);
            }
        }
        $supported_currency = ['USD','NGN'];
        if( !in_array($currency,$supported_currency??[])){
            $error = ['error'=>[$currency." Currency doesn't supported for creating virtual card, Please contact"]];
            return Helpers::error($error);

        }
        $bankCode = $funding_sources['data'][0]['_id']??'';

        $sudo_accounts =    get_sudo_accounts( $this->api->config->sudo_api_key,$this->api->config->sudo_url);
        $filteredArray = array_filter($sudo_accounts, function($item) use ($currency) {
            return $item['currency'] === $currency;
        });
        $matchingElements = array_values($filteredArray);
        $debitAccountId= $matchingElements[0]['_id']??"";

        if(  $debitAccountId == ""){
            //create debit account
            if( $user->sudo_account == null){
                //create account
                $store_account = create_sudo_account($this->api->config->sudo_api_key,$this->api->config->sudo_url, $currency);
                if( isset($store_account['error'])){
                    $error = ['error'=>["Haven't any debit account this currency, Please contact with owner"]];
                    return Helpers::error($error);
                }
                $user->sudo_account =   (object)$store_account['data'];
                $user->save();

            }
        }else{
            $user->sudo_account = (object)$matchingElements[0];
            $user->save();
        }
        $debitAccountId = $user->sudo_account->_id??'';

        $issuerCountry = '';
        if(get_default_currency_code() == "NGN"){
            $issuerCountry = "NGA";
        }elseif(get_default_currency_code() === "USD"){
            $issuerCountry = "USA";
        }

        //check sudo customer have or not
       if( $user->sudo_customer == null){
        //create customer
        $store_customer = create_sudo_customer($this->api->config->sudo_api_key,$this->api->config->sudo_url,$user);

        if( isset($store_customer['error'])){
            $error = ['error'=>["Customer doesn't created properly,Contact with owner"]];
            return Helpers::error($error);
        }
        $user->sudo_customer =   (object)$store_customer['data'];
        $user->save();
        $customerId = $user->sudo_customer->_id;

       }else{
        $customerId = $user->sudo_customer->_id;
       }
       //create card now
       $created_card = create_virtual_card($this->api->config->sudo_api_key,$this->api->config->sudo_url,
                            $customerId, $currency,$bankCode, $debitAccountId, $issuerCountry
                        );
       if(isset($created_card['statusCode'])){
        if($created_card['statusCode'] == 400){
            $error = ['error'=>[$created_card['message']]];
            return Helpers::error($error);
        }

       }
       if($created_card['statusCode']  = 200){
            $card_info = (object)$created_card['data'];
            $v_card = new SudoVirtualCard();
            $v_card->user_id = $user->id;
            $v_card->name = $user->fullname;
            $v_card->card_id = $card_info->_id;
            $v_card->business_id = $card_info->business;
            $v_card->customer = $card_info->customer;
            $v_card->account = $card_info->account;
            $v_card->fundingSource = $card_info->fundingSource;
            $v_card->type = $card_info->type;
            $v_card->brand = $card_info->brand;
            $v_card->currency = $card_info->currency;
            $v_card->amount = $card_info->balance;
            $v_card->charge = $total_charge;
            $v_card->maskedPan = $card_info->maskedPan;
            $v_card->last4 = $card_info->last4;
            $v_card->expiryMonth = $card_info->expiryMonth;
            $v_card->expiryYear = $card_info->expiryYear;
            $v_card->status = true;
            $v_card->isDeleted = $card_info->isDeleted;
            $v_card->billingAddress = $card_info->billingAddress;
            $v_card->save();

            $trx_id =  'CB'.getTrxNum();
            try{
                $sender = $this->insertCadrBuy( $trx_id,$user,$wallet,$amount, $v_card ,$payable);
                $this->insertBuyCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$v_card->maskedPan);
                $message =  ['success'=>['Buy Card Successfully']];
                return Helpers::onlysuccess($message);
            }catch(Exception $e){
                $error = ['error'=>["Something Went Wrong! Please Try Again."]];
                return Helpers::error($error);
            }

       }

    }
    //card buy helper
    public function insertCadrBuy( $trx_id,$user,$wallet,$amount, $v_card ,$payable) {
        $trx_id = $trx_id;
        $authWallet = $wallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'card_info' =>   $v_card??''
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
            $error = ['error'=>['Something went wrong! Please try again']];
            return Helpers::error($error);
        }
        return $id;
    }
    public function insertBuyCardCharge($fixedCharge,$percent_charge, $total_charge,$user,$id,$masked_card) {
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
                'title'         =>"Buy Card ",
                'message'       => "Buy card successful ".$masked_card,
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::CARD_BUY,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            $error = ['error'=>['Something went wrong! Please try again']];
            return Helpers::error($error);
        }
    }
    //update user balance
    public function updateSenderWalletBalance($authWallet,$afterCharge) {
        $authWallet->update([
            'balance'   => $afterCharge,
        ]);
    }
}
