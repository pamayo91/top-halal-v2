<?php

namespace App\Services;

use App\Models\RedirectRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RedirectResolver
{
    private const CACHE_KEY = 'redirect-rules-v1';

    public function resolve(Request $request): ?array
    {
        if (app()->environment('testing') && ! Schema::hasTable('redirect_rules')) return null;
        $path = '/'.ltrim(rawurldecode($request->path()), '/');
        $path = $path === '/.' ? '/' : $path;
        // Apache RewriteCond sees the raw query string; Laravel's normalized accessor may reorder keys.
        $query = (string) ($request->server('QUERY_STRING') ?? '');
        $columns = ['id', 'source_path', 'match_type', 'query_pattern', 'destination', 'status_code', 'preserve_query'];
        $loadRules = fn () => [
            'exact' => RedirectRule::query()->where('is_active', true)->where('match_type', 'exact')->orderBy('priority')->orderBy('id')->get($columns)->map->getAttributes()->all(),
            'regex' => RedirectRule::query()->where('is_active', true)->where('match_type', 'regex')->orderBy('priority')->orderBy('id')->get($columns)->map->getAttributes()->all(),
        ];
        $rules = app()->environment('testing') ? $loadRules() : Cache::rememberForever(self::CACHE_KEY, $loadRules);

        foreach (array_merge($rules['exact'], $rules['regex']) as $rule) {
            $matches = [];
            $matched = $rule['match_type'] === 'exact'
                ? $path === $rule['source_path']
                : @preg_match($this->pattern($rule['source_path']), ltrim($path, '/'), $matches) === 1;
            if (! $matched || ($rule['query_pattern'] && @preg_match($this->pattern($rule['query_pattern']), $query, $queryMatches) !== 1)) continue;

            $destination = $rule['destination'];
            if ($rule['match_type'] === 'regex') {
                $destination = preg_replace($this->pattern($rule['source_path']), $destination, ltrim($path, '/'), 1) ?? $destination;
            }
            foreach ($queryMatches ?? [] as $key => $value) if (is_int($key)) $destination = str_replace('%'.$key, $value, $destination);
            if ($rule['preserve_query'] && $query !== '') $destination .= (str_contains($destination, '?') ? '&' : '?').$query;
            if ($this->normalisePath($destination) === $path && ! $query) continue;

            RedirectRule::whereKey($rule['id'])->increment('hit_count', 1, ['last_hit_at' => now()]);
            return ['destination' => $destination, 'status' => $rule['status_code']];
        }
        return null;
    }

    public function clearCache(): void { Cache::forget(self::CACHE_KEY); }
    private function pattern(string $value): string { return '~'.$value.'~u'; }
    private function normalisePath(string $destination): string { return '/'.ltrim(parse_url($destination, PHP_URL_PATH) ?: '/', '/'); }
}
