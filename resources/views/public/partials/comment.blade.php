<article class="comment {{ $isReply ? 'comment-reply' : '' }}" id="comment-{{ $comment->id }}" data-comment>
    <h3>{{ $comment->author_name }}</h3>
    @if($isReply && $replyTo)<p class="comment-in-reply-to">En réponse à {{ $replyTo->author_name }}</p>@endif
    <p>{{ $comment->content }}</p>
    <p class="muted"><time datetime="{{ $comment->created_at->toDateString() }}">Publié le {{ $comment->created_at->translatedFormat('j F Y') }}</time></p>
    <details class="comment-reply-form" data-reply-details>
        <summary aria-label="Répondre à {{ $comment->author_name }}">Répondre</summary>
        <form class="stack-form" method="post" action="{{ route('editorial.comments.store', $content->slug) }}">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
            <p class="form-help">Répondre à {{ $comment->author_name }}</p>
            <label>Nom <input name="name" required value="{{ old('name', auth()->user()?->name) }}"></label>
            @guest<label>E-mail <input name="email" type="email" required value="{{ old('email') }}"></label>@endguest
            <label>Réponse <textarea name="content" required maxlength="2000">{{ old('parent_id') == $comment->id ? old('content') : '' }}</textarea></label>
            <input class="hp" name="website" tabindex="-1" autocomplete="off">
            <div class="comment-reply-actions"><button class="button button-small">Envoyer pour modération</button><button class="link-button" type="button" data-reply-cancel>Annuler</button></div>
        </form>
    </details>
</article>
@foreach($comment->children as $child)
    @include('public.partials.comment', ['comment' => $child, 'content' => $content, 'isReply' => true, 'replyTo' => $comment])
@endforeach
