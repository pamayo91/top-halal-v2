{{ $email['body'] }}

@if(filled($email['cta_label']) && filled($email['cta_url']))
{{ $email['cta_label'] }} : {{ $email['cta_url'] }}
@endif

{{ trim(($global['year'] ? '© '.$global['year'].' ' : '').$global['footer_text']) }}
@if(filled($global['footer_additional_text']))
{{ $global['footer_additional_text'] }}
@endif
