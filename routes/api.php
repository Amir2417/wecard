<?php

use App\Http\Controllers\Api\AppSettingsController;
use Illuminate\Support\Str;
use App\Models\Admin\SetupPage;
use App\Http\Helpers\Api\Helpers;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\Api\User\UserController;
use App\Http\Controllers\Api\User\AddMoneyController;
use App\Http\Controllers\Api\User\Auth\LoginController;
use App\Http\Controllers\Api\User\AuthorizationController;
use App\Http\Controllers\Api\User\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\User\StripeVirtualController;
use App\Http\Controllers\Api\User\StrowalletVirtualCardController;
use App\Http\Controllers\Api\User\SudoVirtualCardController;
use App\Http\Controllers\Api\User\TransferMoneyController;
use App\Http\Controllers\Api\User\VirtualCardController;
use App\Http\Controllers\Api\User\WithdrawController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::get('clear-cache', function() {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    $message =  ['success'=>['Clear cache successfully']];
    return Helpers::onlysuccess($message);
});
Route::get('useful-links', function() {
    $type = Str::slug(App\Constants\GlobalConst::USEFUL_LINKS);
    $policies =SetupPage::orderBy('id',"ASC")->where('type', $type)->where('status',1)->get()->map(function($link){
        return[
            'id' => $link->id,
            'slug' => $link->slug,
            'link' =>route('useful.link',$link->slug),
        ];
    });
    $data =[
        'about' =>  route('about'),
        'contact' =>  route('contact'),
        'policy_pages' =>  $policies,
    ];
    $message =  ['success'=>['Useful Links']];
    return Helpers::success($data,$message);
});
Route::controller(AppSettingsController::class)->prefix("app-settings")->group(function(){
    Route::get('/','appSettings');
    Route::get('languages','languages');
});
Route::controller(AddMoneyController::class)->prefix("add-money")->group(function(){
    Route::get('success/response/{gateway}','success')->name('api.payment.success');
    Route::get("cancel/response/{gateway}",'cancel')->name('api.payment.cancel');
    Route::get('stripe/payment/success/{trx}','stripePaymentSuccess')->name('api.stripe.payment.success');
    Route::get('/flutterwave/callback', 'flutterwaveCallback')->name('api.flutterwave.callback');
    Route::get('razor/callback', 'razorCallback')->name('api.razor.callback');
     //QRPay
     Route::get('qrpay/success', 'qrPaySuccess')->name('api.qrpay.success');
     Route::get('qrpay/cancel/{trx}', 'qrPayCancel')->name('api.qrpay.cancel');
});


Route::prefix('user')->group(function(){
    Route::post('login',[LoginController::class,'login']);
    Route::post('register',[LoginController::class,'register']);
    //forget password
    Route::post('forget/password', [ForgotPasswordController::class,'sendCode']);
    Route::post('forget/verify/code', [ForgotPasswordController::class,'verifyCode']);
    Route::post('forget/reset/password', [ForgotPasswordController::class,'resetPassword']);

    Route::middleware(['auth.api'])->group(function(){
        Route::get('logout', [LoginController::class,'logout']);
        //email verifications
        Route::post('send-code', [AuthorizationController::class,'sendMailCode']);
        Route::post('email-verify', [AuthorizationController::class,'mailVerify']);
        Route::middleware(['CheckStatusApiUser'])->group(function () {
            Route::get('dashboard', [UserController::class,'home']);
            Route::get('profile', [UserController::class,'profile']);
            Route::post('profile/update', [UserController::class,'profileUpdate'])->middleware('app.mode.api');
            Route::post('password/update', [UserController::class,'passwordUpdate'])->middleware('app.mode.api');
            Route::post('delete/account', [UserController::class,'deleteAccount'])->middleware('app.mode.api');

            //virtual card stripe
            Route::middleware('virtual_card_method:stripe')->group(function(){
                Route::controller(StripeVirtualController::class)->prefix('my-card/stripe')->group(function(){
                    Route::get('/','index');
                    Route::get('details','cardDetails');
                    Route::post('create','cardBuy');
                    Route::get('transaction','cardTransaction');
                    Route::post('inactive','cardInactive');
                    Route::post('active','cardActive');
                    Route::post('get/sensitive/data','getSensitiveData');
                });
            });
             //virtual card sudo
             Route::middleware('virtual_card_method:sudo')->group(function(){
                Route::controller(SudoVirtualCardController::class)->prefix('my-card/sudo')->group(function(){
                    Route::get('/','index');
                    Route::get('charges','charges');
                    Route::get('details','cardDetails');
                    Route::post('create','cardBuy');
                    Route::post('fund','cardFundConfirm');
                    Route::get('details','cardDetails');
                    Route::get('transaction','cardTransaction');
                    Route::post('block','cardBlock');
                    Route::post('unblock','cardUnBlock');
                    Route::post('make-remove/default','makeDefaultOrRemove');
                });
            });
            //virtual card flutterwave
            Route::middleware('virtual_card_method:flutterwave')->group(function(){
                Route::controller(VirtualCardController::class)->prefix('my-card')->group(function(){
                    Route::get('/','index');
                    Route::get('charges','charges');
                    Route::post('create','cardBuy')->middleware('api.kyc.verification.guard');
                    Route::post('fund','cardFundConfirm');
                    Route::post('withdraw','cardWithdraw');
                    Route::get('details','cardDetails');
                    Route::get('transaction','cardTransaction');
                    Route::post('block','cardBlock');
                    Route::post('unblock','cardUnBlock');
                });
            });
            //strowallet virtual card
            Route::middleware('virtual_card_method:strowallet')->group(function(){
                Route::controller(StrowalletVirtualCardController::class)->prefix('strowallet-card')->group(function(){
                    Route::get('/','index');
                    Route::get('charges','charges');
                    Route::post('create','cardBuy')->middleware('api.kyc.verification.guard');
                    Route::post('fund','cardFundConfirm');
                    Route::get('details','cardDetails');
                    Route::get('transaction','cardTransaction');
                    Route::post('block','cardBlock');
                    Route::post('unblock','cardUnBlock');
                });
            });
            //Transfer Money
             Route::controller(TransferMoneyController::class)->prefix('transfer-money')->group(function(){
                Route::get('info','transferMoneyInfo');
                Route::post('exist','checkUser');
                Route::post('confirmed','confirmedTransferMoney');
            });
            //Withdraw Money
            Route::controller(WithdrawController::class)->prefix('withdraw')->group(function(){
                Route::get('info','withdrawInfo');
                Route::post('insert','withdrawInsert');
                Route::post('manual/confirmed','withdrawConfirmed')->name('api.withdraw.manual.confirmed');
            });

            Route::controller(AuthorizationController::class)->prefix('kyc')->group(function(){
                Route::get('input-fields','getKycInputFields');
                Route::post('submit','KycSubmit');
            });

            Route::get('transactions', [UserController::class,'transactions']);
             //add money
            Route::controller(AddMoneyController::class)->prefix("add-money")->group(function(){
                Route::get('/information','addMoneyInformation');
                Route::post('submit-data','submitData');
                //manual gateway
                Route::post('manual/payment/confirmed','manualPaymentConfirmedApi')->name('api.manual.payment.confirmed');
            });

        });

    });

});
