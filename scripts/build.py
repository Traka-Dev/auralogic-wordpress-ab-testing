"""Build an installable plugin ZIP, excluding development artifacts."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
root = Path(__file__).resolve().parents[1]
output = root / 'dist' / 'builder-ab-testing.zip'
output.parent.mkdir(exist_ok=True)
files = [root / 'plugin-ab-testing.php', root / 'README.md', root / 'LICENSE', root / 'readme.txt', root / 'CHANGELOG.md']
for directory in ('includes', 'assets'):
    files.extend(p for p in (root / directory).rglob('*') if p.is_file())
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for path in sorted(files):
        archive.write(path, Path('builder-ab-testing') / path.relative_to(root))
print(output)
