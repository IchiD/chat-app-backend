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
   * @param string|array $messageOrData ユーザーに表示するエラーメッセージまたはエラーデータ
   * @return array            エラーレスポンスの配列
   */
  protected function errorResponse(string $errorType, $messageOrData): array
  {
    if (is_array($messageOrData)) {
      return array_merge([
        'status'     => self::STATUS_ERROR,
        'error_type' => $errorType,
      ], $messageOrData);
    }

    return [
      'status'     => self::STATUS_ERROR,
      'error_type' => $errorType,
      'message'    => $messageOrData,
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

  /**
   * 例外を処理してエラーレスポンスを返す共通メソッド
   */
  protected function handleException(string $context, \Exception $e, string $errorType = 'internal_error', string $message = '処理中にエラーが発生しました'): array
  {
    \Illuminate\Support\Facades\Log::error($context . ': ' . $e->getMessage(), [
      'line' => $e->getLine(),
      'file' => $e->getFile(),
    ]);

    return $this->errorResponse($errorType, $message);
  }
}
