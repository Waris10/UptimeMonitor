<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Monitor Down</title>
</head>

<body style="font-family: sans-serif;">
    <h2 style="color: #b91c1c;">Monitor down</h2>

    <p><strong>URL:</strong> {{ $url }}</p>
    <p><strong>Consecutive failures:</strong> {{ $consecutiveFailures }}</p>
    <p><strong>Detected at:</strong> {{ optional($detectedAt)->toDayDateTimeString() }} UTC</p>

    <p>The monitor has crossed its failure threshold. You'll receive a follow-up message when the URL returns to a
        healthy state.</p>
</body>

</html>