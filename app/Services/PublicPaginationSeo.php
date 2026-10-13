<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\Paginator;

class PublicPaginationSeo
{
    /**
     * Gives an indexable collection its own canonical URL for each real page.
     *
     * The base URL is deliberately supplied by the calling landing page: query
     * parameters used for discovery, filters, or tracking never become part of
     * an indexable collection canonical.
     */
    public function canonical(string $baseUrl, Paginator $paginator): string
    {
        return $paginator->currentPage() > 1
            ? $baseUrl.'?page='.$paginator->currentPage()
            : $baseUrl;
    }
}
