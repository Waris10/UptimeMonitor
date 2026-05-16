<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Monitor Recovered</title>
</head>

<body style="font-family: sans-serif;">
    <h2 style="color: #047857;">Monitor recovered</h2>

    <p><strong>URL:</strong> {{ $url }}</p>
    <p><strong>Recovered at:</strong> {{ optional($recoveredAt)->toDayDateTimeString() }} UTC</p>

    <p>The monitor returned a successful response and is back up.</p>
</body>

</html>