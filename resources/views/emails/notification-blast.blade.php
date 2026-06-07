<x-mail::message>
# {{ $notificationTitle }}

{!! nl2br(e($notificationMessage)) !!}

<x-mail::button :url="config('app.url')">
Visit National Olympiad Hunt
</x-mail::button>

Thanks,<br>
**National Olympiad Hunt Team**
</x-mail::message>
