<?php

return [

  /*
    |--------------------------------------------------------------------------
    | バリデーション用メッセージ
    |--------------------------------------------------------------------------
    |
    | ここに定義されているメッセージは、バリデーションでエラーが発生した際に
    | ユーザーに表示されるデフォルトのエラーメッセージです。サイズなどのルールには
    | 複数のバージョンが存在する場合もありますので、アプリケーションの要件に合わせて
    | 自由に変更してください。
    |
    */

  'accepted'             => ':attribute を承認する必要があります。',
  'accepted_if'          => ':other が :value の場合、:attribute を承認する必要があります。',
  'active_url'           => ':attribute は有効なURLではありません。',
  'after'                => ':attribute には、:date より後の日付を指定してください。',
  'after_or_equal'       => ':attribute には、:date 以降の日付を指定してください。',
  'alpha'                => ':attribute は英字のみで構成されなければなりません。',
  'alpha_dash'           => ':attribute は英字、数字、ハイフン、アンダースコアのみで構成してください。',
  'alpha_num'            => ':attribute は英字と数字のみで構成してください。',
  'array'                => ':attribute は配列でなければなりません。',
  'ascii'                => ':attribute はシングルバイトの英数字および記号のみで構成してください。',
  'before'               => ':attribute には、:date より前の日付を指定してください。',
  'before_or_equal'      => ':attribute には、:date 以前の日付を指定してください。',
  'between'              => [
    'array'   => ':attribute の項目数は :min から :max 個でなければなりません。',
    'file'    => ':attribute のファイルサイズは :min から :max キロバイトの間でなければなりません。',
    'numeric' => ':attribute は :min から :max の間でなければなりません。',
    'string'  => ':attribute は :min 文字から :max 文字の間でなければなりません。',
  ],
  'boolean'              => ':attribute には true か false を指定してください。',
  'can'                  => ':attribute に不正な値が含まれています。',
  'confirmed'            => ':attribute の確認が一致しません。',
  'contains'             => ':attribute は必要な値を含んでいません。',
  'current_password'     => '現在のパスワードが正しくありません。',
  'date'                 => ':attribute は有効な日付ではありません。',
  'date_equals'          => ':attribute は :date と同じ日付でなければなりません。',
  'date_format'          => ':attribute は :format の形式と一致しません。',
  'decimal'              => ':attribute は :decimal 桁の小数点以下の数字を含んでいなければなりません。',
  'declined'             => ':attribute は拒否されなければなりません。',
  'declined_if'          => ':other が :value の場合、:attribute は拒否されなければなりません。',
  'different'            => ':attribute と :other は異なっている必要があります。',
  'digits'               => ':attribute は :digits 桁でなければなりません。',
  'digits_between'       => ':attribute は :min 桁から :max 桁の間でなければなりません。',
  'dimensions'           => ':attribute の画像サイズが無効です。',
  'distinct'             => ':attribute に重複した値があります。',
  'doesnt_end_with'      => ':attribute は次のいずれかで終わってはいけません: :values。',
  'doesnt_start_with'    => ':attribute は次のいずれかで始まってはいけません: :values。',
  'email'                => ':attribute には有効なメールアドレスを指定してください。',
  'ends_with'            => ':attribute は次のいずれかで終わらなければなりません: :values。',
  'enum'                 => '選択された :attribute は無効です。',
  'exists'               => '選択された :attribute は無効です。',
  'extensions'           => ':attribute は次の拡張子のいずれかでなければなりません: :values。',
  'file'                 => ':attribute はファイルでなければなりません。',
  'filled'               => ':attribute には値が必要です。',
  'gt'                   => [
    'array'   => ':attribute は :value 個より多い項目が必要です。',
    'file'    => ':attribute のファイルサイズは :value キロバイトより大きくなければなりません。',
    'numeric' => ':attribute は :value より大きくなければなりません。',
    'string'  => ':attribute は :value 文字より多くなければなりません。',
  ],
  'gte'                  => [
    'array'   => ':attribute は :value 個以上の項目が必要です。',
    'file'    => ':attribute のファイルサイズは :value キロバイト以上でなければなりません。',
    'numeric' => ':attribute は :value 以上でなければなりません。',
    'string'  => ':attribute は :value 文字以上でなければなりません。',
  ],
  'hex_color'            => ':attribute は有効な16進カラーコードでなければなりません。',
  'image'                => ':attribute には画像ファイルを指定してください。',
  'in'                   => '選択された :attribute は無効です。',
  'in_array'             => ':attribute は :other に存在していなければなりません。',
  'integer'              => ':attribute は整数でなければなりません。',
  'ip'                   => ':attribute には有効なIPアドレスを指定してください。',
  'ipv4'                 => ':attribute には有効なIPv4アドレスを指定してください。',
  'ipv6'                 => ':attribute には有効なIPv6アドレスを指定してください。',
  'json'                 => ':attribute には有効なJSON文字列を指定してください。',
  'list'                 => ':attribute はリスト形式でなければなりません。',
  'lowercase'            => ':attribute は小文字でなければなりません。',
  'lt'                   => [
    'array'   => ':attribute は :value 個未満の項目でなければなりません。',
    'file'    => ':attribute のファイルサイズは :value キロバイト未満でなければなりません。',
    'numeric' => ':attribute は :value 未満でなければなりません。',
    'string'  => ':attribute は :value 文字未満でなければなりません。',
  ],
  'lte'                  => [
    'array'   => ':attribute は :value 個以下でなければなりません。',
    'file'    => ':attribute のファイルサイズは :value キロバイト以下でなければなりません。',
    'numeric' => ':attribute は :value 以下でなければなりません。',
    'string'  => ':attribute は :value 文字以下でなければなりません。',
  ],
  'mac_address'          => ':attribute は有効なMACアドレスでなければなりません。',
  'max'                  => [
    'array'   => ':attribute は :max 個以下の項目でなければなりません。',
    'file'    => ':attribute のファイルサイズは :max キロバイト以下でなければなりません。',
    'numeric' => ':attribute は :max 以下でなければなりません。',
    'string'  => ':attribute は :max 文字以下でなければなりません。',
  ],
  'max_digits'           => ':attribute は :max 桁以下でなければなりません。',
  'mimes'                => ':attribute には :values タイプのファイルを指定してください。',
  'mimetypes'            => ':attribute には :values タイプのファイルを指定してください。',
  'min'                  => [
    'array'   => ':attribute には最低でも :min 個の項目が必要です。',
    'file'    => ':attribute のファイルサイズは最低でも :min キロバイトでなければなりません。',
    'numeric' => ':attribute は最低でも :min 以上でなければなりません。',
    'string'  => ':attribute は最低でも :min 文字以上でなければなりません。',
  ],
  'min_digits'           => ':attribute は最低でも :min 桁必要です。',
  'missing'              => ':attribute は存在していてはいけません。',
  'missing_if'           => ':other が :value の場合、:attribute は存在していてはいけません。',
  'missing_unless'       => ':other が :value でない場合、:attribute は存在していてはいけません。',
  'missing_with'         => ':values が存在する場合、:attribute は存在していてはいけません。',
  'missing_with_all'     => ':values が存在する場合、:attribute は存在していてはいけません。',
  'multiple_of'          => ':attribute は :value の倍数でなければなりません。',
  'not_in'               => '選択された :attribute は無効です。',
  'not_regex'            => ':attribute の形式が正しくありません。',
  'numeric'              => ':attribute は数字でなければなりません。',
  'password'             => [
    'letters'       => ':attribute には最低1文字のアルファベットが必要です。',
    'mixed'         => ':attribute には最低1文字の大文字と1文字の小文字の両方が必要です。',
    'numbers'       => ':attribute には最低1つの数字が必要です。',
    'symbols'       => ':attribute には最低1つの記号が必要です。',
    'uncompromised' => '入力された :attribute は漏洩情報に含まれている可能性があるため、別の :attribute を使用してください。',
  ],
  'present'              => ':attribute は必ず存在していなければなりません。',
  'present_if'           => ':other が :value の場合、:attribute は必ず存在していなければなりません。',
  'present_unless'       => ':other が :value でない場合、:attribute は必ず存在していなければなりません。',
  'present_with'         => ':values が存在する場合、:attribute も必ず存在していなければなりません。',
  'present_with_all'     => ':values が存在する場合、:attribute も必ず存在していなければなりません。',
  'prohibited'           => ':attribute の入力は禁止されています。',
  'prohibited_if'        => ':other が :value の場合、:attribute の入力は禁止されています。',
  'prohibited_unless'    => ':other が :values に含まれていない場合、:attribute の入力は禁止されています。',
  'prohibits'            => ':attribute により :other の入力が禁止されています。',
  'regex'                => ':attribute の形式が正しくありません。',
  'required'             => ':attribute は必須です。',
  'required_array_keys'  => ':attribute には、次の項目が含まれている必要があります: :values。',
  'required_if'          => ':other が :value の場合、:attribute は必須です。',
  'required_if_accepted' => ':other が承認されている場合、:attribute は必須です。',
  'required_if_declined' => ':other が拒否されている場合、:attribute は必須です。',
  'required_unless'      => ':other が :values に含まれていない場合、:attribute は必須です。',
  'required_with'        => ':values が存在する場合、:attribute は必須です。',
  'required_with_all'    => ':values が存在する場合、:attribute は必須です。',
  'required_without'     => ':values が存在しない場合、:attribute は必須です。',
  'required_without_all' => ':values のどれも存在しない場合、:attribute は必須です。',
  'same'                 => ':attribute と :other は一致していなければなりません。',
  'size'                 => [
    'array'   => ':attribute は :size 個の項目でなければなりません。',
    'file'    => ':attribute のファイルサイズは :size キロバイトでなければなりません。',
    'numeric' => ':attribute は :size でなければなりません。',
    'string'  => ':attribute は :size 文字でなければなりません。',
  ],
  'starts_with'          => ':attribute は次のいずれかで始まらなければなりません: :values。',
  'string'               => ':attribute は文字列でなければなりません。',
  'timezone'             => ':attribute は有効なタイムゾーンでなければなりません。',
  'unique'               => ':attribute は既に使用されています。',
  'uploaded'             => ':attribute のアップロードに失敗しました。',
  'uppercase'            => ':attribute は大文字でなければなりません。',
  'url'                  => ':attribute は有効なURLでなければなりません。',
  'ulid'                 => ':attribute は有効なULIDでなければなりません。',
  'uuid'                 => ':attribute は有効なUUIDでなければなりません。',

  /*
    |--------------------------------------------------------------------------
    | カスタムバリデーション用メッセージ
    |--------------------------------------------------------------------------
    |
    | ここでは、特定の属性とルールに対するカスタムメッセージを指定できます。
    | "attribute.rule" の形式でキーを設定することで、個別にカスタマイズできます。
    |
    */

  'custom' => [
    'attribute-name' => [
      'rule-name' => 'カスタムメッセージ',
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | カスタム属性名
    |--------------------------------------------------------------------------
    |
    | 以下の言語行は、エラーメッセージ中の属性プレースホルダーを、
    | ユーザーにわかりやすい名称に置き換えるために使用されます。
    | 例: "email" を "Eメールアドレス" にするなど。
    |
    */

  'attributes' => [],

];
