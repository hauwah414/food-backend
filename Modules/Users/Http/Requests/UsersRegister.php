<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UsersRegister extends FormRequest
{
    public function rules()
    {
        return [
            'name'                  => 'required',
            'gender'                => 'required|in:Male,Female',
            'birthday'              => 'required|date_format:Y-m-d',
            'password'              => 'required',
            'id_department'              => 'required',
            'phone'                 => 'required|unique:users,phone',
            'email'                 => 'required|unique:users,email',
           ]; 
    }
    public function messages()
    {
        return [
            'required' => ':attribute harus diisi',
        ];
    }
    public function authorize()
    {
        return true;
    }

    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json(['status' => 'fail', 'messages'  => $validator->errors()->all()], 200));
    }

    public function validationData()
    {
        return $this->all();
    }
}