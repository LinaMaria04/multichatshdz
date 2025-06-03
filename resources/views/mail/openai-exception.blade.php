<x-mail::message>
# OpenAI Exception

Se ha producido la siguiente Exceptión:

<x-mail::panel>
{{ $message }}
</x-mail::panel>


Thanks,<br>
{{ config('app.name') }}
</x-mail::message>