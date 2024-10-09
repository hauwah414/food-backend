<?php

namespace Modules\Setting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Setting\Http\Requests\Version\VersionList;
use App\Http\Models\Setting;
use Modules\Setting\Entities\Version;
use App\Lib\MyHelper;
use DB;
use App\Http\Models\SumberDana;
class ApiSumberDana extends Controller
{
    public function index()
    {
        $result = SumberDana::select('sumber_dana')->get()->toArray();
        $data = array();
        foreach ($result as $value) {
            $data[] = $value['sumber_dana'];
        }
        return response()->json(MyHelper::checkGet($data));
    }

    public function getSumberDana()
    {
        $result = SumberDana::get()->toArray();
        return response()->json(MyHelper::checkGet($result));
    }

    public function updateSumberDana(Request $request)
    {
        $post = $request->json()->all();
        foreach ($post as $key => $data) {
             {
                $datas = [];
               foreach($data as $value){
                  $data = SumberDana::updateOrCreate(['sumber_dana' => $value['sumber-dana']]);
                  $datas[]=$value['sumber-dana'];
               }
               $delete = SumberDana::whereNotIn('sumber_dana',$datas)->delete();
                return response()->json(['status' => 'success']);
            }
        }
    }
}
