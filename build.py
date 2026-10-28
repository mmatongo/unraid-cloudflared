#!/usr/bin/env python3
"""Generates plugin/cloudflared.plg from plugin/cloudflared.plg.template.

The plugin is self-contained: every file under src/ is embedded in the .plg, so there is no
package to build and no package checksum to keep in sync. The cloudflared binary is not pinned
here: scripts/cf-update.sh downloads and verifies it on the server.
"""
import base64
import json
import logging
import sys
from pathlib import Path

REQUIRED_CONFIG_KEYS = {'version', 'author', 'githubRepository'}


class CloudflaredBuilder:
    def __init__(self):
        self.root_dir = Path(__file__).parent.absolute()
        logging.basicConfig(format='%(asctime)s - %(levelname)s - %(message)s',
                            level=logging.INFO, datefmt='%Y-%m-%d %H:%M:%S')
        self.logger = logging.getLogger(__name__)
        config_path = self.root_dir / 'plugin' / 'cloudflared.json'
        try:
            self.config = json.loads(config_path.read_text())
        except FileNotFoundError:
            raise SystemExit(f"E: configuration file not found: {config_path}")
        except json.JSONDecodeError as e:
            raise SystemExit(f"E: invalid JSON in {config_path}: {e}")
        missing = REQUIRED_CONFIG_KEYS - set(self.config)
        if missing:
            raise SystemExit(f"E: missing required config keys: {missing}")

    def embedded_files(self) -> str:
        src = self.root_dir / 'src'
        if not src.is_dir():
            raise SystemExit(f"E: source directory not found: {src}")
        blocks = []
        for path in sorted(p for p in src.rglob('*') if p.is_file()):
            dest = '/' + path.relative_to(src).as_posix()
            data = path.read_bytes()
            mode = '0755' if path.suffix == '.sh' else '0644'
            if path.suffix == '.png':
                # binary file: embedded as base64 and decoded by a small script
                blocks.append(
                    '<FILE Run="/bin/bash">\n<INLINE>\n<![CDATA[\n'
                    f'mkdir -p "$(dirname {dest})"\nbase64 -d > {dest} <<\'B64\'\n{base64.b64encode(data).decode()}\nB64\n'
                    ']]>\n</INLINE>\n</FILE>\n')
            else:
                text = data.decode()
                if ']]>' in text:
                    raise SystemExit(f"E: {dest} contains ']]>' and cannot be embedded in a CDATA block")
                blocks.append(f'<FILE Name="{dest}" Mode="{mode}">\n<INLINE>\n<![CDATA[{text}]]>\n</INLINE>\n</FILE>\n')
        return '\n'.join(blocks)

    def generate_plugin_file(self) -> None:
        template = (self.root_dir / 'plugin' / 'cloudflared.plg.template').read_text()
        replacements = {
            '{{VERSION}}': self.config['version'],
            '{{AUTHOR}}': self.config['author'],
            '{{REPOSITORY}}': self.config['githubRepository'],
            '{{EMBEDDED_FILES}}': self.embedded_files(),
        }
        for key, value in replacements.items():
            template = template.replace(key, str(value))
        out = self.root_dir / 'plugin' / 'cloudflared.plg'
        out.write_text(template)
        self.logger.info(f"Plugin file generated: {out}")

    def build(self) -> None:
        self.generate_plugin_file()


if __name__ == '__main__':
    CloudflaredBuilder().build()
