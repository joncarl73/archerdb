@component('mail::message')
# New Contact Message

**From:** {{ $name }} ({{ $email }})

**Message:**

{{ $messageBody }}

@endcomponent
