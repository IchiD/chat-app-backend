<?php

namespace App\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends BaseFormRequest
{
  /**
   * このリクエストが認可されるかを判断する。
   * （必要に応じて認可ロジックを追加してください。）
   *
   * @return bool
   */
  public function authorize()
  {
    // ここでは全ユーザーに許可する例
    return true;
  }

  /**
   * リクエストに対するバリデーションルールを取得する。
   *
   * @return array
   */
  public function rules()
  {
    return [
      'token' => 'required',
      'email'  => 'required|email',
      'password' => 'required|min:6|confirmed',
    ];
  }

  /**
   * バリデーションエラーメッセージをカスタマイズする。
   *
   * @return array
   */
  public function messages()
  {
    return [
      'token.required'        => 'トークンが見つかりません。',
      'email.required'        => 'メールアドレスを入力してください。',
      'email.email'           => 'メールアドレスの形式が正しくありません。',
      'password.required'     => 'パスワードを入力してください。',
      'password.min'          => 'パスワードは6文字以上で入力してください。',
      'password.confirmed'    => 'パスワードと確認用パスワードが一致しません。',
    ];
  }
}
