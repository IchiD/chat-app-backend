<?php

namespace App\Services;

abstract class BaseService
{

  public const STATUS_ERROR   = 'error';
  public const STATUS_SUCCESS = 'success';

  /**
   * 共通のエラーレスポンスを生成するメソッド
   *
   * @param string $errorType エラータイプの識別子
   * @param string $message   ユーザーに表示するエラーメッセージ
   * @return array            エラーレスポンスの配列
   */
  protected function errorResponse(string $errorType, string $message): array
  {
    return [
      'status'     => self::STATUS_ERROR,
      'error_type' => $errorType,
      'message'    => $message,
    ];
  }

  /**
   * 共通の成功レスポンスを生成するメソッド
   *
   * @param string $message   ユーザーに表示するメッセージ
   * @param array  $data      追加情報（オプション）
   * @return array            成功レスポンスの配列
   */
  protected function successResponse(string $message, array $data = []): array
  {
    return array_merge([
      'status'  => self::STATUS_SUCCESS,
      'message' => $message,
    ], $data);
  }
}
