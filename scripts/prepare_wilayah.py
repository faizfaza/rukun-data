"""Parse only quoted kode/nama tuples; never execute the source SQL."""
import argparse, gzip, hashlib, json, re
from pathlib import Path
p = argparse.ArgumentParser()
p.add_argument('sql'); p.add_argument('output'); args = p.parse_args()
raw = Path(args.sql).read_bytes()
rows = []
levels = {2: 'provinsi', 5: 'kabupaten_kota', 8: 'kecamatan', 13: 'desa'}
for code, name in re.findall(r"\('([0-9.]+)'\s*,\s*'((?:\\.|''|[^'])*)'\)", raw.decode('utf-8-sig')):
    if len(code) not in levels:
        raise ValueError('Unsupported code: ' + code)
    rows.append({'code': code, 'name': name.replace("\\'", "'").replace("''", "'"), 'level': levels[len(code)], 'parent_code': code.rsplit('.', 1)[0] if '.' in code else None})
assert rows and len({r['code'] for r in rows}) == len(rows)
data = {'source_url': 'https://github.com/cahyadsn/wilayah/blob/master/db/wilayah.sql', 'version': 'snapshot-2026-10-31', 'license': 'MIT; Copyright (c) 2025 cahya dsn', 'source_sha256': hashlib.sha256(raw).hexdigest(), 'rows': rows}
with gzip.GzipFile(args.output, 'wb', mtime=0) as f:
    f.write(json.dumps(data, ensure_ascii=False, separators=(',', ':')).encode())
print(f'{len(rows)} wilayah; source SHA256 {data["source_sha256"]}')
