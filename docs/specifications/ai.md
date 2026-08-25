# AI / Automated News

## Provider abstraction
Business logic calls an internal AI provider contract. Provider adapters can be selected/configured without rewriting editorial workflow. Planned examples: OpenAI, Anthropic, Gemini, Mistral.

## Workflow
Sources/RSS/APIs -> source items -> deduplication -> relevance/fact extraction -> AI drafting -> validation -> draft or publication.
Do not ask a model to invent current news without source material.

## Provenance
Always store internally:
- provider/model;
- generation date;
- prompt/template version;
- source URLs/metadata;
- usage/cost metadata when available;
- validation result.

## Disclosure
Global setting: always / never / per-article.
Per article: inherit / show / hide.
Internal provenance is never removed just because public disclosure is hidden.

## Safety/quality
Start in draft mode. Automatic publication is a later configurable policy after reliable validation.
