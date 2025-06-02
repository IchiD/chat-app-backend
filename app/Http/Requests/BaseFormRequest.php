<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

abstract class BaseFormRequest extends FormRequest
{
  /**
   * バリデーション失敗時のレスポンスをカスタマイズ
   *
   * @param \Illuminate\Contracts\Validation\Validator $validator
   * @throws \Illuminate\Http\Exceptions\HttpResponseException
   */
  protected function failedValidation(Validator $validator)
  {
    $errors = $validator->errors();
    $response = [
      'status'     => 'error',
      'error_type' => 'validation_error',
      'message'    => '入力に誤りがあります。',
      'errors'     => $errors->toArray(),
    ];

    throw new HttpResponseException(response()->json($response, 422));
  }
}
