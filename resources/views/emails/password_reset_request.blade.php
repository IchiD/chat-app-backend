<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>パスワード再設定のお知らせ</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f9f9f9;
      color: #333;
      padding: 20px;
    }

    .container {
      max-width: 600px;
      margin: 0 auto;
      background-color: #fff;
      padding: 20px;
      border: 1px solid #ddd;
      border-radius: 0;
    }

    .button {
      display: inline-block;
      padding: 10px 20px;
      margin-top: 20px;
      background-color: #3490dc;
      color: #ffffff !important;
      text-decoration: none;
      border-radius: 0;
    }
  </style>
</head>

<body>
  <div class="container">
    <p>パスワードの再設定リクエスト</p>

    <p>
      パスワードを再設定するには下記のリンクをクリックして、新しいパスワードの設定を行ってください。<br>
      （リンクの有効期限は60分間です。）
    </p>

    <p>
      <a href="{!! $resetUrl !!}" class="button">パスワード再設定</a>
    </p>

    <p>
      よろしくお願いいたします。<br>
      サポートチーム
    </p>
  </div>
</body>

</html>