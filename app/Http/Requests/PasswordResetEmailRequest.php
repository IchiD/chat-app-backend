<?php

namespace App\Http\Requests;

// use Illuminate\Foundation\Http\FormRequest;

class PasswordResetEmailRequest extends BaseFormRequest
{
  /**
   * ユーザーがこのリクエストを行う権限があるかどうかを判断する
   *
   * ※ パスワードリセットリンクのリクエストは未認証でも行うので true を返す
   */
  public function authorize()
  {
    return true;
  }

  /**
   * バリデーションルールを定義する
   */
  public function rules()
  {
    return [
      'email' => 'required|email',
    ];
  }

  /**
   * バリデーションエラーメッセージのカスタマイズ
   */
  public function messages()
  {
    return [
      'email.required' => 'メールアドレスを入力してください。',
      'email.email'    => 'メールアドレスの形式が正しくありません。',
    ];
  }
}
