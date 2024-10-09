<?php

namespace App\Http\Middleware;

use App\Http\Models\UserFeature;
use Closure;

class FeatureControl
{
  /**
   * Handle an incoming request.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  \Closure  $next
   * @return mixed
   */
    public function handle($request, Closure $next, $feature, $feature2 = null)
    {
        if ($request->user()['level'] == "Super Admin"||$request->user()['level'] == "Mitra") {
            return $next($request);
        }

        $granted = UserFeature::where('id_user', $request->user()['id'])->where('id_feature', $feature)->first();
        if (!$granted) {
            return response()->json(['error' => 'Unauthenticated action'], 403);
        } else {
            return $next($request);
        }
    }
}
