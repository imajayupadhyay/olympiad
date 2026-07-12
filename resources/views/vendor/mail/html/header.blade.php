@props(['url'])

@php
    $baseUrl = rtrim((string) config('app.url'), '/');
    $host = parse_url($baseUrl, PHP_URL_HOST);

    if (! $host || in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
        $baseUrl = 'https://neoexam.org';
    }
@endphp

<tr>
    <td class="header" style="background: #0A1024; border-bottom: 4px solid #EE6A2C; padding: 28px 32px;">
        <a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
            <img
                src="{{ $baseUrl }}/NEO_logo_horizontal_light.png"
                width="300"
                alt="{{ config('app.name', 'National Olympiad Hunt') }}"
                style="display: block; width: 300px; max-width: 100%; height: auto; border: 0; outline: none; text-decoration: none;"
            >
        </a>
    </td>
</tr>
