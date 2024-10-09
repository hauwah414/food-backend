<?php

namespace Modules\Deals\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class Update extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'id_deals'                  => 'nullable|integer',
            'id_deals_promotion_template' => 'nullable|integer',
            'deals_type'                => 'required|in:Deals,Hidden,Point,Spin,Subscription,WelcomeVoucher,Promotion,Quest',
            'deals_voucher_type'        => 'sometimes|required|in:Auto generated,List Vouchers,Unlimited',
            'deals_promo_id'            => 'nullable',
            'deals_title'               => 'required',
            'deals_second_title'        => '',
            'deals_description'         => '',
            'deals_short_description'   => '',
            'deals_image'               => '',
            'deals_video'               => '',
            'id_product'                => 'nullable|integer',
            'deals_start'               => 'sometimes|nullable|date|date_format:"Y-m-d H:i:s"',
            'deals_end'                 => 'sometimes|nullable|date|date_format:"Y-m-d H:i:s"|after_or_equal:deals_start',
            'deals_publish_start'       => 'sometimes|nullable|date|date_format:"Y-m-d H:i:s"',
            'deals_publish_end'         => 'sometimes|nullable|date|date_format:"Y-m-d H:i:s"|after_or_equal:deals_publish_start',
            'deals_voucher_duration'    => '',
            'deals_voucher_price_point' => '',
            'deals_voucher_price_cash'  => '',
            'deals_total_voucher'       => '',
            'deals_total_claimed'       => '',
            'deals_total_redeemed'      => '',
            'deals_total_used'          => '',
            'id_outlet'                 => 'sometimes|array',
        ];

        if ($this->deals_voucher_start) {
            $rules['deals_voucher_expired'] = 'nullable|date|date_format:"Y-m-d H:i:s"|after:deals_voucher_start';
        } else {
            $rules['deals_voucher_expired'] = 'nullable|date|date_format:"Y-m-d H:i:s"|after:' . date('Y-m-d H:i:s') . '';
        }

        return $rules;
    }

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(['status' => 'fail', 'messages'  => $validator->errors()->all()], 200));
    }

    protected function validationData()
    {
        return $this->json()->all();
    }

    public function attributes()
    {
        $attributes = [
            'deals_voucher_expired'     => 'Voucher Expiry',
            'deals_voucher_start'       => 'Voucher Start Date'
        ];

        return $attributes;
    }
}
