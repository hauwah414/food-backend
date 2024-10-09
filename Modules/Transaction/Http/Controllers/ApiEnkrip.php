<?php

namespace Modules\Transaction\Http\Controllers;

use App\Http\Models\Configs;
use App\Http\Models\TransactionProduct;
use App\Jobs\FraudJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\User;
use App\Http\Models\LogTopup;
use App\Http\Models\LogTopupMidtrans;
use App\Http\Models\LogTopupManual;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProductModifier;
use App\Http\Models\ManualPaymentMethod;
use App\Http\Models\OvoReference;
use App\Http\Models\TransactionMultiplePayment;
use App\Http\Models\TransactionPaymentBalance;
use App\Http\Models\TransactionPaymentMidtran;
use App\Http\Models\TransactionPaymentOvo;
use App\Http\Models\LogRequest;
use App\Http\Models\OvoReversal;
use App\Http\Models\TransactionPickup;
use App\Http\Models\Setting;
use DB;
use Modules\IPay88\Lib\IPay88;
use App\Lib\MyHelper;
use App\Lib\Midtrans;
use App\Lib\Ovo;
use Modules\ProductVariant\Entities\TransactionProductVariant;
use Modules\ShopeePay\Entities\TransactionPaymentShopeePay;
use Modules\Transaction\Entities\TransactionBundlingProduct;
use Modules\Transaction\Entities\TransactionGroup;
use Modules\Transaction\Http\Requests\Transaction\ConfirmPayment;
use Modules\Xendit\Entities\TransactionPaymentXendit;

class ApiEnkrip extends Controller
{
    public function enkrip(Request $request)
    {
        $dataHashBalance = [
            'id_hairstylist_log_balance'     => $request->id_hairstylist_log_balance,
            'id_user_hair_stylist'           => $request->id_user_hair_stylist,
            'balance'                        => $request->balance,
            'balance_before'                 => $request->balance_before,
            'balance_after'                  => $request->balance_after,
            'id_reference'                   => $request->id_reference,
            'source'                         => $request->source,
            'type_log_balance'               => $request->type_log_balance,
        ];
        $enc = MyHelper::encrypt2019(json_encode(($dataHashBalance)));
        return $enc;
    }
}
