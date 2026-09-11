{{ $global['display_name'] }}
@if(filled($global['footer_presentation']))
{{ $global['footer_presentation'] }}
@endif

{{ $email['body'] }}

@if(filled($email['cta_label']) && filled($email['cta_url']))
{{ $email['cta_label'] }} : {{ $email['cta_url'] }}
@endif

{{ $global['footer_text'] }}
