<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Page;
use App\Models\RedirectRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EditorialSlugRedirects
{
    public function assertCanSave(Article|Page $content): void
    {
        $slug = (string) $content->slug;
        $destination = $this->path($slug);

        $this->assertSlugIsAvailable($content, $slug);

        if (! $content->exists || ! $content->isDirty('slug')) {
            return;
        }

        $source = $this->path((string) $content->getOriginal('slug'));

        if ($source === $destination) {
            return;
        }

        $existing = $this->exactRulesFor($source);
        if ($existing->isNotEmpty() && ! $existing->every(fn (RedirectRule $rule): bool => $rule->is_active && (int) $rule->status_code === 301 && $rule->destination === $destination)) {
            throw ValidationException::withMessages(['slug' => 'Ce slug possède déjà une redirection incompatible. Modifiez ou supprimez d’abord cette règle dans Redirections.']);
        }

        if ($this->wouldCreateLoop($source, $destination)) {
            throw ValidationException::withMessages(['slug' => 'Ce changement créerait une boucle de redirection.']);
        }
    }

    public function createForChangedSlug(Article|Page $content): void
    {
        if (! $content->wasChanged('slug')) {
            return;
        }

        $source = $this->path((string) $content->getOriginal('slug'));
        $destination = $this->path((string) $content->slug);

        if ($source === $destination || $this->exactRulesFor($source)->isNotEmpty()) {
            return;
        }

        RedirectRule::create([
            'source_path' => $source,
            'match_type' => 'exact',
            'query_pattern' => null,
            'destination' => $destination,
            'status_code' => 301,
            'preserve_query' => false,
            'priority' => 100,
            'is_active' => true,
            'origin' => 'editorial_slug_change',
            'source_rule' => 'Automatic redirect after an editorial slug change.',
        ]);
    }

    private function assertSlugIsAvailable(Article|Page $content, string $slug): void
    {
        $duplicate = Article::query()
            ->where('slug', $slug)
            ->when($content instanceof Article && $content->exists, fn ($query) => $query->whereKeyNot($content->getKey()))
            ->exists()
            || Page::query()
                ->where('slug', $slug)
                ->when($content instanceof Page && $content->exists, fn ($query) => $query->whereKeyNot($content->getKey()))
                ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['slug' => 'Ce slug est déjà utilisé par un contenu éditorial.']);
        }

        if ($this->exactRulesFor($this->path($slug))->isNotEmpty()) {
            throw ValidationException::withMessages(['slug' => 'Ce slug est déjà une source de redirection. Choisissez un autre slug ou modifiez d’abord cette règle dans Redirections.']);
        }
    }

    private function exactRulesFor(string $source): Collection
    {
        return RedirectRule::query()
            ->where('source_path', $source)
            ->where('match_type', 'exact')
            ->whereNull('query_pattern')
            ->get();
    }

    private function wouldCreateLoop(string $source, string $destination): bool
    {
        $visited = [];
        $current = $destination;

        while (! isset($visited[$current])) {
            if ($current === $source) {
                return true;
            }

            $visited[$current] = true;
            $next = $this->nextDestinationFor($current);
            if ($next === null) {
                return false;
            }

            $current = $this->pathFromDestination($next);
        }

        return false;
    }

    private function nextDestinationFor(string $path): ?string
    {
        $rules = RedirectRule::query()
            ->where('is_active', true)
            ->whereIn('status_code', [301, 302, 307, 308])
            ->orderBy('priority')
            ->orderBy('id')
            ->get(['source_path', 'match_type', 'query_pattern', 'destination']);

        foreach (['exact', 'regex'] as $type) {
            foreach ($rules->where('match_type', $type) as $rule) {
                if ($rule->query_pattern !== null && @preg_match($this->pattern($rule->query_pattern), '') !== 1) {
                    continue;
                }
                if ($type === 'exact' && $rule->source_path === $path) {
                    return $rule->destination;
                }
                if ($type !== 'regex' || @preg_match($this->pattern($rule->source_path), ltrim($path, '/')) !== 1) {
                    continue;
                }

                return preg_replace($this->pattern($rule->source_path), $rule->destination, ltrim($path, '/'), 1) ?? $rule->destination;
            }
        }

        return null;
    }

    private function path(string $slug): string
    {
        return '/'.Str::of($slug)->trim('/');
    }

    private function pathFromDestination(string $destination): string
    {
        $path = parse_url($destination, PHP_URL_PATH);

        return '/'.ltrim($path === null || $path === '' ? $destination : $path, '/');
    }

    private function pattern(string $value): string
    {
        return '~'.$value.'~u';
    }
}
