<?php

namespace App\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends BaseFormRequest
{
  /**
   * ユーザーがこのリクエストを行う権限があるかどうかを判断する
   */
  public function authorize()
  {
    // 認証前のリクエストでも利用可能。
    return true;
  }

  /**
   * バリデーションルールを取得する
   */
  public function rules()
  {
    return [
      'email'    => 'required|email',
      'password' => 'required|string',
    ];
  }

  /**
   * バリデーションエラーメッセージのカスタマイズ
   */
  public function messages()
  {
    return [
      'email.required'   => 'メールアドレスを入力してください。',
      'email.email'      => 'メールアドレスの形式が正しくありません。',
      'password.required' => 'パスワードを入力してください。',
      'password.string'   => 'パスワードは文字列で入力してください。',
    ];
  }
}
