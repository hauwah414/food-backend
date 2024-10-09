<?php

namespace Modules\Disburse\Http\Controllers;

use App\Exports\MultipleSheetExport;
use App\Exports\SummaryTrxBladeExport;
use App\Http\Models\Configs;
use App\Http\Models\DailyReportTrx;
use App\Http\Models\Outlet;
use App\Http\Models\Transaction;
use App\Http\Models\TransactionProduct;
use App\Http\Models\TransactionProductModifier;
use App\Jobs\SendRecapManualy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Lib\MyHelper;
use Modules\Disburse\Entities\BankName;
use Modules\Disburse\Entities\Disburse;
use DB;
use Modules\Disburse\Entities\DisburseOutletTransaction;
use Modules\Franchise\Entities\UserFranchise;
use App\Http\Models\Setting;
use Modules\Disburse\Entities\BankAccount;
use Modules\Disburse\Entities\DisburseOutlet;
use Rap2hpoutre\FastExcel\FastExcel;
use Rap2hpoutre\FastExcel\SheetCollection;
use Illuminate\Support\Facades\Storage;
use File;
use App\Lib\SendMail as Mail;
use Maatwebsite\Excel\Excel;

use function Clue\StreamFilter\fun;

class ApiDisburseController extends Controller
{
    public function __construct()
    {
        $this->trx = "Modules\Transaction\Http\Controllers\ApiTransaction";
    }

    public function dashboard(Request $request)
    {
        $post = $request->json()->all();
        $nominal = Disburse::join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse')->where('disburse.disburse_status', 'Success');
        $nominal_fail = Disburse::join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse')->whereIn('disburse.disburse_status', ['Fail', 'Failed Create Payouts']);
        $income_central = Disburse::join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse')->where('disburse.disburse_status', 'Success');
        $total_disburse = DisburseOutletTransaction::join('transactions', 'transactions.id_transaction', 'disburse_outlet_transactions.id_transaction')
                            ->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
                            ->where(function ($q) {
                                $q->whereNotNull('transaction_pickups.taken_at')
                                    ->orWhereNotNull('transaction_pickups.taken_by_system_at');
                            })
                            ->where(function ($q) {
                                $q->orWhereNull('id_disburse_outlet')
                                    ->orWhereIn('id_disburse_outlet', function ($query) {
                                        $query->select('id_disburse_outlet')
                                        ->from('disburse')
                                        ->join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse')
                                        ->where('disburse.disburse_status', 'Queued');
                                    });
                            });

        if (isset($post['id_outlet']) && !empty($post['id_outlet']) && $post['id_outlet'] != 'all') {
            $nominal->where('disburse_outlet.id_outlet', $post['id_outlet']);
            $nominal_fail->where('disburse_outlet.id_outlet', $post['id_outlet']);
            $income_central->where('disburse_outlet.id_outlet', $post['id_outlet']);
            $total_disburse->where('transactions.id_outlet', $post['id_outlet']);
        }

        if (isset($post['fitler_date']) && $post['fitler_date'] == 'today') {
            $nominal->whereDate('disburse.created_at', date('Y-m-d'));
            $nominal_fail->whereDate('disburse.created_at', date('Y-m-d'));
            $income_central->where('disburse.created_at', date('Y-m-d'));
            $total_disburse->where('transactions.transaction_date', date('Y-m-d'));
        } elseif (isset($post['fitler_date']) && $post['fitler_date'] == 'specific_date') {
            if (
                isset($post['start_date']) && !empty($post['start_date']) &&
                isset($post['end_date']) && !empty($post['end_date'])
            ) {
                $start_date = date('Y-m-d', strtotime($post['start_date']));
                $end_date = date('Y-m-d', strtotime($post['end_date']));

                $nominal->whereDate('disburse.created_at', '>=', $start_date)
                    ->whereDate('disburse.created_at', '<=', $end_date);
                $nominal_fail->whereDate('disburse.created_at', '>=', $start_date)
                    ->whereDate('disburse.created_at', '<=', $end_date);
                $income_central->whereDate('disburse.created_at', '>=', $start_date)
                    ->whereDate('disburse.created_at', '<=', $end_date);
                $total_disburse->whereDate('transactions.transaction_date', '>=', $start_date)
                    ->whereDate('transactions.transaction_date', '<=', $end_date);
            }
        }

        if (isset($post['id_user_franchise']) && !empty($post['id_user_franchise'])) {
            $nominal->join('user_franchise_outlet', 'user_franchise_outlet.id_outlet', 'disburse_outlet.id_outlet')
                ->where('user_franchise_outlet.id_user_franchise', $post['id_user_franchise']);
            $nominal_fail->join('user_franchise_outlet', 'user_franchise_outlet.id_outlet', 'disburse_outlet.id_outlet')
                ->where('user_franchise_outlet.id_user_franchise', $post['id_user_franchise']);
        }

        $nominal = $nominal->selectRaw('SUM(disburse_outlet.disburse_nominal-(disburse.disburse_fee / disburse.total_outlet)) as "nom_success", SUM(disburse_outlet.total_fee_item) as "nom_item", SUM(disburse_outlet.total_omset) as "nom_grandtotal", SUM(disburse_outlet.total_expense_central) as "nom_expense_central", SUM(disburse_outlet.total_delivery_price) as "nom_delivery"')->first();
        $nominal_fail = $nominal_fail->selectRaw('SUM(disburse_outlet.disburse_nominal-(disburse.disburse_fee / disburse.total_outlet)) as "disburse_nominal"')->first();
        $income_central = $income_central->sum('total_income_central');
        $total_disburse = $total_disburse->sum('disburse_outlet_transactions.income_outlet');

        $result = [
            'status' => 'success',
            'result' => [
                'nominal' => $nominal,
                'nominal_fail' => $nominal_fail,
                'income_central' => $income_central,
                'total_disburse' => $total_disburse
            ]
        ];
        return response()->json($result);
    }

