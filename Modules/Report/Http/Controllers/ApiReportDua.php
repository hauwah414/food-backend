<?php

namespace Modules\Report\Http\Controllers;

use App\Http\Models\User;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProduct;
use App\Http\Models\Outlet;
use App\Http\Models\Product;
use App\Http\Models\ProductTag;
use App\Http\Models\DailyReportTrx;
use App\Http\Models\DailyReportTrxMenu;
use App\Http\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Report\Http\Requests\DetailReport;
use Modules\Report\Http\Requests\Report;
use App\Lib\MyHelper;
use Validator;
use Hash;
use DB;
use Mail;

class ApiReportDua extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    /* REPORT PRODUCT RECURSIF QTY*/
    public function transactionProduct(Report $request)
    {

        if ($request->json('id_outlet')) {
            $product   = DB::select(DB::raw('SELECT products.id_product, 
                            products.product_code,
                            products.product_name, 
                            (SELECT SUM(daily_report_trx_menu.total_rec)) as total_rec,
                            (SELECT SUM(daily_report_trx_menu.total_qty)) as total_qty,
                            (SELECT SUM(daily_report_trx_menu.total_nominal)) as total_nominal
                            FROM daily_report_trx_menu
                            LEFT JOIN products ON daily_report_trx_menu.id_product = products.id_product
                            WHERE id_outlet = "' . $request->json('id_outlet') . '"
                            AND daily_report_trx_menu.trx_date BETWEEN "' . date('Y-m-d', strtotime($request->json('date_start'))) . '" 
                            AND "' . date('Y-m-d', strtotime($request->json('date_end'))) . '"
                            GROUP BY id_product'));
        } else {
            $product   = DB::select(DB::raw('SELECT products.id_product, 
                            products.product_code,
                            products.product_name, 
                            (SELECT SUM(daily_report_trx_menu.total_rec)) as total_rec,
                            (SELECT SUM(daily_report_trx_menu.total_qty)) as total_qty,
                            (SELECT SUM(daily_report_trx_menu.total_nominal)) as total_nominal
                            FROM daily_report_trx_menu
                            LEFT JOIN products ON daily_report_trx_menu.id_product = products.id_product
                            WHERE daily_report_trx_menu.trx_date BETWEEN "' . date('Y-m-d', strtotime($request->json('date_start'))) . '" 
                            AND "' . date('Y-m-d', strtotime($request->json('date_end'))) . '"
                            GROUP BY id_product'));
        }

        $product = json_decode(json_encode($product), true);

        return response()->json(MyHelper::checkGet($product));
    }

    /* REPORT TRANSACTION */
    public function transactionTrx(Report $request)
    {
        $trans = DailyReportTrx::whereBetween('trx_date', [date('Y-m-d', strtotime($request->json('date_start'))), date('Y-m-d', strtotime($request->json('date_end')))]);

        if ($request->json('id_outlet')) {
            $trans->with(['outlet'])->where('id_outlet', $request->json('id_outlet'));
        }

        $trans = $trans->orderBy('trx_date', 'ASC')->get()->toArray();

        return response()->json(MyHelper::checkGet($trans));
    }

    /* REPORT DETAIL MENU */
    public function transactionProductDetail(Request $request)
    {
        $product = DailyReportTrxMenu::whereBetween('trx_date', [date('Y-m-d', strtotime($request->json('date_start'))), date('Y-m-d', strtotime($request->json('date_end')))])->where('id_product', $request->json('id_product'));

        if ($request->json('id_outlet')) {
            $product->with(['outlet'])->where('id_outlet', $request->json('id_outlet'));
        }

        $product = $product->orderBy('trx_date', 'ASC')->get()->toArray();

        return response()->json(MyHelper::checkGet($product));
    }

    /* REPORT USER 10 */
    public function transactionUser(Report $request)
    {
        $transaction = Transaction::with('user')
        ->whereBetween('transaction_date', [date('Y-m-d 00:00:00', strtotime($request->json('date_start'))), date('Y-m-d 23:59:59', strtotime($request->json('date_end')))])
        ->where('transaction_payment_status', 'Completed')
        ->select('id_user', DB::raw('SUM(transactions.transaction_grandtotal) as nominal'));

        if ($request->json('id_outlet')) {
            $transaction->where('id_outlet', $request->json('id_outlet'));
        }

        $transaction = $transaction->groupBy('id_user')->orderBy('nominal', 'DESC')->take(10)->get();

        return response()->json(MyHelper::checkGet($transaction));
    }

     /* REPORT OUTLET */
    public function transactionOutlet(Report $request)
    {

        $outletValue   = DB::select(DB::raw('SELECT outlets.id_outlet, 
                        outlets.outlet_code,
                        outlets.outlet_name, 
                        (SELECT SUM(daily_report_trx.trx_grand)) as total_value,
                        (SELECT SUM(daily_report_trx.trx_count)) as total_count
                        FROM daily_report_trx
                        JOIN outlets ON daily_report_trx.id_outlet = outlets.id_outlet
                        AND daily_report_trx.trx_date BETWEEN "' . date('Y-m-d', strtotime($request->json('date_start'))) . '" 
                        AND "' . date('Y-m-d', strtotime($request->json('date_end'))) . '"
                        GROUP BY outlets.id_outlet
                        ORDER BY total_value DESC'));

        $outletCount   = DB::select(DB::raw('SELECT outlets.id_outlet, 
                        outlets.outlet_code,
                        outlets.outlet_name, 
                        (SELECT SUM(daily_report_trx.trx_count)) as total_count
                        FROM daily_report_trx
                        JOIN outlets ON daily_report_trx.id_outlet = outlets.id_outlet
                        AND daily_report_trx.trx_date BETWEEN "' . date('Y-m-d', strtotime($request->json('date_start'))) . '" 
                        AND "' . date('Y-m-d', strtotime($request->json('date_end'))) . '"
                        GROUP BY outlets.id_outlet
                        ORDER BY total_count DESC
                        LIMIT 10'));

        $outlet['count'] = json_decode(json_encode($outletCount), true);
        $outlet['value'] = json_decode(json_encode($outletValue), true);

        return response()->json(MyHelper::checkGet($outlet));
    }

    /* REPORT DETAIL OUTLET */
    public function transactionOutletDetail(Request $request)
    {
        $outlet = DailyReportTrx::whereBetween('trx_date', [date('Y-m-d', strtotime($request->json('date_start'))), date('Y-m-d', strtotime($request->json('date_end')))])
                    ->where('id_outlet', $request->json('id_outlet'))
                    ->select(DB::raw('SUM(daily_report_trx.trx_count) as total_count, SUM(daily_report_trx.trx_grand) as total_value, trx_date'))
                    ->groupBy('trx_date')
                    ->orderBy('trx_date', 'ASC')->get()->toArray();

        return response()->json(MyHelper::checkGet($outlet));
    }
}
