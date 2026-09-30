<section class="editorial-faq" aria-label="Questions fréquentes">
    @foreach($questions as $question)
        <details>
            <summary><span>{{ $question['question'] }}</span><span class="editorial-faq-chevron" aria-hidden="true"></span></summary>
            <div class="editorial-faq-answer">{!! $question['answer'] !!}</div>
        </details>
    @endforeach
</section>
