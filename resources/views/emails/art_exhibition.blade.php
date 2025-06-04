
<html>
<body>
    <h2>Art Exhibition Information</h2>
    @foreach($mailData as $art)
        <p>Title: {{ $art['title'] }}</p>
        <p>Artist: {{ $art['artist_name'] }}</p>
        <p>Exhibition: {{ $art['exhibition_name'] }}</p>
        <hr>
    @endforeach
    <p><strong>Note:</strong> Please log in to your account to check more details.</p>
</body>
</html>
