#!/usr/bin/env python3

import os
import json
import hashlib
import shutil
import subprocess
import tarfile
import tempfile
import logging
from pathlib import Path
from datetime import datetime
from typing import Dict, Any

class CloudflaredBuilder:
    REQUIRED_CONFIG_KEYS = {
        'version', 'cloudflaredVersion', 'cloudflaredSHA256',
        'packageVersion', 'author', 'githubRepository'
    }

    def __init__(self):
        self._setup_paths()
        self._setup_logging()
        self._load_config()

    def _setup_paths(self) -> None:
        self.root_dir = Path(__file__).parent.absolute()
        self.build_dir = self.root_dir / 'build'
        self.package_dir = self.build_dir / 'package'
        self.plugin_dir = self.build_dir / 'plugin'

    def _setup_logging(self) -> None:
        logging.basicConfig(
            format='%(asctime)s - %(levelname)s - %(message)s',
            level=logging.INFO,
            datefmt='%Y-%m-%d %H:%M:%S'
        )
        self.logger = logging.getLogger(__name__)

    def _load_config(self) -> None:
        config_path = self.root_dir / 'plugin' / 'cloudflared.json'

        try:
            with open(config_path, 'r') as f:
                self.config = json.load(f)

            missing_keys = self.REQUIRED_CONFIG_KEYS - set(self.config.keys())
            if missing_keys:
                raise ValueError(f"missing required config keys: {missing_keys}")

        except FileNotFoundError:
            raise SystemExit(f"E: configuration file not found: {config_path}")
        except json.JSONDecodeError as e:
            raise SystemExit(f"E: invalid JSON in {config_path}: {str(e)}")
        except ValueError as e:
            raise SystemExit(str(e))

    def _run_command(self, cmd: list, desc: str) -> subprocess.CompletedProcess:
        self.logger.info(f"Running {desc}...")
        try:
            result = subprocess.run(cmd, check=True, capture_output=True, text=True)
            self.logger.debug(f"Command output: {result.stdout}")
            return result
        except subprocess.CalledProcessError as e:
            self.logger.error(f"Command failed with exit code {e.returncode}")
            self.logger.error(f"E: {e.stderr}")
            raise SystemExit(f"Failed to {desc}: {e.stderr}")

    def setup_directories(self) -> None:
        self.logger.info("Setting up build directories...")
        for directory in [self.build_dir, self.package_dir, self.plugin_dir]:
            directory.mkdir(parents=True, exist_ok=True)

    def download_cloudflared(self) -> None:
        version = self.config['cloudflaredVersion']
        self.logger.info(f"Downloading Cloudflared {version}...")

        binary_url = (
            f"https://github.com/cloudflare/cloudflared/releases/download/"
            f"{version}/cloudflared-linux-amd64"
        )
        output_path = self.build_dir / 'cloudflared'

        self._run_command(
            ['curl', '-L', '-o', str(output_path), binary_url],
            "download binary"
        )

        self._verify_sha256(output_path, self.config['cloudflaredSHA256'])


        output_path.chmod(0o755)
        self.logger.info("Binary download and verification successful")

    def _verify_sha256(self, file_path: Path, expected_hash: str) -> None:
        with open(file_path, 'rb') as f:
            sha256 = hashlib.sha256(f.read()).hexdigest()

        if sha256 != expected_hash:
            raise ValueError(
                f"SHA256 verification failed\n"
                f"expected: {expected_hash}\n"
                f"got: {sha256}"
            )

    def create_txz_archive(self, source_dir: Path, output_file: Path) -> None:
        self.logger.info(f"Creating archive: {output_file}")
        temp_tar = tempfile.mktemp(suffix='.tar')

        try:
            with tarfile.open(temp_tar, 'w') as tar:
                for root, _, files in os.walk(source_dir):
                    for file in files:
                        full_path = Path(root) / file
                        arcname = full_path.relative_to(source_dir)
                        tar.add(full_path, arcname=f"./{arcname}")

            self._run_command(['xz', '-9', '-f', temp_tar], "compress archive")
            shutil.move(f"{temp_tar}.xz", output_file)
            self.logger.info("Archive created successfully")

        finally:
            if os.path.exists(temp_tar):
                os.remove(temp_tar)

    def build_utils_package(self) -> None:
        self.logger.info("Building utils package...")
        pkg_root = self.package_dir / 'root'
        plugin_path = pkg_root / 'usr' / 'local' / 'emhttp' / 'plugins' / 'cloudflared'

        plugin_path.mkdir(parents=True, exist_ok=True)

        src_plugin_path = self.root_dir / 'src' / 'usr' / 'local' / 'emhttp' / 'plugins' / 'cloudflared'
        if not src_plugin_path.exists():
            raise ValueError(f"Source plugin directory not found: {src_plugin_path}")

        directories = [
            'assets/css',
            'assets/js',
            'images',
            'include',
            'pages',
            'scripts'
        ]

        for dir_path in directories:
            (plugin_path / dir_path).mkdir(parents=True, exist_ok=True)

        def copy_files_recursive(src: Path, dst: Path) -> None:
            if src.is_file():
                shutil.copy2(src, dst)
                return

            dst.mkdir(parents=True, exist_ok=True)

            for item in src.iterdir():
                dst_item = dst / item.name
                if item.is_file():
                    shutil.copy2(item, dst_item)
                else:
                    copy_files_recursive(item, dst_item)

        copy_files_recursive(src_plugin_path, plugin_path)

        self._create_icon_symlink(plugin_path)
        self._set_permissions(plugin_path)
        self._create_and_hash_package(pkg_root)
        self._verify_package_contents(plugin_path)

    def _verify_package_contents(self, plugin_path: Path) -> None:
        required_files = [
            'Cloudflared.page',
            'assets/css/styles.php',
            'assets/js/app.php',
            'images/cloudflared.png',
            'include/Config.php',
            'include/Logger.php',
            'include/ServiceManager.php',
            'scripts/install.sh',
            'scripts/restart.sh',
            'include/ajax/fetch_logs.php',
            'include/ajax/log_clear.php',
            'include/ajax/service_handler.php'
        ]

        missing_files = []
        for required_file in required_files:
            file_path = plugin_path / required_file
            if not file_path.is_file():
                missing_files.append(required_file)

        if missing_files:
            raise ValueError(f"Missing files in package: {', '.join(missing_files)}")

        self.logger.info("Package contents verified successfully")

    def _create_icon_symlink(self, plugin_path: Path) -> None:
        icon_symlink = plugin_path / 'cloudflared.png'
        if icon_symlink.is_symlink() or icon_symlink.exists():
            icon_symlink.unlink()
        os.symlink('images/cloudflared.png', str(icon_symlink))

    def _set_permissions(self, plugin_path: Path) -> None:
        for file in plugin_path.rglob('*'):
            if file.is_file():
                file.chmod(0o755 if file.name.endswith('.sh') else 0o644)
            elif file.is_dir():
                file.chmod(0o755)

    def _create_and_hash_package(self, pkg_root: Path) -> None:
        package_file = self.build_dir / f"cloudflared-utils-{self.config['packageVersion']}-noarch-1.txz"
        self.create_txz_archive(pkg_root, package_file)

        with open(package_file, 'rb') as f:
            self.config['packageSHA256'] = hashlib.sha256(f.read()).hexdigest()

        with open(self.root_dir / 'plugin' / 'cloudflared.json', 'w') as f:
            json.dump(self.config, f, indent=4)

    def generate_plugin_file(self) -> None:
        self.logger.info("Generating plugin file...")
        template_path = self.root_dir / 'plugin' / 'cloudflared.plg.template'

        with open(template_path, 'r') as f:
            template = f.read()

        replacements = {
            '{{VERSION}}': self.config['version'],
            '{{CLOUDFLARED_VERSION}}': self.config['cloudflaredVersion'],
            '{{CLOUDFLARED_SHA256}}': self.config['cloudflaredSHA256'],
            '{{PACKAGE_VERSION}}': self.config['packageVersion'],
            '{{PACKAGE_SHA256}}': self.config['packageSHA256'],
            '{{AUTHOR}}': self.config['author'],
            '{{REPOSITORY}}': self.config['githubRepository']
        }

        content = template
        for key, value in replacements.items():
            content = content.replace(key, str(value))

        plugin_file = self.plugin_dir / 'cloudflared.plg'
        with open(plugin_file, 'w') as f:
            f.write(content)

        repo_plugin_dir = self.root_dir / 'plugin'
        repo_plugin_file = repo_plugin_dir / 'cloudflared.plg'

        repo_plugin_dir.mkdir(parents=True, exist_ok=True)

        shutil.copy2(plugin_file, repo_plugin_file)

        self.logger.info(f"Plugin file generated and copied to {repo_plugin_file}")

    def build(self) -> None:
        start_time = datetime.now()
        self.logger.info(f"Starting build process at {start_time}")

        try:
            self.setup_directories()
            self.download_cloudflared()
            self.build_utils_package()
            self.generate_plugin_file()

            end_time = datetime.now()
            build_duration = end_time - start_time
            self.logger.info(f"Build completed successfully at {end_time} (Duration: {build_duration})")

        except SystemExit as e:
            self.logger.error(f"Build failed: {str(e)}")
            raise
        except Exception as e:
            self.logger.error(f"Unexpected error: {str(e)}")
            raise SystemExit(f"Build failed due to unexpected error: {str(e)}")

if __name__ == '__main__':
    builder = CloudflaredBuilder()
    builder.build()
