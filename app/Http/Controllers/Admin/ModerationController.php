<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\{Comment,RestaurantClaim,RestaurantReview}; use App\Services\AdminAudit; use Illuminate\Http\Request;
class ModerationController extends Controller {
 public function reviews(Request $r){$q=RestaurantReview::with('restaurant');if($r->filled('status'))$q->where('status',$r->status);if($r->filled('q'))$q->where(fn($x)=>$x->where('author_name','like','%'.$r->q.'%')->orWhere('content','like','%'.$r->q.'%'));return view('admin.moderation.reviews',['items'=>$q->latest()->paginate(30)->withQueryString()]);}
 public function comments(Request $r){$q=Comment::with(['article','page','parent']);if($r->filled('status'))$q->where('status',$r->status);if($r->filled('q'))$q->where(fn($x)=>$x->where('author_name','like','%'.$r->q.'%')->orWhere('content','like','%'.$r->q.'%'));return view('admin.moderation.comments',['items'=>$q->latest()->paginate(30)->withQueryString()]);}
 public function reviewStatus(Request $r,RestaurantReview $review,AdminAudit $audit){$d=$r->validate(['status'=>'required|in:approved,rejected,spam']);$review->update(['status'=>$d['status'],'approved_at'=>$d['status']==='approved'?now():null]);$audit->record('review.'.$d['status'],$review);return back()->with('status','Avis modéré.');}
 public function commentStatus(Request $r,Comment $comment,AdminAudit $audit){$d=$r->validate(['status'=>'required|in:approved,rejected,spam']);$comment->update(['status'=>$d['status'],'approved_at'=>$d['status']==='approved'?now():null]);$audit->record('comment.'.$d['status'],$comment);return back()->with('status','Commentaire modéré.');}
}
