<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>{{ $content->title }}</title></head><body><main><h1>{{ $content->title }}</h1>{!! $content->content_html !!}
<section aria-labelledby="comments-title"><h2 id="comments-title">Commentaires</h2>
@if(session('comment_submitted'))<p role="status">Votre commentaire est en attente de modération.</p>@endif
@foreach($comments as $comment)<article data-comment-id="{{ $comment->id }}"><h3>{{ $comment->author_name }}</h3><p>{!! nl2br(e($comment->content)) !!}</p></article>@endforeach
<form method="post" action="{{ url('/_preview/'.$type.'/'.$legacyId.'/comments') }}"><fieldset><legend>Ajouter un commentaire</legend>@csrf
<label>Nom <input name="name" required maxlength="100" value="{{ old('name') }}"></label>
<label>E-mail <input name="email" type="email" required value="{{ old('email') }}"></label>
<label class="visually-hidden" aria-hidden="true">Site web <input name="website" tabindex="-1" autocomplete="off"></label>
<label>Commentaire <textarea name="content" required maxlength="2000">{{ old('content') }}</textarea></label>
@error('content')<p role="alert">{{ $message }}</p>@enderror
<button type="submit">Envoyer</button></fieldset></form></section></main></body></html>
