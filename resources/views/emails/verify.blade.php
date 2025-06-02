<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <title>メール認証のお願い</title>
</head>

<body>
  <p>{{ $user->email }} 様、</p>
  <p>以下のリンクをクリックして、本登録を完了してください。（リンクの有効期限は60分です。）</p>
  <p>
    <a href="{{ $verificationUrl }}">本登録を完了する</a>
  </p>
  <p>このリンクをクリックすると、自動的にログインされます。</p>
</body>

</html>