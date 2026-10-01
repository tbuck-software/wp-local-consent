#!/usr/bin/env python3
"""Build the installable WordPress ZIP without development files."""
import argparse
import re
import zipfile
from pathlib import Path


def build(root, tag=None):
    header = (root / 'local-consent.php').read_text()
    version = re.search(r'\* Version:\s*(\S+)', header).group(1)
    runtime = re.search(r"const VERSION = '([^']+)';", header).group(1)
    stable = re.search(r'^Stable tag:\s*(\S+)', (root / 'readme.txt').read_text(), re.M).group(1)
    if version != runtime or version != stable:
        raise ValueError('Plugin header, runtime version and stable tag must match.')
    if tag is not None and tag != f'v{version}':
        raise ValueError(f'Release tag must be v{version}, got {tag}.')
    destination = root / 'dist' / f'local-consent-{version}.zip'
    destination.parent.mkdir(exist_ok=True)
    files = [root / name for name in ['local-consent.php', 'uninstall.php', 'LICENSE', 'readme.txt']]
    for folder in ['assets', 'includes']:
        files.extend(file for file in (root / folder).rglob('*') if file.is_file()
                     and not any(part.startswith('.') for part in file.relative_to(root).parts))
    with zipfile.ZipFile(destination, 'w', zipfile.ZIP_DEFLATED) as archive:
        for file in sorted(files):
            if file.is_symlink():
                raise ValueError(f'Package source must not be a symlink: {file}')
            entry = zipfile.ZipInfo(str(Path('local-consent') / file.relative_to(root)), date_time=(1980, 1, 1, 0, 0, 0))
            entry.compress_type = zipfile.ZIP_DEFLATED
            entry.external_attr = 0o644 << 16
            archive.writestr(entry, file.read_bytes())
    return destination


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--tag', help='Require a matching release tag, e.g. v0.1.4')
    args = parser.parse_args()
    try:
        print(build(Path(__file__).resolve().parents[1], args.tag))
    except ValueError as error:
        parser.exit(1, f'{error}\n')
