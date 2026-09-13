<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EditorialContentReport extends Model { protected $guarded = []; protected function casts(): array { return ['is_authenticated' => 'boolean', 'status_changed_at' => 'datetime']; } public function user(): BelongsTo { return $this->belongsTo(User::class); } }