    public function getOutlets(Request $request)
    {
        $post = $request->json()->all();

        $outlet = Outlet::leftJoin('bank_account_outlets', 'bank_account_outlets.id_outlet', 'outlets.id_outlet')
            ->leftJoin('bank_accounts', 'bank_accounts.id_bank_account', 'bank_account_outlets.id_bank_account')
            ->leftJoin('bank_name', 'bank_name.id_bank_name', 'bank_accounts.id_bank_name');

        if (isset($post['for'])) {
            $outlet->select('outlets.id_outlet', 'outlets.outlet_code', 'outlets.outlet_name');
        } else {
            $outlet->select(
                'outlets.id_outlet',
                'outlets.outlet_code',
                'outlets.outlet_name',
                'outlets.status_franchise',
                'outlets.outlet_special_status',
                'outlets.outlet_special_fee',
                'bank_accounts.id_bank_name',
                'bank_accounts.beneficiary_name',
                'bank_accounts.beneficiary_alias',
                'bank_accounts.beneficiary_account',
                'bank_accounts.beneficiary_email',
                'bank_name.bank_name',
                'bank_name.bank_code'
            );
        }

        if (isset($post['id_user_franchise']) && !empty($post['id_user_franchise'])) {
            $outlet->join('user_franchise_outlet', 'outlets.id_outlet', 'user_franchise_outlet.id_outlet')
                ->where('user_franchise_outlet.id_user_franchise', $post['id_user_franchise']);
        }

        if (isset($post['conditions']) && !empty($post['conditions'])) {
            $rule = 'and';
            if (isset($post['rule'])) {
                $rule = $post['rule'];
            }

            if ($rule == 'and') {
                foreach ($post['conditions'] as $row) {
                    if (isset($row['subject'])) {
                        if ($row['subject'] == 'outlet_code') {
                            if ($row['operator'] == '=') {
                                $outlet->where('outlets.outlet_code', $row['parameter']);
                            } else {
                                $outlet->where('outlets.outlet_code', 'like', '%' . $row['parameter'] . '%');
                            }
                        }

                        if ($row['subject'] == 'outlet_name') {
                            if ($row['operator'] == '=') {
                                $outlet->where('outlets.outlet_name', $row['parameter']);
                            } else {
                                $outlet->where('outlets.outlet_name', 'like', '%' . $row['parameter'] . '%');
                            }
                        }

                        if ($row['subject'] == 'beneficiary_bank') {
                            if ($row['operator'] == '=') {
                                $outlet->where('outlets.bank_name', $row['parameter']);
                            } else {
                                $outlet->where('outlets.bank_name', 'like', '%' . $row['parameter'] . '%');
                            }
                        }

                        if ($row['subject'] == 'beneficiary_name') {
                            if ($row['operator'] == '=') {
                                $outlet->where('bank_accounts.beneficiary_name', $row['parameter']);
                            } else {
                                $outlet->where('bank_accounts.beneficiary_name', 'like', '%' . $row['parameter'] . '%');
                            }
                        }

                        if ($row['subject'] == 'beneficiary_alias') {
                            if ($row['operator'] == '=') {
                                $outlet->where('bank_accounts.beneficiary_alias', $row['parameter']);
                            } else {
                                $outlet->where('bank_accounts.beneficiary_alias', 'like', '%' . $row['parameter'] . '%');
                            }
                        }

                        if ($row['subject'] == 'beneficiary_account') {
                            if ($row['operator'] == '=') {
                                $outlet->where('bank_accounts.beneficiary_account', $row['parameter']);
                            } else {
                                $outlet->where('bank_accounts.beneficiary_account', 'like', '%' . $row['parameter'] . '%');
                            }
                        }

                        if ($row['subject'] == 'beneficiary_email') {
                            if ($row['operator'] == '=') {
                                $outlet->where('bank_accounts.beneficiary_email', $row['parameter']);
                            } else {
                                $outlet->where('bank_accounts.beneficiary_email', 'like', '%' . $row['parameter'] . '%');
                            }
                        }
                    }
                }
            } else {
                $outlet->where(function ($subquery) use ($post) {
                    foreach ($post['conditions'] as $row) {
                        if (isset($row['subject'])) {
                            if ($row['subject'] == 'outlet_code') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('outlets.outlet_code', $row['parameter']);
                                } else {
                                    $subquery->orWhere('outlets.outlet_code', 'like', '%' . $row['parameter'] . '%');
                                }
                            }

                            if ($row['subject'] == 'outlet_name') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('outlets.outlet_name', $row['parameter']);
                                } else {
                                    $subquery->orWhere('outlets.outlet_name', 'like', '%' . $row['parameter'] . '%');
                                }
                            }

                            if ($row['subject'] == 'beneficiary_bank') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('outlets.bank_name', $row['parameter']);
                                } else {
                                    $subquery->orWhere('outlets.bank_name', 'like', '%' . $row['parameter'] . '%');
                                }
                            }

                            if ($row['subject'] == 'beneficiary_name') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('bank_accounts.beneficiary_name', $row['parameter']);
                                } else {
                                    $subquery->orWhere('bank_accounts.beneficiary_name', 'like', '%' . $row['parameter'] . '%');
                                }
                            }

                            if ($row['subject'] == 'beneficiary_alias') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('bank_accounts.beneficiary_alias', $row['parameter']);
                                } else {
                                    $subquery->orWhere('bank_accounts.beneficiary_alias', 'like', '%' . $row['parameter'] . '%');
                                }
                            }

                            if ($row['subject'] == 'beneficiary_account') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('bank_accounts.beneficiary_account', $row['parameter']);
                                } else {
                                    $subquery->orWhere('bank_accounts.beneficiary_account', 'like', '%' . $row['parameter'] . '%');
                                }
                            }

                            if ($row['subject'] == 'beneficiary_email') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('bank_accounts.beneficiary_email', $row['parameter']);
                                } else {
                                    $subquery->orWhere('bank_accounts.beneficiary_email', 'like', '%' . $row['parameter'] . '%');
                                }
                            }
                        }
                    }
                });
            }
        }

        if (isset($post['page'])) {
            $outlet = $outlet->paginate(25);
        } else {
            $outlet = $outlet->get()->toArray();
        }

        return response()->json(MyHelper::checkGet($outlet));
    }

    public function listDisburse(Request $request, $status)
    {
        $post = $request->json()->all();

        $data = Disburse::join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse')
            ->join('outlets', 'outlets.id_outlet', 'disburse_outlet.id_outlet')
            ->leftJoin('bank_name', 'bank_name.bank_code', 'disburse.beneficiary_bank_name');

        if (isset($post['id_disburse']) && !is_null($post['id_disburse'])) {
            $data->where('disburse.id_disburse', $post['id_disburse']);
        }

        if ($status != 'all') {
            $data->where('disburse.disburse_status', ucfirst($status));
        }

        if (isset($post['id_user_franchise']) && !empty($post['id_user_franchise'])) {
            $data->join('user_franchise_outlet', 'user_franchise_outlet.id_outlet', 'disburse_outlet.id_outlet')
                ->where('user_franchise_outlet.id_user_franchise', $post['id_user_franchise']);
        }

        if (
            isset($post['date_start']) && !empty($post['date_start']) &&
            isset($post['date_end']) && !empty($post['date_end'])
        ) {
            $start_date = date('Y-m-d', strtotime($post['date_start']));
            $end_date = date('Y-m-d', strtotime($post['date_end']));

            $data->whereDate('disburse.created_at', '>=', $start_date)
                ->whereDate('disburse.created_at', '<=', $end_date);
        }

        if (isset($post['conditions']) && !empty($post['conditions'])) {
            $rule = 'and';
            if (isset($post['rule'])) {
                $rule = $post['rule'];
            }

            if ($rule == 'and') {
                foreach ($post['conditions'] as $row) {
                    if (isset($row['subject'])) {
                        if ($row['subject'] == 'error_status') {
                            $data->where('disburse.error_code', $row['operator'])
                                ->where('disburse.disburse_status', 'Fail');
                        }

                        if ($row['subject'] == 'bank_name') {
                            $data->where('bank_name.id_bank_name', $row['operator']);
                        }

                        if ($row['subject'] == 'status') {
                            $data->where('disburse.disburse_status', $row['operator']);
                        }

                        if ($row['subject'] == 'outlet_code') {
                            if ($row['operator'] == '=') {
                                $data->where('outlets.outlet_code', $row['parameter']);
                            } else {
                                $data->where('outlets.outlet_code', 'like', '%' . $row['parameter'] . '%');
                            }
                        }

                        if ($row['subject'] == 'outlet_name') {
                            if ($row['operator'] == '=') {
                                $data->where('outlets.outlet_name', $row['parameter']);
                            } else {
                                $data->where('outlets.outlet_name', 'like', '%' . $row['parameter'] . '%');
                            }
                        }

                        if ($row['subject'] == 'account_number') {
                            if ($row['operator'] == '=') {
                                $data->where('disburse.beneficiary_account_number', $row['parameter']);
                            } else {
                                $data->where('disburse.beneficiary_account_number', 'like', '%' . $row['parameter'] . '%');
                            }
                        }

                        if ($row['subject'] == 'recipient_name') {
                            if ($row['operator'] == '=') {
                                $data->where('disburse.beneficiary_name', $row['parameter']);
                            } else {
                                $data->where('disburse.beneficiary_name', 'like', '%' . $row['parameter'] . '%');
                            }
                        }
                    }
                }
            } else {
                $data->where(function ($subquery) use ($post) {
                    foreach ($post['conditions'] as $row) {
                        if (isset($row['subject'])) {
                            if ($row['subject'] == 'error_status') {
                                $subquery->orWhere(function ($q) use ($row) {
                                    $q->where('disburse.error_code', $row['operator'])
                                        ->where('disburse.disburse_status', 'Fail');
                                });
                            }

                            if ($row['subject'] == 'bank_name') {
                                $subquery->orWhere('bank_name.id_bank_name', $row['operator']);
                            }

                            if ($row['subject'] == 'status') {
                                $subquery->orWhere('disburse.disburse_status', $row['operator']);
                            }

                            if ($row['subject'] == 'outlet_code') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('outlets.outlet_code', $row['parameter']);
                                } else {
                                    $subquery->orWhere('outlets.outlet_code', 'like', '%' . $row['parameter'] . '%');
                                }
                            }

                            if ($row['subject'] == 'outlet_name') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('outlets.outlet_name', $row['parameter']);
                                } else {
                                    $subquery->orWhere('outlets.outlet_name', 'like', '%' . $row['parameter'] . '%');
                                }
                            }

                            if ($row['subject'] == 'account_number') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('disburse.beneficiary_account_number', $row['parameter']);
                                } else {
                                    $subquery->orWhere('disburse.beneficiary_account_number', 'like', '%' . $row['parameter'] . '%');
                                }
                            }

                            if ($row['subject'] == 'recipient_name') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWheree('disburse.beneficiary_name', $row['parameter']);
                                } else {
                                    $subquery->orWhere('disburse.beneficiary_name', 'like', '%' . $row['parameter'] . '%');
                                }
                            }
                        }
                    }
                });
            }
        }

        if (isset($post['export']) && $post['export'] == 1) {
            $data = $data->selectRaw('disburse_status as "Disburse Status", bank_name.bank_name as "Bank Name", CONCAT(" ",disburse.beneficiary_account_number) as "Account Number", disburse.beneficiary_name as "Recipient Name", DATE_FORMAT(disburse.created_at, "%d %M %Y %H:%i") as "Date", CONCAT(outlets.outlet_code, " - ", outlets.outlet_name) as "Outlet", disburse_outlet.disburse_nominal as "Nominal Disburse",
                total_omset as "Total Gross Sales", total_discount as "Total Discount", total_delivery_price as "Total Delivery", 
                total_fee_item as "Total Fee Item", total_payment_charge as "Total Fee Payment", total_promo_charged as "Total Fee Promo",
                total_point_use_expense as "Total Fee Point Use", total_subscription as "Total Fee Subscription"')
                ->get()->toArray();
        } else {
            $data = $data->select(
                'disburse.error_code',
                'disburse.error_message',
                'disburse_outlet.id_disburse_outlet',
                'outlets.outlet_name',
                'outlets.outlet_code',
                'disburse.id_disburse',
                'disburse_outlet.disburse_nominal',
                'disburse.disburse_status',
                'disburse.beneficiary_account_number',
                'disburse.beneficiary_name',
                'disburse.created_at',
                'disburse.updated_at',
                'bank_name.bank_code',
                'bank_name.bank_name',
                'disburse.count_retry',
                'disburse.error_message'
            )->orderBy('disburse.created_at', 'desc')
                ->paginate(25);
        }

        return response()->json(MyHelper::checkGet($data));
    }

    public function listDisburseFailAction(Request $request)
    {
        $post = $request->json()->all();

        $data = Disburse::leftJoin('bank_name', 'bank_name.bank_code', 'disburse.beneficiary_bank_name')
            ->with(['disburse_outlet'])
            ->whereIn('disburse.disburse_status', ['Fail', 'Failed Create Payouts'])
            ->select(
                'disburse.error_code',
                'disburse.id_disburse',
                'disburse.disburse_nominal',
                'disburse.disburse_status',
                'disburse.beneficiary_account_number',
                'disburse.beneficiary_name',
                'disburse.created_at',
                'disburse.updated_at',
                'bank_name.bank_code',
                'bank_name.bank_name',
                'disburse.count_retry',
                'disburse.error_message'
            )->orderBy('disburse.created_at', 'desc');

        if (isset($post['id_user_franchise']) && !empty($post['id_user_franchise'])) {
            $data->whereIn('disburse.id_disburse', function ($query) use ($post) {
                $query->select('disburse_outlet.id_disburse')
                    ->from('disburse_outlet')
                    ->join('outlets', 'disburse_outlet.id_outlet', 'outlets.id_outlet')
                    ->join('user_franchise_outlet', 'user_franchise_outlet.id_outlet', 'disburse_outlet.id_outlet')
                    ->where('user_franchise_outlet.id_user_franchise', $post['id_user_franchise']);
            });
        }

        if (
            isset($post['date_start']) && !empty($post['date_start']) &&
            isset($post['date_end']) && !empty($post['date_end'])
        ) {
            $start_date = date('Y-m-d', strtotime($post['date_start']));
            $end_date = date('Y-m-d', strtotime($post['date_end']));

            $data->whereDate('disburse.created_at', '>=', $start_date)
                ->whereDate('disburse.created_at', '<=', $end_date);
        }

        if (isset($post['conditions']) && !empty($post['conditions'])) {
            $rule = 'and';
            if (isset($post['rule'])) {
                $rule = $post['rule'];
            }

            if ($rule == 'and') {
                foreach ($post['conditions'] as $row) {
                    if (isset($row['subject'])) {
                        if ($row['subject'] == 'error_status') {
                            $data->where('disburse.error_code', $row['operator']);
                        }

                        if ($row['subject'] == 'bank_name') {
                            $data->where('bank_name.id_bank_name', $row['operator']);
                        }

                        if ($row['subject'] == 'outlet_code') {
                            if ($row['operator'] == '=') {
                                $data->whereIn('disburse.id_disburse', function ($query) use ($row) {
                                    $query->select('disburse_outlet.id_disburse')
                                        ->from('disburse_outlet')
                                        ->join('outlets', 'disburse_outlet.id_outlet', 'outlets.id_outlet')
                                        ->where('outlets.outlet_code', $row['parameter']);
                                });
                            } else {
                                $data->whereIn('disburse.id_disburse', function ($query) use ($row) {
                                    $query->select('disburse_outlet.id_disburse')
                                        ->from('disburse_outlet')
                                        ->join('outlets', 'disburse_outlet.id_outlet', 'outlets.id_outlet')
                                        ->where('outlets.outlet_code', 'like', '%' . $row['parameter'] . '%');
                                });
                            }
                        }

                        if ($row['subject'] == 'outlet_name') {
                            if ($row['operator'] == '=') {
                                $data->whereIn('disburse.id_disburse', function ($query) use ($row) {
                                    $query->select('disburse_outlet.id_disburse')
                                        ->from('disburse_outlet')
                                        ->join('outlets', 'disburse_outlet.id_outlet', 'outlets.id_outlet')
                                        ->where('outlets.outlet_name', $row['parameter']);
                                });
                            } else {
                                $data->whereIn('disburse.id_disburse', function ($query) use ($row) {
                                    $query->select('disburse_outlet.id_disburse')
                                        ->from('disburse_outlet')
                                        ->join('outlets', 'disburse_outlet.id_outlet', 'outlets.id_outlet')
                                        ->where('outlets.outlet_name', 'like', '%' . $row['parameter'] . '%');
                                });
                            }
                        }

                        if ($row['subject'] == 'account_number') {
                            if ($row['operator'] == '=') {
                                $data->where('disburse.beneficiary_account_number', $row['parameter']);
                            } else {
                                $data->where('disburse.beneficiary_account_number', 'like', '%' . $row['parameter'] . '%');
                            }
                        }

                        if ($row['subject'] == 'recipient_name') {
                            if ($row['operator'] == '=') {
                                $data->where('disburse.beneficiary_name', $row['parameter']);
                            } else {
                                $data->where('disburse.beneficiary_name', 'like', '%' . $row['parameter'] . '%');
                            }
                        }
                    }
                }
            } else {
                $data->where(function ($subquery) use ($post) {
                    foreach ($post['conditions'] as $row) {
                        if (isset($row['subject'])) {
                            if ($row['subject'] == 'error_status') {
                                $subquery->orWhere('disburse.error_code', $row['operator']);
                            }

                            if ($row['subject'] == 'bank_name') {
                                $subquery->orWhere('bank_name.id_bank_name', $row['operator']);
                            }

                            if ($row['subject'] == 'outlet_code') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhereIn('disburse.id_disburse', function ($query) use ($row) {
                                        $query->select('disburse_outlet.id_disburse')
                                            ->from('disburse_outlet')
                                            ->join('outlets', 'disburse_outlet.id_outlet', 'outlets.id_outlet')
                                            ->where('outlets.outlet_code', $row['parameter']);
                                    });
                                } else {
                                    $subquery->orWhereIn('disburse.id_disburse', function ($query) use ($row) {
                                        $query->select('disburse_outlet.id_disburse')
                                            ->from('disburse_outlet')
                                            ->join('outlets', 'disburse_outlet.id_outlet', 'outlets.id_outlet')
                                            ->where('outlets.outlet_code', 'like', '%' . $row['parameter'] . '%');
                                    });
                                }
                            }

                            if ($row['subject'] == 'outlet_name') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhereIn('disburse.id_disburse', function ($query) use ($row) {
                                        $query->select('disburse_outlet.id_disburse')
                                            ->from('disburse_outlet')
                                            ->join('outlets', 'disburse_outlet.id_outlet', 'outlets.id_outlet')
                                            ->where('outlets.outlet_name', $row['parameter']);
                                    });
                                } else {
                                    $subquery->orWhereIn('disburse.id_disburse', function ($query) use ($row) {
                                        $query->select('disburse_outlet.id_disburse')
                                            ->from('disburse_outlet')
                                            ->join('outlets', 'disburse_outlet.id_outlet', 'outlets.id_outlet')
                                            ->where('outlets.outlet_name', 'like', '%' . $row['parameter'] . '%');
                                    });
                                }
                            }

                            if ($row['subject'] == 'account_number') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('disburse.beneficiary_account_number', $row['parameter']);
                                } else {
                                    $subquery->orWhere('disburse.beneficiary_account_number', 'like', '%' . $row['parameter'] . '%');
                                }
                            }

                            if ($row['subject'] == 'recipient_name') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWheree('disburse.beneficiary_name', $row['parameter']);
                                } else {
                                    $subquery->orWhere('disburse.beneficiary_name', 'like', '%' . $row['parameter'] . '%');
                                }
                            }
                        }
                    }
                });
            }
        }

        $data = $data->paginate(25);
        return response()->json(MyHelper::checkGet($data));
    }

    public function listTrx(Request $request)
    {
        $post = $request->json()->all();

        $data = Transaction::join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->leftJoin('disburse_outlet_transactions', 'transactions.id_transaction', 'disburse_outlet_transactions.id_transaction')
            ->leftJoin('disburse_outlet', 'disburse_outlet.id_disburse_outlet', 'disburse_outlet_transactions.id_disburse_outlet')
            ->leftJoin('disburse', 'disburse.id_disburse', 'disburse_outlet.id_disburse')
            ->where('transactions.transaction_payment_status', 'Completed')
            ->where('transactions.trasaction_type', '!=', 'Offline')
            ->select('disburse.disburse_status', 'transactions.*', 'outlets.outlet_name', 'outlets.outlet_code');

        if (
            isset($post['date_start']) && !empty($post['date_start']) &&
            isset($post['date_end']) && !empty($post['date_end'])
        ) {
            $start_date = date('Y-m-d', strtotime($post['date_start']));
            $end_date = date('Y-m-d', strtotime($post['date_end']));

            $data->whereDate('transactions.created_at', '>=', $start_date)
                ->whereDate('transactions.created_at', '<=', $end_date);
        }

        if (isset($post['id_user_franchise']) && !empty($post['id_user_franchise'])) {
            $data->join('user_franchise_outlet', 'user_franchise_outlet.id_outlet', 'disburse_outlet.id_outlet')
                ->where('user_franchise_outlet.id_user_franchise', $post['id_user_franchise']);
        }

        if (isset($post['conditions']) && !empty($post['conditions'])) {
            $rule = 'and';
            if (isset($post['rule'])) {
                $rule = $post['rule'];
            }

            if ($rule == 'and') {
                foreach ($post['conditions'] as $row) {
                    if (isset($row['subject'])) {
                        if ($row['subject'] == 'status') {
                            if ($row['operator'] == 'Unprocessed') {
                                $data->whereNull('disburse.disburse_status');
                            } else {
                                $data->where('disburse.disburse_status', $row['operator']);
                            }
                        }

                        if ($row['subject'] == 'recipient_number') {
                            if ($row['operator'] == '=') {
                                $data->where('transactions.transaction_receipt_number', $row['parameter']);
                            } else {
                                $data->where('transactions.transaction_receipt_number', 'like', '%' . $row['parameter'] . '%');
                            }
                        }
                    }
                }
            } else {
                $data->where(function ($subquery) use ($post) {
                    foreach ($post['conditions'] as $row) {
                        if (isset($row['subject'])) {
                            if ($row['subject'] == 'status') {
                                if ($row['operator'] == 'Unprocessed') {
                                    $subquery->orWhereNull('disburse_status');
                                } else {
                                    $subquery->orWhere('disburse_status', $row['operator']);
                                }
                            }

                            if ($row['subject'] == 'recipient_number') {
                                if ($row['operator'] == '=') {
                                    $subquery->orWhere('transactions.transaction_receipt_number', $row['parameter']);
                                } else {
                                    $subquery->orWhere('transactions.transaction_receipt_number', 'like', '%' . $row['parameter'] . '%');
                                }
                            }
                        }
                    }
                });
            }
        }

        $data = $data->paginate(25);
        return response()->json(MyHelper::checkGet($data));
    }

    public function detailDisburse(Request $request, $id)
    {
        $post = $request->json()->all();

        $disburse = Disburse::join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse')
            ->join('outlets', 'outlets.id_outlet', 'disburse_outlet.id_outlet')
            ->leftJoin('bank_name', 'bank_name.bank_code', 'disburse.beneficiary_bank_name')
            ->where('disburse_outlet.id_disburse_outlet', $id)
            ->select(
                'disburse_outlet.id_disburse_outlet',
                'outlets.outlet_name',
                'outlets.outlet_code',
                'disburse.id_disburse',
                'disburse_outlet.disburse_nominal',
                'disburse.disburse_status',
                'disburse.beneficiary_account_number',
                'disburse.beneficiary_name',
                'disburse.created_at',
                'disburse.updated_at',
                'bank_name.bank_code',
                'bank_name.bank_name'
            )->first();
        $data = Transaction::join('disburse_outlet_transactions', 'disburse_outlet_transactions.id_transaction', 'transactions.id_transaction')
            ->leftJoin('transaction_payment_balances', 'transaction_payment_balances.id_transaction', 'transactions.id_transaction')
            ->where('disburse_outlet_transactions.id_disburse_outlet', $id)
            ->select('disburse_outlet_transactions.*', 'transactions.*', 'transaction_payment_balances.balance_nominal');

        $config = [];
        if (isset($post['export']) && $post['export'] == 1) {
            $config = Configs::where('config_name', 'show or hide info calculation disburse')->first();
            $data = $data->get()->toArray();
        } else {
            $data = $data->paginate(25);
        }

        $result = [
            'status' => 'success',
            'result' => [
                'data_disburse' => $disburse,
                'list_trx' => $data,
                'config' => $config
            ]
        ];
        return response()->json($result);
    }

    public function listDisburseDataTable(Request $request, $status)
    {
        $post = $request->json()->all();

        $start = $post['start'];
        $length = $post['length'];

        $data = Disburse::join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse')
            ->join('outlets', 'outlets.id_outlet', 'disburse_outlet.id_outlet')
            ->leftJoin('bank_name', 'bank_name.bank_code', 'disburse.beneficiary_bank_name')
            ->select(
                'disburse_outlet.id_disburse_outlet as 0',
                DB::raw("CONCAT (outlets.outlet_code, ' - ',outlets.outlet_name) as '1'"),
                DB::raw("DATE_FORMAT(disburse.created_at, '%d %b %Y %H:%i') as '2'"),
                DB::raw('FORMAT(disburse.disburse_nominal,2) as "3"'),
                'disburse.disburse_status',
                'bank_name.bank_name as 4',
                'disburse.beneficiary_account_number as 5',
                'disburse.beneficiary_name as 6',
                'disburse.updated_at',
                'bank_name.bank_code'
            )->orderBy('disburse.created_at', 'desc');

        if ($status != 'all') {
            $data->where('disburse.disburse_status', $status);
        }

        if (isset($post['id_user_franchise']) && !empty($post['id_user_franchise'])) {
            $data->join('user_franchise_outlet', 'user_franchise_outlet.id_outlet', 'disburse_outlet.id_outlet')
                ->where('user_franchise_outlet.id_user_franchise', $post['id_user_franchise']);
        }

        if (isset($post['fitler_date']) &&  $post['fitler_date'] == 'today') {
            $data->whereDate('disburse.created_at', date('Y-m-d'));
        } elseif (isset($post['fitler_date']) &&  $post['fitler_date'] == 'specific_date') {
            if (
                isset($post['start_date']) && !empty($post['start_date']) &&
                isset($post['end_date']) && !empty($post['end_date'])
            ) {
                $start_date = date('Y-m-d', strtotime($post['start_date']));
                $end_date = date('Y-m-d', strtotime($post['end_date']));

                $data->whereDate('disburse.created_at', '>=', $start_date)
                    ->whereDate('disburse.created_at', '<=', $end_date);
            }
        }

        if (isset($post['id_outlet']) && !empty($post['id_outlet']) && $post['id_outlet'] != 'all') {
            $data->where('disburse_outlet.id_outlet', $post['id_outlet']);
        }

        $total = $data->count();
        $data = $data->skip($start)->take($length)->get()->toArray();
        $result = [
            'status' => 'success',
            'result' => $data,
            'total' => $total
        ];

        return response()->json($result);
    }

    public function listCalculationDataTable(Request $request)
    {
        $post = $request->json()->all();

        $start = $post['start'];
        $length = $post['length'];

        $data = DisburseOutletTransaction::join('transactions', 'transactions.id_transaction', 'disburse_outlet_transactions.id_transaction')
            ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->leftJoin('disburse_outlet', 'disburse_outlet.id_disburse_outlet', 'disburse_outlet_transactions.id_disburse_outlet')
            ->leftJoin('disburse', 'disburse.id_disburse', 'disburse_outlet.id_disburse')
            ->select(
                'disburse.disburse_status as 0',
                DB::raw("CONCAT (outlets.outlet_code, ' - ',outlets.outlet_name) as '1'"),
                DB::raw("DATE_FORMAT(disburse.created_at, '%d %b %Y %H:%i') as '2'"),
                DB::raw("DATE_FORMAT(transactions.transaction_date, '%d %b %Y %H:%i') as '3'"),
                'transactions.transaction_receipt_number as 4',
                DB::raw('FORMAT(transactions.transaction_grandtotal,2) as "5"'),
                DB::raw('FORMAT(transactions.transaction_discount,2) as "6"'),
                DB::raw('(transactions.transaction_shipment_go_send+transactions.transaction_shipment) as "7"'),
                DB::raw('FORMAT(transactions.transaction_subtotal,2) as "8"'),
                DB::raw('FORMAT(disburse_outlet_transactions.fee_item,2) as "9"'),
                DB::raw('FORMAT(disburse_outlet_transactions.payment_charge,2) as "10"'),
                DB::raw('FORMAT(disburse_outlet_transactions.discount,2) as "11"'),
                DB::raw('FORMAT(disburse_outlet_transactions.subscription,2) as "12"'),
                DB::raw('FORMAT(disburse_outlet_transactions.point_use_expense,2) as "13"'),
                DB::raw('FORMAT(disburse_outlet_transactions.income_outlet,2) as "14"'),
                DB::raw('FORMAT(disburse_outlet_transactions.income_central,2) as "15"'),
                DB::raw('FORMAT(disburse_outlet_transactions.expense_central,2) as "16"')
            )
            ->orderBy('transactions.transaction_date', 'desc');

        if (isset($post['fitler_date']) &&  $post['fitler_date'] == 'today') {
            $data->where(function ($q) {
                $q->whereDate('disburse.created_at', date('Y-m-d'))
                    ->orWhereDate('transactions.transaction_date', date('Y-m-d'));
            });
        } elseif (isset($post['fitler_date']) &&  $post['fitler_date'] == 'specific_date') {
            if (
                isset($post['start_date']) && !empty($post['start_date']) &&
                isset($post['end_date']) && !empty($post['end_date'])
            ) {
                $start_date = date('Y-m-d', strtotime($post['start_date']));
                $end_date = date('Y-m-d', strtotime($post['end_date']));

                $data->where(function ($qu) use ($start_date, $end_date) {
                    $qu->where(function ($q) use ($start_date, $end_date) {
                        $q->whereDate('disburse.created_at', '>=', $start_date)
                            ->whereDate('disburse.created_at', '<=', $end_date);
                    });

                    $qu->orWhere(function ($q) use ($start_date, $end_date) {
                        $q->whereDate('transactions.transaction_date', '>=', $start_date)
                            ->whereDate('transactions.transaction_date', '<=', $end_date);
                    });
                });
            }
        }

        if (isset($post['id_outlet']) && !empty($post['id_outlet']) && $post['id_outlet'] != 'all') {
            $data->where('transactions.id_outlet', $post['id_outlet']);
        }

        $total = $data->count();
        $data = $data->skip($start)->take($length)->get()->toArray();
        $result = [
            'status' => 'success',
            'result' => $data,
            'total' => $total
        ];

        return response()->json($result);
    }

    public function syncListBank()
    {
        $getListBank = MyHelper::connectIris('Banks', 'GET', 'api/v1/beneficiary_banks', []);

        if (isset($getListBank['status']) && $getListBank['status'] == 'success') {
            $getCurrentListBank = BankName::get()->toArray();
            $currentBank = array_column($getCurrentListBank, 'bank_code');

            $arrTmp = [];
            foreach ($getListBank['response']['beneficiary_banks'] as $dt) {
                $checkExist = array_search($dt['code'], $currentBank);
                if ($checkExist === false) {
                    $arrTmp[] = [
                        'bank_code' => $dt['code'],
                        'bank_name' => $dt['name']
                    ];
                }
            }

            BankName::insert($arrTmp);
        }

        return 'success';
    }


    public function userFranchise(Request $request)
    {
        $post = $request->json()->all();

        if (isset($post['user_type'])) {
            $data = UserFranchise::where('user_franchise_type', $post['user_type'])->get()->toArray();
        } elseif (isset($post['phone'])) {
            $data = UserFranchise::where('phone', $post['phone'])->first();
        }

        return response()->json(MyHelper::checkGet($data));
    }

    public function userFranchiseResetPassword(Request $request)
    {
        $post = $request->json()->all();
        $get = UserFranchise::where('id_user_franchise', $post['id_user_franchise'])->first();

        if (!password_verify($post['current_pin'], $get['password'])) {
            return response()->json(['status' => 'fail', 'message' => 'Current pin does not match']);
        } else {
            $update = UserFranchise::where('id_user_franchise', $post['id_user_franchise'])->update([
                'password' => bcrypt($post['pin']), 'password_default_plain_text' => null
            ]);

            if ($update) {
                return response()->json(['status' => 'success']);
            } else {
                return response()->json(['status' => 'fail', 'message' => 'Failed update pin']);
            }
        }
    }

    public function updateStatusDisburse(Request $request)
    {
        $post = $request->json()->all();
        $checkFirst = Disburse::where('id_disburse', $post['id'])->first();
        $getBank = DisburseOutlet::join('bank_account_outlets', 'bank_account_outlets.id_outlet', 'disburse_outlet.id_outlet')
                    ->join('bank_accounts', 'bank_accounts.id_bank_account', 'bank_account_outlets.id_bank_account')
                    ->orderBy('bank_accounts.id_bank_account', 'desc')->select('bank_accounts.*')
                    ->where('id_disburse', $post['id'])
                    ->first();
        $dataUpdate['id_bank_account'] = $getBank['id_bank_account'];

        if ($checkFirst['disburse_status'] == 'Failed Create Payouts') {
            $dataUpdate['disburse_status'] = 'Retry From Failed Payouts';
            $update = Disburse::where('id_disburse', $post['id'])->update($dataUpdate);
        } elseif (strpos($checkFirst['error_message'], "Partner does not have sufficient balance for the payout") !== false) {
            $dataUpdate['disburse_status'] = 'Queued';
            $update = Disburse::where('id_disburse', $post['id'])->update($dataUpdate);
        } else {
            $dataUpdate['disburse_status'] = $post['disburse_status'];
            $update = Disburse::where('id_disburse', $post['id'])->update($dataUpdate);
        }

        return response()->json(MyHelper::checkUpdate($update));
    }

    public function dashboardV2(Request $request)
    {
        $pending    = $this->getDisburseDashboardData($request, 'pending');
        $processed  = $this->getDisburseDashboardData($request, 'processed');

        $result = [
            'pending'   => $pending,
            'processed' => $processed
        ];

        return MyHelper::checkGet($result);
    }

    public function getDisburseDashboardData($request, $status)
    {
        $post = $request->json()->all();

        if ($status == 'pending') {
            $operator = '!=';
        } else {
            $operator = '=';
        }

        $nominal = Disburse::join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse');
        $income_central = Disburse::join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse');

        if ($status == 'processed') {
            $nominal = $nominal->whereIn('disburse.disburse_status', ['Success']);
            $income_central = $income_central->whereIn('disburse.disburse_status', ['Success']);

            $nominal_fail = Disburse::join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse')->whereIn('disburse.disburse_status', ['Fail', 'Failed Create Payouts']);
        }

        if ($status == 'pending') {
            $nominal = $nominal->whereNotIn('disburse.disburse_status', ['Fail', 'Failed Create Payouts', 'Success']);
            $income_central = $income_central->whereNotIn('disburse.disburse_status', ['Fail', 'Failed Create Payouts', 'Success']);

            $total_disburse = DisburseOutletTransaction::join('transactions', 'transactions.id_transaction', 'disburse_outlet_transactions.id_transaction')
                ->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
                ->where(function ($q) {
                    $q->whereNotNull('transaction_pickups.taken_at')
                        ->orWhereNotNull('transaction_pickups.taken_by_system_at');
                })
                ->where(function ($q) {
                    $q->orWhereNull('id_disburse_outlet')
                        ->orWhereIn('id_disburse_outlet', function ($query) {
                            $query->select('id_disburse_outlet')
                                ->from('disburse')
                                ->join('disburse_outlet', 'disburse.id_disburse', 'disburse_outlet.id_disburse')
                                ->where('disburse.disburse_status', 'Queued');
                        });
                });
        }

        if (isset($post['id_outlet']) && !empty($post['id_outlet']) && $post['id_outlet'] != 'all') {
            $nominal->where('disburse_outlet.id_outlet', $post['id_outlet']);
            $income_central->where('disburse_outlet.id_outlet', $post['id_outlet']);
            if ($status == 'processed') {
                $nominal_fail->where('disburse_outlet.id_outlet', $post['id_outlet']);
            }
            if ($status == 'pending') {
                $total_disburse->where('transactions.id_outlet', $post['id_outlet']);
            }
        }

        if (isset($post['fitler_date']) && $post['fitler_date'] == 'today') {
            $nominal->whereDate('disburse.created_at', date('Y-m-d'));
            $income_central->where('disburse.created_at', date('Y-m-d'));
            if ($status == 'processed') {
                $nominal_fail->whereDate('disburse.created_at', date('Y-m-d'));
            }
            if ($status == 'pending') {
                $total_disburse->where('transactions.transaction_date', date('Y-m-d'));
            }
        } elseif (isset($post['fitler_date']) && $post['fitler_date'] == 'specific_date') {
            if (
                isset($post['start_date']) && !empty($post['start_date']) &&
                isset($post['end_date']) && !empty($post['end_date'])
            ) {
                $start_date = date('Y-m-d', strtotime($post['start_date']));
                $end_date = date('Y-m-d', strtotime($post['end_date']));

                $nominal->whereDate('disburse.created_at', '>=', $start_date)
                    ->whereDate('disburse.created_at', '<=', $end_date);
                $income_central->whereDate('disburse.created_at', '>=', $start_date)
                    ->whereDate('disburse.created_at', '<=', $end_date);
                if ($status == 'processed') {
                    $nominal_fail->whereDate('disburse.created_at', '>=', $start_date)
                        ->whereDate('disburse.created_at', '<=', $end_date);
                }
                if ($status == 'pending') {
                    $total_disburse->whereDate('transactions.transaction_date', '>=', $start_date)
                        ->whereDate('transactions.transaction_date', '<=', $end_date);
                }
            }
        }

        if (isset($post['id_user_franchise']) && !empty($post['id_user_franchise'])) {
            $nominal->join('user_franchise_outlet', 'user_franchise_outlet.id_outlet', 'disburse_outlet.id_outlet')
                ->where('user_franchise_outlet.id_user_franchise', $post['id_user_franchise']);

            if ($status == 'processed') {
                $nominal_fail->join('user_franchise_outlet', 'user_franchise_outlet.id_outlet', 'disburse_outlet.id_outlet')
                    ->where('user_franchise_outlet.id_user_franchise', $post['id_user_franchise']);
            }
        }

        $nominal = $nominal->selectRaw(
            'SUM(disburse_outlet.disburse_nominal-(disburse.disburse_fee / disburse.total_outlet)) as "nom_success", 
        	SUM(disburse_outlet.total_fee_item) as "nom_item", 
        	SUM(disburse_outlet.total_omset) as "nom_grandtotal", 
        	SUM(disburse_outlet.total_expense_central) as "nom_expense_central", 
        	SUM(disburse_outlet.total_delivery_price) as "nom_delivery"'
        )->first();

        $income_central = $income_central->sum('total_income_central');
        if ($status == 'processed') {
            $nominal_fail = $nominal_fail->selectRaw('SUM(disburse_outlet.disburse_nominal-(disburse.disburse_fee / disburse.total_outlet)) as "disburse_nominal"')->first();
        }
        if ($status == 'pending') {
            $total_disburse = $total_disburse->sum('disburse_outlet_transactions.income_outlet');
        }

        $result = [
            'nominal' => $nominal,
            'nominal_fail' => $nominal_fail ?? 0,
            'income_central' => $income_central,
            'total_disburse' => $total_disburse ?? 0
        ];
        return $result;
        // return response()->json($result);
    }

    public function sendRecapTransactionEachOultet(Request $request)
    {
        $post = $request->json()->all();
        SendRecapManualy::dispatch(['data' => $post, 'type' => 'recap_transaction_each_outlet'])->onConnection('disbursequeue');
        return response()->json(['status' => 'success']);
    }

    public function exportToOutlet($post)
    {
        $start = date('Y-m-d', strtotime($post['date_start']));
        $end = date('Y-m-d', strtotime($post['date_end']));
        if (isset($post['all_outlet']) && !empty($post['all_outlet'])) {
            $transactions = Transaction::join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
                ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
                ->where('transaction_payment_status', 'Completed')
                ->whereNull('reject_at')
                ->whereDate('transactions.transaction_date', '>=', $start)
                ->whereDate('transactions.transaction_date', '<=', $end)
                ->select('outlets.id_outlet', 'outlets.outlet_email', 'outlets.outlet_code', 'outlets.outlet_name')
                ->groupBy('outlets.id_outlet')
                ->get()->toArray();

            foreach ($transactions as $getOutlet) {
                $dt['date_start'] = $start;
                $dt['date_end'] = $end;
                $dt['outlet_code'] = $getOutlet['outlet_code'];
                SendRecapManualy::dispatch(['data' => $dt, 'type' => 'recap_transaction_each_outlet'])->onConnection('disbursequeue');
            }
            return 'success';
        } else {
            $getOutlet = Outlet::where('outlet_code', $post['outlet_code'])->first();
            if ($getOutlet && !empty($getOutlet['outlet_email'])) {
                $filter['date_start'] = $start;
                $filter['date_end'] = $end;
                $filter['detail'] = 1;
                $filter['key'] = 'all';
                $filter['rule'] = 'and';
                $filter['conditions'] = [
                    [
                        'subject' => 'id_outlet',
                        'operator' => $getOutlet['id_outlet'],
                        'parameter' => null
                    ],
                    [
                        'subject' => 'status',
                        'operator' => 'Completed',
                        'parameter' => null
                    ]
                ];

                $summary = $this->summaryCalculationFee(null, $start, $end, $getOutlet['id_outlet'], 1);
                $generateTrx = app($this->trx)->exportTransaction($filter, 1);
                $dataDisburse = $this->summaryDisburse(null, $start, $end, $getOutlet['id_outlet'], 1);

                if (!empty($generateTrx['list'])) {
                    $excelFile = 'Transaction_[' . $start . '_' . $end . '][' . $getOutlet['outlet_code'] . '].xlsx';
                    $store  = (new MultipleSheetExport([
                        "Summary" => $summary,
                        "Calculation Fee" => $dataDisburse,
                        "Detail Transaction" => $generateTrx
                    ]))->store('excel_email/' . $excelFile);

                    if ($store) {
                        $tmpPath[] = storage_path('app/excel_email/' . $excelFile);
                    }

                    if (!empty($tmpPath)) {
                        $getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
                        $setting = array();
                        foreach ($getSetting as $key => $value) {
                            if ($value['key'] == 'email_setting_url') {
                                $setting[$value['key']]  = (array)json_decode($value['value_text']);
                            } else {
                                $setting[$value['key']] = $value['value'];
                            }
                        }

                        $data = array(
                            'customer' => '',
                            'html_message' => 'Report Outlet ' . $getOutlet['outlet_name'] . ', transaksi tanggal ' . date('d M Y', strtotime($start)) . ' sampai ' . date('d M Y', strtotime($end)),
                            'setting' => $setting
                        );

                        $to = $getOutlet['outlet_email'];
                        $subject = 'Report Transaksi [' . date('d M Y', strtotime($start)) . ' - ' . date('d M Y', strtotime($end)) . ']';
                        $name =  $getOutlet['outlet_name'];
                        $variables['attachment'] = $tmpPath;

                        try {
                            Mail::send('emails.test', $data, function ($message) use ($to, $subject, $name, $setting, $variables) {
                                $message->to($to, $name)->subject($subject);
                                if (!empty($setting['email_from']) && !empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender'], $setting['email_from']);
                                } elseif (!empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender']);
                                }

                                if (!empty($setting['email_reply_to']) && !empty($setting['email_reply_to_name'])) {
                                    $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                                } elseif (!empty($setting['email_reply_to'])) {
                                    $message->replyTo($setting['email_reply_to']);
                                }

                                if (!empty($setting['email_cc']) && !empty($setting['email_cc_name'])) {
                                    $message->cc($setting['email_cc'], $setting['email_cc_name']);
                                }

                                if (!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])) {
                                    $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                                }

                                // attachment
                                if (isset($variables['attachment']) && !empty($variables['attachment'])) {
                                    foreach ($variables['attachment'] as $attach) {
                                        $message->attach($attach);
                                    }
                                }
                            });
                        } catch (\Exception $e) {
                        }

                        foreach ($tmpPath as $t) {
                            File::delete($t);
                        }
                    }
                }

                return 'success';
            } else {
                return 'Outlet Not Found';
            }
        }
    }

    public function sendRecapDisburseEachOultet(Request $request)
    {
        $post = $request->json()->all();
        SendRecapManualy::dispatch(['data' => $post, 'type' => 'recap_disburse_each_outlet'])->onConnection('disbursequeue');
        return 'Success';
    }

    public function sendDisburseWithRangeDate($post)
    {
        $start = date('Y-m-d', strtotime($post['date_start']));
        $end = date('Y-m-d', strtotime($post['date_end']));
        $settingSendEmail = Setting::where('key', 'disburse_setting_email_send_to')->first();
        $disburse = Disburse::join('disburse_outlet', 'disburse_outlet.id_disburse', 'disburse.id_disburse')
            ->join('outlets', 'outlets.id_outlet', 'disburse_outlet.id_outlet')
            ->whereDate('disburse.created_at', '>=', $start)
            ->whereDate('disburse.created_at', '<=', $end)
            ->where('outlets.outlet_code', $post['outlet_code'])
            ->where('send_email_status', 1)
            ->groupBy('disburse.id_disburse')
            ->select('disburse.*')
            ->get()->toArray();

        foreach ($disburse as $getDataDisburse) {
            if (!empty($settingSendEmail)) {
                $feeDisburse = (int)$getDataDisburse['disburse_fee'];
                //get status franchise
                $getOutlet = DisburseOutlet::join('outlets', 'outlets.id_outlet', 'disburse_outlet.id_outlet')
                    ->where('disburse_outlet.id_disburse', $getDataDisburse['id_disburse'])
                    ->pluck('outlets.status_franchise')->toArray();
                if (!$getOutlet) {
                    return false;
                }
                //check if data disburse have outlet franchise
                //if have outlet franchise, send email to use setting outlet franchise
                //if no have otulet franchise, send email to use setting outlet central
                $check = in_array("1", $getOutlet);

                $sendEmailTo = '';
                $getSettingSendEmail = (array)json_decode($settingSendEmail['value_text']);
                if ($check === false) {
                    $sendEmailTo = $getSettingSendEmail['outlet_central'];
                } else {
                    $sendEmailTo = $getSettingSendEmail['outlet_franchise'];
                }

                $disburseOutlet = DisburseOutlet::join('disburse_outlet_transactions as dot', 'dot.id_disburse_outlet', 'disburse_outlet.id_disburse_outlet')
                    ->join('transactions as t', 'dot.id_transaction', 't.id_transaction')
                    ->join('outlets as o', 'o.id_outlet', 't.id_outlet')
                    ->where('disburse_outlet.id_disburse', $getDataDisburse['id_disburse'])
                    ->groupBy(DB::raw('DATE(t.transaction_date)'), 't.id_outlet');

                if ($sendEmailTo == 'Email Outlet') {
                    $disburseOutlet = $disburseOutlet->selectRaw('t.transaction_date, o.outlet_code, o.outlet_name, o.outlet_email, Sum(income_outlet) as nominal')
                        ->get()->toArray();
                    $feePerOutlet = $feeDisburse / $getDataDisburse['total_outlet'];
                    $data = [];
                    foreach ($disburseOutlet as $dt) {
                        $check = array_search($dt['outlet_code'], array_column($data, 'outlet_code'));
                        if ($check === false) {
                            $data[] = [
                                'outlet_code' => $dt['outlet_code'],
                                'outlet_name' => $dt['outlet_name'],
                                'outlet_email' => $dt['outlet_email'],
                                'datas' => [[
                                    'Transaction Date' => date('d M Y', strtotime($dt['transaction_date'])),
                                    'Outlet' => $dt['outlet_code'] . ' - ' . $dt['outlet_name'],
                                    'Nominal' => number_format($dt['nominal'], 2)
                                ]]
                            ];
                        } else {
                            $data[$check]['datas'][] = [
                                'Transaction Date' => date('d M Y', strtotime($dt['transaction_date'])),
                                'Outlet' => $dt['outlet_code'] . ' - ' . $dt['outlet_name'],
                                'Nominal' => number_format($dt['nominal'], 2)
                            ];
                        }
                    }

                    /*send excel to outlet*/
                    if (!empty($data)) {
                        foreach ($data as $val) {
                            if ($val['outlet_email']) {
                                $fileName = 'Disburse_[' . date('d M Y', strtotime($getDataDisburse['created_at'])) . ']_[' . $val['outlet_code'] . ']_[' . $getDataDisburse['reference_no'] . '].xlsx';
                                $path = storage_path('app/excel_email/' . $fileName);
                                $val['datas'][] = [
                                    'Transaction Date' => '',
                                    'Outlet' => 'Fee Disburse',
                                    'Nominal' => -$feePerOutlet
                                ];
                                if (!Storage::disk(env('local'))->exists('excel_email')) {
                                    Storage::makeDirectory('excel_email');
                                }
                                $store = (new FastExcel($val['datas']))->export($path);

                                if ($store) {
                                    $getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
                                    $setting = array();
                                    foreach ($getSetting as $key => $value) {
                                        if ($value['key'] == 'email_setting_url') {
                                            $setting[$value['key']]  = (array)json_decode($value['value_text']);
                                        } else {
                                            $setting[$value['key']] = $value['value'];
                                        }
                                    }

                                    $data = array(
                                        'customer' => '',
                                        'html_message' => 'Laporan Disburse tanggal ' . date('d M Y', strtotime($getDataDisburse['created_at'])) . ' untuk outlet ' . $val['outlet_code'] . '-' . $val['outlet_name'] . '.',
                                        'setting' => $setting
                                    );

                                    $to = $val['outlet_email'];
                                    $subject = 'Report Disburse [' . date('d M Y', strtotime($getDataDisburse['created_at'])) . '][' . $val['outlet_code'] . ']';
                                    $name =  $val['outlet_name'];
                                    $variables['attachment'] = [$path];

                                    try {
                                        Mail::send('emails.test', $data, function ($message) use ($to, $subject, $name, $setting, $variables) {
                                            $message->to($to, $name)->subject($subject);
                                            if (!empty($setting['email_from']) && !empty($setting['email_sender'])) {
                                                $message->from($setting['email_sender'], $setting['email_from']);
                                            } elseif (!empty($setting['email_sender'])) {
                                                $message->from($setting['email_sender']);
                                            }

                                            if (!empty($setting['email_reply_to']) && !empty($setting['email_reply_to_name'])) {
                                                $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                                            } elseif (!empty($setting['email_reply_to'])) {
                                                $message->replyTo($setting['email_reply_to']);
                                            }

                                            if (!empty($setting['email_cc']) && !empty($setting['email_cc_name'])) {
                                                $message->cc($setting['email_cc'], $setting['email_cc_name']);
                                            }

                                            if (!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])) {
                                                $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                                            }

                                            // attachment
                                            if (isset($variables['attachment']) && !empty($variables['attachment'])) {
                                                foreach ($variables['attachment'] as $attach) {
                                                    $message->attach($attach);
                                                }
                                            }
                                        });
                                    } catch (\Exception $e) {
                                    }

                                    foreach ($variables['attachment'] as $t) {
                                        File::delete($t);
                                    }
                                }
                            }
                        }
                    }
                } elseif ($sendEmailTo == 'Email Bank') {
                    $disburseOutlet = $disburseOutlet->selectRaw('DATE_FORMAT(t.transaction_date, "%d %M %Y") as "Transaction Date", CONCAT(o.outlet_code, " - ", o.outlet_name) AS Outlet, FORMAT(SUM(income_outlet), 2) as Nominal')
                        ->get()->toArray();

                    if ($getDataDisburse['beneficiary_email']) {
                        $fileName = 'Disburse_[' . date('d M Y', strtotime($getDataDisburse['created_at'])) . '][' . $getDataDisburse['reference_no'] . '].xlsx';
                        $path = storage_path('app/excel_email/' . $fileName);
                        $listOutlet = array_column($disburseOutlet, 'Outlet');
                        $disburseOutlet[] = [
                            'Transaction Date' => '',
                            'Outlet' => 'Fee Disburse',
                            'Nominal' => -$feeDisburse
                        ];
                        if (!Storage::disk(env('local'))->exists('excel_email')) {
                            Storage::makeDirectory('excel_email');
                        }

                        $store = (new FastExcel($disburseOutlet))->export($path);

                        if ($store) {
                            $getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
                            $setting = array();
                            foreach ($getSetting as $key => $value) {
                                if ($value['key'] == 'email_setting_url') {
                                    $setting[$value['key']]  = (array)json_decode($value['value_text']);
                                } else {
                                    $setting[$value['key']] = $value['value'];
                                }
                            }

                            $data = array(
                                'customer' => '',
                                'html_message' => 'Laporan Disburse tanggal ' . date('d M Y', strtotime($getDataDisburse['created_at'])) . '.<br><br> List Outlet : <br>' . implode('<br>', $listOutlet),
                                'setting' => $setting
                            );

                            $to = $getDataDisburse['beneficiary_email'];
                            $subject = 'Report Disburse [' . date('d M Y', strtotime($getDataDisburse['created_at'])) . ']';
                            $name =  $getDataDisburse['beneficiary_name'];
                            $variables['attachment'] = [$path];

                            try {
                                Mail::send('emails.test', $data, function ($message) use ($to, $subject, $name, $setting, $variables) {
                                    $message->to($to, $name)->subject($subject);
                                    if (!empty($setting['email_from']) && !empty($setting['email_sender'])) {
                                        $message->from($setting['email_sender'], $setting['email_from']);
                                    } elseif (!empty($setting['email_sender'])) {
                                        $message->from($setting['email_sender']);
                                    }

                                    if (!empty($setting['email_reply_to']) && !empty($setting['email_reply_to_name'])) {
                                        $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                                    } elseif (!empty($setting['email_reply_to'])) {
                                        $message->replyTo($setting['email_reply_to']);
                                    }

                                    if (!empty($setting['email_cc']) && !empty($setting['email_cc_name'])) {
                                        $message->cc($setting['email_cc'], $setting['email_cc_name']);
                                    }

                                    if (!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])) {
                                        $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                                    }

                                    // attachment
                                    if (isset($variables['attachment']) && !empty($variables['attachment'])) {
                                        foreach ($variables['attachment'] as $attach) {
                                            $message->attach($attach);
                                        }
                                    }
                                });
                            } catch (\Exception $e) {
                            }

                            foreach ($variables['attachment'] as $t) {
                                File::delete($t);
                            }
                        }
                    }
                }
            }
        }
    }

    public function sendRecapTransactionOultet(Request $request)
    {
        $post = $request->json()->all();
        SendRecapManualy::dispatch(['date' => $post['date'], 'type' => 'recap_transaction_to_outlet'])->onConnection('disbursequeue');
        return 'Success';
    }

    public function cronSendEmailDisburse($date = null)
    {
        $log = MyHelper::logCron('Send Email Recap To Outlet');
        try {
            $currentDate = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime($currentDate . "-1 days"));
            if (!empty($date)) {
                $yesterday =  date('Y-m-d', strtotime($date));
            }

            $getOultets = Transaction::join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
                ->where('transaction_payment_status', 'Completed')->whereNull('reject_at')
                ->whereDate('transaction_date', $yesterday)
                ->groupBy('transactions.id_outlet')->pluck('id_outlet');
            $getEmail = Outlet::whereIn('id_outlet', $getOultets)
                ->whereNotNull('outlet_email')
                ->groupBy('outlet_email')
                ->pluck('outlet_email');

            if (!empty($getEmail)) {
                foreach ($getEmail as $e) {
                    $email = $e;
                    $tmpPath = [];
                    $tmpOutlet = [];
                    $outlets = Outlet::where('outlet_email', $e)->select('id_outlet', 'outlet_code', 'outlet_name', 'outlet_email')->get()->toArray();
                    foreach ($outlets as $outlet) {
                        if (empty($outlet['outlet_email'])) {
                            continue 2;
                        }
                        $filter['date_start'] = $yesterday;
                        $filter['date_end'] = $yesterday;
                        $filter['detail'] = 1;
                        $filter['key'] = 'all';
                        $filter['rule'] = 'and';
                        $filter['conditions'] = [
                            [
                                'subject' => 'id_outlet',
                                'operator' => $outlet['id_outlet'],
                                'parameter' => null
                            ],
                            [
                                'subject' => 'status',
                                'operator' => 'Completed',
                                'parameter' => null
                            ]
                        ];

                        $summary = $this->summaryCalculationFee($yesterday, null, null, $outlet['id_outlet'], 1);
                        $generateTrx = app($this->trx)->exportTransaction($filter, 1);
                        $dataDisburse = $this->summaryDisburse($yesterday, null, null, $outlet['id_outlet'], 1);

                        if (!empty($generateTrx['list'])) {
                            $excelFile = 'Transaction_[' . $yesterday . ']_[' . $outlet['outlet_code'] . '].xlsx';
                            $store  = (new MultipleSheetExport([
                                "Summary" => $summary,
                                "Calculation Fee" => $dataDisburse,
                                "Detail Transaction" => $generateTrx
                            ]))->store('excel_email/' . $excelFile);

                            if ($store) {
                                $tmpPath[] = storage_path('app/excel_email/' . $excelFile);
                                $tmpOutlet[] = $outlet['outlet_code'] . ' - ' . $outlet['outlet_name'];
                            }
                        }
                    }

                    if (!empty($tmpPath)) {
                        $getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();
                        $setting = array();
                        foreach ($getSetting as $key => $value) {
                            if ($value['key'] == 'email_setting_url') {
                                $setting[$value['key']]  = (array)json_decode($value['value_text']);
                            } else {
                                $setting[$value['key']] = $value['value'];
                            }
                        }

                        $data = array(
                            'customer' => '',
                            'html_message' => 'Report Transaksi tanggal ' . date('d M Y', strtotime($yesterday)) . '.<br><br> List Outlet : <br>' . implode('<br>', $tmpOutlet),
                            'setting' => $setting
                        );

                        $to = $outlets[0]['outlet_email'];
                        $subject = 'Report Transaksi [' . date('d M Y', strtotime($yesterday)) . ']';
                        $name =  $outlets[0]['outlet_name'];
                        $variables['attachment'] = $tmpPath;

                        try {
                            Mail::send('emails.test', $data, function ($message) use ($to, $subject, $name, $setting, $variables) {
                                $message->to($to, $name)->subject($subject);
                                if (!empty($setting['email_from']) && !empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender'], $setting['email_from']);
                                } elseif (!empty($setting['email_sender'])) {
                                    $message->from($setting['email_sender']);
                                }

                                if (!empty($setting['email_reply_to']) && !empty($setting['email_reply_to_name'])) {
                                    $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                                } elseif (!empty($setting['email_reply_to'])) {
                                    $message->replyTo($setting['email_reply_to']);
                                }

                                if (!empty($setting['email_cc']) && !empty($setting['email_cc_name'])) {
                                    $message->cc($setting['email_cc'], $setting['email_cc_name']);
                                }

                                if (!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])) {
                                    $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                                }

                                // attachment
                                if (isset($variables['attachment']) && !empty($variables['attachment'])) {
                                    foreach ($variables['attachment'] as $attach) {
                                        $message->attach($attach);
                                    }
                                }
                            });
                        } catch (\Exception $e) {
                            \Log::error($e);
                            \Log::error($email);
                        }

                        foreach ($tmpPath as $t) {
                            File::delete($t);
                        }
                    }
                }
            }

            $log->success();
            return 'succes';
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        };
    }

    public function sendRecap(Request $request)
    {
        $post = $request->json()->all();
        SendRecapManualy::dispatch(['date' => $post['date'], 'type' => 'recap_to_admin'])->onConnection('disbursequeue');

        return 'Success';
    }

    public function shortcutRecap($date = null)
    {
        $log = MyHelper::logCron('Send Recap Disburse');
        try {
            $currentDate = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime($currentDate . "-1 days"));
            if (!empty($date)) {
                $yesterday =  date('Y-m-d', strtotime($date));
            }

            $filter['date_start'] = $yesterday;
            $filter['date_end'] = $yesterday;
            $filter['detail'] = 1;
            $filter['key'] = 'all';
            $filter['rule'] = 'and';
            $filter['show_product_code'] = 1;
            $filter['show_another_income'] = 1;
            $filter['conditions'] = [
                [
                    'subject' => 'status',
                    'operator' => 'Completed',
                    'parameter' => null
                ]
            ];

            $generateTrx = app($this->trx)->exportTransaction($filter, 1);
            $dataDisburse = $this->summaryDisburse($yesterday, null, null, null, 1);

            $getEmailTo = Setting::where('key', 'email_to_send_recap_transaction')->first();

            if (!empty($dataDisburse) && !empty($generateTrx['list']) && !empty($getEmailTo['value'])) {
                $excelFile = 'Transaction_[' . $yesterday . '].xlsx';
                $summary = $this->summaryCalculationFee($yesterday, null, null, null, 1);
                $summary['show_another_income'] = 1;
                $generateTrx['show_product_code'] = 1;
                $generateTrx['show_another_income'] = 1;
                $store  = (new MultipleSheetExport([
                    "Summary" => $summary,
                    "Calculation Fee" => ['data' => $dataDisburse, 'show_another_income' => 1],
                    "Detail Transaction" => $generateTrx
                ]))->store('excel_email/' . $excelFile);

                if ($store) {
                    $tmpPath[] = storage_path('app/excel_email/' . $excelFile);
                }

                if (!empty($tmpPath)) {
                    $getSetting = Setting::where('key', 'LIKE', 'email%')->get()->toArray();

                    $setting = array();
                    foreach ($getSetting as $key => $value) {
                        if ($value['key'] == 'email_setting_url') {
                            $setting[$value['key']]  = (array)json_decode($value['value_text']);
                        } else {
                            $setting[$value['key']] = $value['value'];
                        }
                    }

                    $data = array(
                        'customer' => '',
                        'html_message' => 'Report Transaksi tanggal ' . date('d M Y', strtotime($yesterday)),
                        'setting' => $setting
                    );

                    $to = $getEmailTo['value'];
                    $subject = 'Report Transaksi [' . date('d M Y', strtotime($yesterday)) . ']';
                    $name =  '';
                    $variables['attachment'] = $tmpPath;

                    try {
                        Mail::send('emails.test', $data, function ($message) use ($to, $subject, $name, $setting, $variables) {
                            $message->to($to, $name)->subject($subject);
                            if (!empty($setting['email_from']) && !empty($setting['email_sender'])) {
                                $message->from($setting['email_sender'], $setting['email_from']);
                            } elseif (!empty($setting['email_sender'])) {
                                $message->from($setting['email_sender']);
                            }

                            if (!empty($setting['email_reply_to']) && !empty($setting['email_reply_to_name'])) {
                                    $message->replyTo($setting['email_reply_to'], $setting['email_reply_to_name']);
                            } elseif (!empty($setting['email_reply_to'])) {
                                $message->replyTo($setting['email_reply_to']);
                            }

                            if (!empty($setting['email_cc']) && !empty($setting['email_cc_name'])) {
                                $message->cc($setting['email_cc'], $setting['email_cc_name']);
                            }

                            if (!empty($setting['email_bcc']) && !empty($setting['email_bcc_name'])) {
                                $message->bcc($setting['email_bcc'], $setting['email_bcc_name']);
                            }

                            // attachment
                            if (isset($variables['attachment']) && !empty($variables['attachment'])) {
                                foreach ($variables['attachment'] as $attach) {
                                    $message->attach($attach);
                                }
                            }
                        });
                    } catch (\Exception $e) {
                    }

                    foreach ($tmpPath as $t) {
                        File::delete($t);
                    }
                }
            }

            $log->success();
            return 'succes';
        } catch (\Exception $e) {
            $log->fail($e->getMessage());
        };
    }

    public function summaryCalculationFee($date = null, $date_start = null, $date_end = null, $id_outlet = null, $check_reject_at = 0, $use_filter = [])
    {
        $summaryFee = [];
        $summaryFee = DisburseOutletTransaction::join('transactions', 'transactions.id_transaction', 'disburse_outlet_transactions.id_transaction')
            ->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
            ->leftJoin('transaction_payment_subscriptions as tps', 'tps.id_transaction', 'transactions.id_transaction')
            ->where('transactions.transaction_payment_status', 'Completed')
            ->selectRaw('COUNT(transactions.id_transaction) total_trx, SUM(transactions.transaction_grandtotal) as total_gross_sales,
                        SUM(tps.subscription_nominal) as total_subscription, 
                        SUM(bundling_product_total_discount) as total_discount_bundling,
                        SUM(transactions.transaction_subtotal) as total_sub_total, 
                        SUM(transactions.transaction_shipment_go_send+transactions.transaction_shipment) as total_delivery, SUM(transactions.transaction_discount) as total_discount, 
                        SUM(fee_item) total_fee_item, SUM(payment_charge) total_fee_pg, SUM(income_outlet) total_income_outlet,
                        SUM(discount_central) total_income_promo, SUM(subscription_central) total_income_subscription, SUM(bundling_product_fee_central) total_income_bundling_product,
                        SUM(fee_promo_payment_gateway_central) total_income_promo_payment_gateway, SUM(fee_promo_payment_gateway_outlet+fee_promo_payment_gateway_central) total_promo_payment_gateway,
                        SUM(transactions.transaction_discount_delivery) total_discount_delivery');

        $summaryProduct = TransactionProduct::join('transactions', 'transactions.id_transaction', 'transaction_products.id_transaction')
            ->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
            ->join('products as p', 'p.id_product', 'transaction_products.id_product')
            ->where('transaction_payment_status', 'Completed')
            ->where('p.product_type', 'product')
            ->groupBy('transaction_products.id_product_variant_group')
            ->groupBy('transaction_products.id_product')
            ->selectRaw("p.product_name as name, SUM(transaction_products.transaction_product_qty) as total_qty,
                        p.product_type as type,
                        (SELECT GROUP_CONCAT(pv.`product_variant_name` SEPARATOR ',') FROM `product_variant_groups` pvg
                        JOIN `product_variant_pivot` pvp ON pvg.`id_product_variant_group` = pvp.`id_product_variant_group`
                        JOIN `product_variants` pv ON pv.`id_product_variant` = pvp.`id_product_variant`
                        WHERE pvg.`id_product_variant_group` = transaction_products.id_product_variant_group) as variants");

        $summaryModifier = TransactionProductModifier::join('transactions', 'transactions.id_transaction', 'transaction_product_modifiers.id_transaction')
            ->join('transaction_products as tp', 'tp.id_transaction_product', 'transaction_product_modifiers.id_transaction_product')
            ->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
            ->join('product_modifiers as pm', 'pm.id_product_modifier', 'transaction_product_modifiers.id_product_modifier')
            ->where('transaction_payment_status', 'Completed')
            ->whereNull('transaction_product_modifiers.id_product_modifier_group')
            ->groupBy('transaction_product_modifiers.id_product_modifier')
            ->selectRaw("pm.text as name, 'Modifier' as type, SUM(transaction_product_modifiers.qty * tp.transaction_product_qty) as total_qty,
                        NULL as variants");

        $summaryProductPlastic = TransactionProduct::join('transactions', 'transactions.id_transaction', 'transaction_products.id_transaction')
            ->join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
            ->join('products as p', 'p.id_product', 'transaction_products.id_product')
            ->where('transaction_payment_status', 'Completed')
            ->where('p.product_type', 'plastic')
            ->groupBy('transaction_products.id_product_variant_group')
            ->groupBy('transaction_products.id_product')
            ->selectRaw("p.product_name as name, SUM(transaction_products.transaction_product_qty) as total_qty,
                        p.product_type as type,
                        (SELECT GROUP_CONCAT(pv.`product_variant_name` SEPARATOR ',') FROM `product_variant_groups` pvg
                        JOIN `product_variant_pivot` pvp ON pvg.`id_product_variant_group` = pvp.`id_product_variant_group`
                        JOIN `product_variants` pv ON pv.`id_product_variant` = pvp.`id_product_variant`
                        WHERE pvg.`id_product_variant_group` = transaction_products.id_product_variant_group) as variants");

        if (!empty($date)) {
            $summaryFee = $summaryFee->whereDate('transactions.transaction_date', $date);
            $summaryProduct = $summaryProduct->whereDate('transactions.transaction_date', $date);
            $summaryModifier = $summaryModifier->whereDate('transactions.transaction_date', $date);
            $summaryProductPlastic = $summaryProductPlastic->whereDate('transactions.transaction_date', $date);
        } else {
            $summaryFee = $summaryFee->whereDate('transaction_date', '>=', $date_start)
                ->whereDate('transaction_date', '<=', $date_end);
            $summaryProduct = $summaryProduct->whereDate('transactions.transaction_date', '>=', $date_start)
                ->whereDate('transaction_date', '<=', $date_end);
            $summaryModifier = $summaryModifier->whereDate('transaction_date', '>=', $date_start)
                ->whereDate('transaction_date', '<=', $date_end);
            $summaryProductPlastic = $summaryProductPlastic->whereDate('transaction_date', '>=', $date_start)
                ->whereDate('transaction_date', '<=', $date_end);
        }

        if ($id_outlet) {
            $summaryFee = $summaryFee->where('transactions.id_outlet', $id_outlet);
            $summaryProduct = $summaryProduct->where('transactions.id_outlet', $id_outlet);
            $summaryModifier = $summaryModifier->where('transactions.id_outlet', $id_outlet);
            $summaryProductPlastic = $summaryProductPlastic->where('transactions.id_outlet', $id_outlet);
        }

        if ($check_reject_at == 1) {
            $summaryFee = $summaryFee->whereNull('transaction_pickups.reject_at');
            $summaryProduct = $summaryProduct->whereNull('transaction_pickups.reject_at');
            $summaryModifier = $summaryModifier->whereNull('transaction_pickups.reject_at');
            $summaryProductPlastic = $summaryProductPlastic->whereNull('transaction_pickups.reject_at');
        }

        if (!empty($use_filter)) {
            $summaryFee = app('Modules\Franchise\Http\Controllers\ApiTransactionFranchiseController')->filterTransaction($summaryFee, $use_filter);
            $summaryProduct = app('Modules\Franchise\Http\Controllers\ApiTransactionFranchiseController')->filterTransaction($summaryProduct, $use_filter);
            $summaryModifier = app('Modules\Franchise\Http\Controllers\ApiTransactionFranchiseController')->filterTransaction($summaryModifier, $use_filter);
            $summaryProductPlastic = app('Modules\Franchise\Http\Controllers\ApiTransactionFranchiseController')->filterTransaction($summaryProductPlastic, $use_filter);
        }

        $summaryFee = $summaryFee->first()->toArray();
        $summaryProduct = $summaryProduct->get()->toArray();
        $summaryModifier = $summaryModifier->get()->toArray();
        $summaryProductPlastic = $summaryProductPlastic->get()->toArray();

        $summary = array_merge($summaryProduct, $summaryModifier, $summaryProductPlastic);
        $config = Configs::where('config_name', 'show or hide info calculation disburse')->first();
        return [
            'summary_product' => $summary,
            'summary_fee' => $summaryFee,
            'config' => $config
        ];
    }

    public function summaryDisburse($date = null, $date_start = null, $date_end = null, $id_outlet = null, $check_reject_at = 0, $use_filter = [])
    {
        $data = Transaction::join('transaction_pickups', 'transaction_pickups.id_transaction', 'transactions.id_transaction')
            ->join('outlets', 'outlets.id_outlet', 'transactions.id_outlet')
            ->join('disburse_outlet_transactions as dot', 'dot.id_transaction', 'transactions.id_transaction')
            ->leftJoin('promo_payment_gateway_transactions as promo_pg', 'promo_pg.id_transaction', 'transactions.id_transaction')
            ->leftJoin('transaction_payment_balances', 'transaction_payment_balances.id_transaction', 'transactions.id_transaction')
            ->leftJoin('transaction_payment_midtrans', 'transactions.id_transaction', '=', 'transaction_payment_midtrans.id_transaction')
            ->leftJoin('transaction_payment_ipay88s', 'transactions.id_transaction', '=', 'transaction_payment_ipay88s.id_transaction')
            ->leftJoin('transaction_payment_shopee_pays', 'transactions.id_transaction', '=', 'transaction_payment_shopee_pays.id_transaction')
            ->leftJoin('rule_promo_payment_gateway', 'rule_promo_payment_gateway.id_rule_promo_payment_gateway', '=', 'dot.id_rule_promo_payment_gateway')
            ->where('transaction_payment_status', 'Completed')
            ->with(['transaction_payment_subscription' => function ($q) {
                $q->join('subscription_user_vouchers', 'subscription_user_vouchers.id_subscription_user_voucher', 'transaction_payment_subscriptions.id_subscription_user_voucher')
                    ->join('subscription_users', 'subscription_users.id_subscription_user', 'subscription_user_vouchers.id_subscription_user')
                    ->leftJoin('subscriptions', 'subscriptions.id_subscription', 'subscription_users.id_subscription');
            }, 'vouchers.deal', 'promo_campaign', 'subscription_user_voucher.subscription_user.subscription'])
            ->select(
                'promo_pg.total_received_cashback',
                'rule_promo_payment_gateway.name as promo_payment_gateway_name',
                'transactions.id_subscription_user_voucher',
                'transaction_payment_shopee_pays.id_transaction_payment_shopee_pay',
                'payment_type',
                'payment_method',
                'dot.*',
                'outlets.outlet_name',
                'outlets.outlet_code',
                'transactions.transaction_receipt_number',
                'transactions.transaction_date',
                'transactions.transaction_shipment_go_send',
                'transactions.transaction_shipment',
                'transactions.transaction_grandtotal',
                'transactions.transaction_discount_delivery',
                'transactions.transaction_discount',
                'transactions.transaction_subtotal',
                'transactions.id_promo_campaign_promo_code'
            );

        if (!empty($date)) {
            $data = $data->whereDate('transaction_date', $date);
        } else {
            $data = $data->whereDate('transaction_date', '>=', $date_start)
                ->whereDate('transaction_date', '<=', $date_end);
        }

        if ($id_outlet) {
            $data = $data->where('transactions.id_outlet', $id_outlet);
        }

        if ($check_reject_at == 1) {
            $data = $data->whereNull('transaction_pickups.reject_at');
        }

        if (!empty($use_filter)) {
            $data = app('Modules\Franchise\Http\Controllers\ApiTransactionFranchiseController')->filterTransaction($data, $use_filter);
        }

        $data = $data->get()->toArray();

        return $data;
    }
}
