<?php

namespace Modules\Outlet\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use App\Http\Models\Outlet;
use App\Http\Models\OutletDoctor;
use App\Http\Models\OutletDoctorSchedule;
use App\Http\Models\OutletHoliday;
use App\Http\Models\OutletPhoto;
use App\Http\Models\City;
use App\Lib\MyHelper;
use Validator;
use Hash;
use DB;
use Mail;
use Modules\Outlet\Http\Requests\Sync;

class ApiSyncOutletController extends Controller
{
    public $saveImage = "img/outlet/";

    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    /* CEK  INPUTAN */
    public function checkInputOutlet($post = [])
    {
        $data = [];

        if (isset($post['outlet_code'])) {
            $data['outlet_code'] = $post['outlet_code'];
        }

        if (isset($post['outlet_name'])) {
            $data['outlet_name'] = $post['outlet_name'];
        }

        if (isset($post['outlet_address'])) {
            $data['outlet_address'] = $post['outlet_address'];
        }

        // khususon city
        if (isset($post['id_city']) && !empty($post['id_city'])) {
            $data['id_city'] = $post['id_city'];
        } else {
            $data['id_city'] = 501;
        }

        if (isset($post['outlet_postal_code'])) {
            $data['outlet_postal_code'] = $post['outlet_postal_code'];
        }
        if (isset($post['outlet_phone'])) {
            $data['outlet_phone'] = $post['outlet_phone'];
        }
        if (isset($post['outlet_email'])) {
            $data['outlet_email'] = $post['outlet_email'];
        }

        if (isset($post['outlet_open_hours'])) {
            $data['outlet_open_hours'] = $post['outlet_open_hours'];
        }

        if (isset($post['outlet_close_hours'])) {
            $data['outlet_close_hours'] = $post['outlet_close_hours'];
        }

        return $data;
    }

    /* search city */
    public function searchCity($city)
    {
        $cityId = City::where('city_name', 'LIKE', '%' . $city . '%')->first();

        if (!empty($cityId)) {
            return $cityId->id_city;
        }
    }

    /* SYNC */
    public function sync(Sync $request)
    {
        $outlet = $request->json('outlet');
        $data_pin = [];

        foreach ($outlet as $key => $value) {
            $value['id_city'] = $this->searchCity($value['city']);
            $data             = $this->checkInputOutlet($value);

            $save = Outlet::updateOrCreate(['outlet_code' => $data['outlet_code']], $data);

            if ($save) {
                $outlet = Outlet::where('id_outlet', $save['id_outlet'])->first();
                if (empty($outlet->outlet_pin)) {
                    $pin = MyHelper::createRandomPIN(6, 'angka');
                    $outlet->update(['outlet_pin' => \Hash::make($pin)]);
                    $data_pin[] = ['id_outlet' => $outlet->id_outlet, 'data' => $pin];
                }
                continue;
            } else {
                return response()->json([
                    'status'   => 'fail',
                    'messages' => ['fail to sync']
                ]);
            }
        }

        MyHelper::updateOutletFile($data_pin);
        // return success
        return response()->json([
            'status' => 'success'
        ]);
    }
}
