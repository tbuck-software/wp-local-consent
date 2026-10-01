import importlib.util
import tempfile
import unittest
import zipfile
from pathlib import Path

spec = importlib.util.spec_from_file_location('package', Path(__file__).resolve().parents[1] / 'scripts/package.py')
package = importlib.util.module_from_spec(spec)
spec.loader.exec_module(package)


class PackageTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        (self.root / 'local-consent.php').write_text("<?php\n/* Version: 0.1.4 */\nconst VERSION = '0.1.4';\n")
        for name, content in [('readme.txt', 'Stable tag: 0.1.4\n'), ('LICENSE', 'License'), ('uninstall.php', '<?php')]:
            (self.root / name).write_text(content)
        for folder in ['assets', 'includes', 'tests', '.github', '.local']:
            (self.root / folder).mkdir()
        (self.root / 'assets/consent.js').write_text('/* runtime */')
        (self.root / 'includes/view.php').write_text('<?php')
        (self.root / 'assets/.DS_Store').write_bytes(b'local metadata')
        (self.root / '.local/admin-password.txt').write_text('local secret')
        (self.root / 'tests/test.php').write_text('test')

    def test_installable_package_excludes_development_and_is_reproducible(self):
        path = package.build(self.root, 'v0.1.4')
        first = path.read_bytes()
        with zipfile.ZipFile(path) as archive:
            self.assertEqual(set(archive.namelist()), {
                'local-consent/local-consent.php', 'local-consent/uninstall.php',
                'local-consent/readme.txt', 'local-consent/LICENSE',
                'local-consent/assets/consent.js', 'local-consent/includes/view.php',
            })
        self.assertEqual(first, package.build(self.root, 'v0.1.4').read_bytes())

    def test_wrong_release_tag_does_not_build(self):
        with self.assertRaises(ValueError):
            package.build(self.root, 'v0.1.5')
        self.assertFalse((self.root / 'dist').exists())

    def test_inconsistent_version_does_not_build(self):
        (self.root / 'readme.txt').write_text('Stable tag: 0.1.3\n')
        with self.assertRaises(ValueError):
            package.build(self.root)
        self.assertFalse((self.root / 'dist').exists())
