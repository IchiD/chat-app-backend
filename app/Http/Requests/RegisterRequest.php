<?php

namespace App\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends BaseFormRequest
{
  /**
   * ユーザーがこのリクエストを実行する権限があるかどうかを判断する
   *
   * @return bool
   */
  public function authorize()
  {
    // 認証前のリクエストでも利用可能
    return true;
  }

  /**
   * リクエストに適用するバリデーションルールを取得する
   *
   * @return array
   */
  public function rules()
  {
    return [
      'email'    => 'required|email',
      'password' => 'required|min:6|regex:/\A[!-~]+\z/|confirmed',
      'password_confirmation' => 'required|min:6',
      'name'     => 'required|string|max:10',
    ];
  }

  /**
   * バリデーションエラーメッセージ
   *
   * @return array
   */
  public function messages()
  {
    return [
      'email.required'   => 'メールアドレスを入力してください。',
      'email.email'      => 'メールアドレスの形式が正しくありません。',
      'password.required' => 'パスワードを入力してください。',
      'password.string'   => 'パスワードは文字列で入力してください。',
      'password.min'      => 'パスワードは6文字以上で入力してください。',
      'password.regex'    => 'パスワードは半角英数字と半角記号のみを使用してください。',
      'password.confirmed' => 'パスワードと確認用パスワードが一致しません。',
      'password_confirmation.required' => '確認用パスワードを入力してください。',
      'password_confirmation.min' => '確認用パスワードは6文字以上で入力してください。',
      'name.required'    => '名前を入力してください。',
      'name.string'      => '名前は文字列で入力してください。',
      'name.max'         => '名前は10文字以内で入力してください。',
    ];
  }
}
