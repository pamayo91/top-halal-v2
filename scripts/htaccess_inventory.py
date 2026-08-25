#!/usr/bin/env python3
"""Inventory redirect-only Apache rules without modifying them.

Usage:
  python3 scripts/htaccess_inventory.py legacy/redirects.htaccess --out docs/redirect-inventory
"""
from __future__ import annotations
import argparse, csv, json, re, unicodedata
from pathlib import Path
from urllib.parse import urlsplit

STATIC_RE = re.compile(r'^\s*Redirect(?:Permanent)?\s+(\S+)\s+(\S+)\s*$', re.I)
COND_RE = re.compile(r'^\s*RewriteCond\s+(.+?)\s*$', re.I)
RULE_RE = re.compile(r'^\s*RewriteRule\s+(\S+)\s+(\S+)(?:\s+\[(.*?)\])?\s*$', re.I)

def path_of_destination(dst: str) -> str:
    if dst.startswith(('http://','https://')):
        p = urlsplit(dst.rstrip('?'))
        return p.path or '/'
    return dst.rstrip('?') or '/'

def is_home(dst: str) -> bool:
    return path_of_destination(dst) == '/'

def has_unicode(s: str) -> bool:
    return any(ord(c) > 127 for c in s)

def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument('file', type=Path)
    ap.add_argument('--out', type=Path, default=Path('redirect-inventory'))
    args = ap.parse_args()
    text = args.file.read_text(encoding='utf-8-sig', errors='replace')
    lines = text.splitlines()
    records = []
    pending_conds = []
    for no, raw in enumerate(lines, 1):
        line = raw.strip()
        if not line or line.startswith('#'):
            continue
        m = COND_RE.match(raw)
        if m:
            pending_conds.append({'line': no, 'expr': m.group(1)})
            continue
        m = STATIC_RE.match(raw)
        if m:
            src, dst = m.groups()
            records.append({'line': no, 'kind':'exact', 'source':src, 'destination':dst, 'conditions':[], 'flags':'', 'raw':raw})
            pending_conds = []
            continue
        m = RULE_RE.match(raw)
        if m:
            src, dst, flags = m.groups()
            records.append({'line': no, 'kind':'rewrite', 'source':src, 'destination':dst, 'conditions':pending_conds, 'flags':flags or '', 'raw':raw})
            pending_conds = []
            continue
        pending_conds = []

    for r in records:
        cond_text = ' '.join(c['expr'] for c in r['conditions'])
        r['home_destination'] = is_home(r['destination'])
        r['drops_query'] = r['destination'].endswith('?')
        r['unicode_source'] = has_unicode(r['source'])
        r['infrastructure'] = bool(re.search(r'%\{(?:HTTP_HOST|HTTPS|SERVER_NAME)\}', cond_text, re.I))
        r['query_condition'] = 'QUERY_STRING' in cond_text.upper()
        r['destination_path'] = path_of_destination(r['destination'])

    exact = [r for r in records if r['kind']=='exact']
    rewrites = [r for r in records if r['kind']=='rewrite']
    normalized = {}
    dupes=[]
    for r in records:
        normalized_source = unicodedata.normalize('NFC', r['source']).lower()
        # A RewriteRule pattern is not a duplicate when its preceding conditions differ.
        # Exact Redirect/RedirectPermanent rules have no condition block.
        condition_signature = tuple(
            unicodedata.normalize('NFC', c['expr']).strip().lower()
            for c in r['conditions']
        ) if r['kind'] == 'rewrite' else ()
        key=(r['kind'], normalized_source, condition_signature)
        if key in normalized:
            dupes.append({'first_line':normalized[key]['line'],'line':r['line'],'source':r['source']})
        else:
            normalized[key]=r

    summary = {
        'source_file': str(args.file),
        'line_count': len(lines),
        'redirect_records': len(records),
        'static_exact': len(exact),
        'rewrite_rules': len(rewrites),
        'with_conditions': sum(bool(r['conditions']) for r in rewrites),
        'infrastructure_rules': sum(r['infrastructure'] for r in records),
        'query_condition_rules': sum(r['query_condition'] for r in records),
        'home_destinations': sum(r['home_destination'] for r in records),
        'drops_query': sum(r['drops_query'] for r in records),
        'unicode_sources': sum(r['unicode_source'] for r in records),
        'normalized_duplicate_sources': len(dupes),
    }

    outbase=args.out
    outbase.parent.mkdir(parents=True, exist_ok=True)
    outbase.with_suffix('.json').write_text(json.dumps({'summary':summary,'duplicates':dupes,'records':records}, ensure_ascii=False, indent=2), encoding='utf-8')
    with outbase.with_suffix('.csv').open('w', newline='', encoding='utf-8-sig') as f:
        w=csv.writer(f); w.writerow(['line','kind','source','destination','flags','conditions','infrastructure','query_condition','home_destination','drops_query','unicode_source'])
        for r in records:
            w.writerow([r['line'],r['kind'],r['source'],r['destination'],r['flags'],' | '.join(c['expr'] for c in r['conditions']),r['infrastructure'],r['query_condition'],r['home_destination'],r['drops_query'],r['unicode_source']])
    md=['# Legacy redirect inventory','',f"Source: `{args.file}`",'','## Summary','']
    for k,v in summary.items():
        if k!='source_file': md.append(f'- **{k.replace("_"," ")}**: {v}')
    if dupes:
        md += ['', '## Potential normalized duplicate sources','']
        for d in dupes: md.append(f"- `{d['source']}` — lines {d['first_line']} and {d['line']}")
    md += ['', '## Notes','', '- This is an inventory, not a semantic proof that each rewrite behaves exactly as intended.', '- Conditional/regex rules must be validated with URL test cases before migration.', '- Host/protocol rules should normally remain in Apache rather than the Laravel redirect manager.', '']
    outbase.with_suffix('.md').write_text('\n'.join(md), encoding='utf-8')
    print(json.dumps(summary, ensure_ascii=False, indent=2))
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
