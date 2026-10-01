#!/usr/bin/env python3
"""Run a disposable local WordPress with SQLite; never load production config."""
import argparse
import os
import secrets
import subprocess
import sys
import urllib.request
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LOCAL = ROOT / '.local'
WORDPRESS = LOCAL / 'wordpress'
MARKER = LOCAL / 'development-installation'


def wp(*args):
    phar = os.environ.get('WP_CLI_PHAR')
    command = ['php', '-d', 'memory_limit=512M', '-d', 'error_reporting=22527', phar] if phar else ['wp']
    subprocess.run(command + [f'--path={WORDPRESS}', *args], cwd=ROOT, check=True)


def seed():
    if not MARKER.is_file():
        raise RuntimeError('Run make setup first.')
    wp('eval-file', str(ROOT / 'tests/seed.php'))


def setup(port):
    if WORDPRESS.exists() and any(WORDPRESS.iterdir()):
        if MARKER.is_file():
            print('Local WordPress already exists. No files changed.')
            return
        raise RuntimeError('Refusing to overwrite a non-empty WordPress without the local marker.')
    WORDPRESS.mkdir(parents=True, exist_ok=True)
    version = os.environ.get('WORDPRESS_VERSION', '6.8.1')
    wp('core', 'download', f'--version={version}', '--skip-content')
    archive = LOCAL / 'sqlite.zip'
    urllib.request.urlretrieve('https://downloads.wordpress.org/plugin/sqlite-database-integration.3.0.2.zip', archive)
    with zipfile.ZipFile(archive) as package:
        package.extractall(WORDPRESS / 'wp-content/plugins')
    archive.unlink()
    sqlite = WORDPRESS / 'wp-content/plugins/sqlite-database-integration'
    (WORDPRESS / 'wp-content/db.php').write_text((sqlite / 'db.copy').read_text())
    wp('config', 'create', '--dbname=local', '--dbuser=local', '--dbpass=local', '--skip-check')
    wp('config', 'set', 'WP_ENVIRONMENT_TYPE', 'local')
    wp('config', 'set', 'DISABLE_WP_CRON', 'true', '--raw')
    password = secrets.token_urlsafe(24)
    wp('core', 'install', f'--url=http://127.0.0.1:{port}', '--title=Local Consent', '--admin_user=local-admin', f'--admin_password={password}', '--admin_email=local@example.test', '--skip-email')
    wp('theme', 'install', 'twentytwentyfive', '--version=1.2', '--activate')
    (WORDPRESS / 'wp-content/plugins/local-consent').symlink_to(os.path.relpath(ROOT, WORDPRESS / 'wp-content/plugins'), target_is_directory=True)
    wp('plugin', 'activate', 'local-consent')
    password_file = LOCAL / 'admin-password.txt'
    password_file.write_text(password + '\n')
    password_file.chmod(0o600)
    MARKER.write_text('Disposable local WordPress. No production data.\n')
    seed()
    print(f'Local WordPress ready at http://127.0.0.1:{port}.')
    print('Login: local-admin. Password: .local/admin-password.txt')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('command', choices=['setup', 'serve', 'seed'])
    parser.add_argument('--port', type=int, default=8091)
    args = parser.parse_args()
    if args.command == 'setup':
        setup(args.port)
    elif args.command == 'seed':
        seed()
    else:
        if not MARKER.is_file():
            raise RuntimeError('Run make setup first.')
        subprocess.run(['php', '-d', 'error_reporting=22527', '-d', 'realpath_cache_ttl=0', '-d', 'opcache.enable_cli=0', '-S', f'127.0.0.1:{args.port}', '-t', str(WORDPRESS), str(ROOT / 'scripts/dev-router.php')], check=True)


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, subprocess.CalledProcessError, OSError) as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
