<input type="hidden" name="form_token" value="{{ app(\App\Services\ContributionSpamProtection::class)->issueFormToken() }}">
