<?php

namespace App\Services;

use App\Models\{Article, Page, RedirectRule};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditorialTrash
{
    public const CODES = [301, 302, 404, 410];

    public function trash(Article|Page $content, int $status, ?string $destination = null): void
    {
        $this->validateChoice($content, $status, $destination);
        if ($this->hasPublicUrl($content) && $status <= 302) $this->assertDestinationIsSafe($content, (string) $destination);

        DB::transaction(function () use ($content, $status, $destination): void {
            $content->delete();
            if ($this->hasPublicUrl($content)) {
                RedirectRule::create([
                    'source_path' => '/'.ltrim($content->slug, '/'),
                    'match_type' => 'exact',
                    'query_pattern' => null,
                    'destination' => $status <= 302 ? $destination : null,
                    'status_code' => $status,
                    'preserve_query' => false,
                    'priority' => 10,
                    'is_active' => true,
                    'origin' => 'editorial_deletion',
                    'source_rule' => 'Automatic rule created when editorial content was moved to the trash.',
                    'related_type' => $content instanceof Article ? 'article' : 'page',
                    'related_id' => $content->getKey(),
                ]);
            }
            app(AdminAudit::class)->record($this->type($content).'.trashed', $content, ['status_code' => $status, 'destination' => $destination]);
        });
    }

    public function restore(Article|Page $content): void
    {
        DB::transaction(function () use ($content): void {
            $collision = ($content instanceof Article ? Article::class : Page::class)::query()->where('slug', $content->slug)->whereKeyNot($content->getKey())->withTrashed()->exists();
            if ($collision) throw ValidationException::withMessages(['record' => 'Impossible de restaurer ce contenu : son slug est déjà utilisé par un autre contenu.']);

            $source = '/'.ltrim($content->slug, '/');
            $rule = RedirectRule::query()->where('origin', 'editorial_deletion')->where('related_type', $this->type($content))->where('related_id', $content->getKey())->where('is_active', true)->latest('id')->first();
            $manualConflict = RedirectRule::query()->where('source_path', $source)->where('match_type', 'exact')->whereNull('query_pattern')->where('is_active', true)->when($rule, fn ($query) => $query->whereKeyNot($rule->getKey()))->exists();
            if ($manualConflict) throw ValidationException::withMessages(['record' => 'Impossible de restaurer ce contenu : une autre règle active possède déjà cette URL. La règle manuelle n’a pas été modifiée.']);
            if ($rule) $rule->update(['is_active' => false]);
            $content->restore();
            app(AdminAudit::class)->record($this->type($content).'.restored', $content, ['redirect_rule_id' => $rule?->id, 'redirect_rule_deactivated' => $rule !== null]);
        });
    }

    public function forceDelete(Article|Page $content): void
    {
        $snapshot = ['slug' => $content->slug, 'deleted_at' => $content->deleted_at];
        $content->forceDelete();
        app(AdminAudit::class)->record($this->type($content).'.force_deleted', $content, $snapshot);
    }

    public function emptyTrash(string $model): void
    {
        ($model === Article::class ? Article::class : Page::class)::onlyTrashed()->cursor()->each(fn (Article|Page $content) => $this->forceDelete($content));
    }

    private function validateChoice(Article|Page $content, int $status, ?string $destination): void
    {
        if (! in_array($status, self::CODES, true)) throw ValidationException::withMessages(['status_code' => 'Comportement d’URL invalide.']);
        if ($status <= 302 && blank($destination)) throw ValidationException::withMessages(['destination' => 'Une destination est obligatoire pour une réponse 301 ou 302.']);
        if ($status >= 404 && filled($destination)) throw ValidationException::withMessages(['destination' => 'Une réponse 404 ou 410 ne peut pas avoir de destination.']);
        if ($status <= 302 && $this->normalise($destination) === '/'.ltrim($content->slug, '/')) throw ValidationException::withMessages(['destination' => 'La destination ne peut pas être l’ancienne URL.']);
        if ($this->hasPublicUrl($content) && RedirectRule::query()->where('source_path', '/'.ltrim($content->slug, '/'))->where('match_type', 'exact')->whereNull('query_pattern')->where('is_active', true)->exists()) throw ValidationException::withMessages(['record' => 'Cette URL possède déjà une règle active. Modifiez-la ou désactivez-la avant la suppression.']);
    }

    private function assertDestinationIsSafe(Article|Page $content, string $destination): void
    {
        $source = '/'.ltrim($content->slug, '/'); $target = $this->normalise($destination); $seen = [];
        while (! isset($seen[$target])) {
            if ($target === $source) throw ValidationException::withMessages(['destination' => 'Cette destination créerait une boucle de redirection.']);
            $seen[$target] = true;
            $next = RedirectRule::query()->where('source_path', $target)->where('match_type', 'exact')->whereNull('query_pattern')->where('is_active', true)->whereIn('status_code', [301,302,307,308])->value('destination');
            if ($next === null) return;
            $target = $this->normalise($next);
        }
        throw ValidationException::withMessages(['destination' => 'Cette destination participe déjà à une chaîne ou une boucle de redirection.']);
    }

    private function hasPublicUrl(Article|Page $content): bool { return $content->status === 'published' && filled($content->slug); }
    private function type(Model $content): string { return $content instanceof Article ? 'article' : 'page'; }
    private function normalise(?string $value): string { return '/'.ltrim((string) parse_url((string) $value, PHP_URL_PATH), '/'); }
}
