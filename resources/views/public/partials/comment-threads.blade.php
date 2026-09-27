@foreach($commentThreads as $comment)
    @include('public.partials.comment', ['comment' => $comment, 'content' => $content, 'isReply' => false, 'replyTo' => null])
@endforeach
