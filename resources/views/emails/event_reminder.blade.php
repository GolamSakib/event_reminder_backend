<!DOCTYPE html>
<html>
<head>
    <title>Event Reminder</title>
</head>
<body>
    <h1>Reminder for Your Upcoming Event</h1>
    <p>Your event "{{ $event->name }}" is starting soon!</p>
    <p>Start Time: {{ $event->startDate }}</p>
</body>
</html>
