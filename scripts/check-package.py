"""Validate installable distribution and release metadata, without extracting it."""
from pathlib import Path
import json
import re
from zipfile import ZipFile

root = Path(__file__).resolve().parents[1]
header = (root / 'plugin-ab-testing.php').read_text()
version = re.search(r' \* Version: ([\d.]+)', header).group(1)
assert f"define('BAT_VERSION', '{version}')" in header
assert json.loads((root / 'package.json').read_text())['version'] == version
assert json.loads((root / 'package-lock.json').read_text())['version'] == version
assert f'Stable tag: {version}' in (root / 'readme.txt').read_text()
assert f'— {version}' in (root / 'README.md').read_text()
assert f'## {version}' in (root / 'CHANGELOG.md').read_text()
with ZipFile(root / 'dist/builder-ab-testing.zip') as archive:
    assert archive.testzip() is None
    names = archive.namelist()
    expected = {'plugin-ab-testing.php', 'README.md', 'LICENSE', 'readme.txt', 'CHANGELOG.md'}
    for path in list((root / 'includes').rglob('*')) + list((root / 'assets').rglob('*')):
        if path.is_file():
            expected.add(path.relative_to(root).as_posix())
    assert set(names) == {'builder-ab-testing/' + name for name in expected}, 'Unexpected or missing ZIP files'
    for name in expected:
        assert archive.read('builder-ab-testing/' + name) == (root / name).read_bytes(), f'Stale file: {name}'
print(f'PASS: release {version}, complete license, current files and no development artifacts')
