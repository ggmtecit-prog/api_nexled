from __future__ import annotations

import pathlib
import re
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET


ROOT = pathlib.Path(__file__).resolve().parents[1]
IMAGE_ROOT = ROOT / "appdatasheets" / "img"
EDGE_CANDIDATES = [
    pathlib.Path(r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"),
    pathlib.Path(r"C:\Program Files\Microsoft\Edge\Application\msedge.exe"),
    pathlib.Path(r"C:\Program Files\Google\Chrome\Application\chrome.exe"),
    pathlib.Path(r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"),
]
TARGET_PATTERNS = (
    "temperaturas/*.svg",
    "*/desenhos/*.svg",
    "*/*/desenhos/*.svg",
    "*/diagramas/*.svg",
    "*/*/diagramas/*.svg",
    "*/diagramas/i/*.svg",
    "*/*/diagramas/i/*.svg",
)


def find_browser() -> pathlib.Path:
    for candidate in EDGE_CANDIDATES:
        if candidate.is_file():
            return candidate

    raise SystemExit("No Edge/Chrome executable found in standard paths.")


def parse_svg_size(svg_path: pathlib.Path) -> tuple[int, int]:
    root = ET.parse(svg_path).getroot()
    viewbox = root.get("viewBox")

    if viewbox:
        values = [float(token) for token in re.split(r"[ ,]+", viewbox.strip()) if token]
        if len(values) == 4 and values[2] > 0 and values[3] > 0:
            width, height = values[2], values[3]
            return scale_viewport(width, height)

    width = parse_numeric_dimension(root.get("width"))
    height = parse_numeric_dimension(root.get("height"))

    if width > 0 and height > 0:
        return scale_viewport(width, height)

    return 1600, 900


def parse_numeric_dimension(raw_value: str | None) -> float:
    if not raw_value:
        return 0.0

    match = re.search(r"(\d+(?:\.\d+)?)", raw_value)
    return float(match.group(1)) if match else 0.0


def scale_viewport(width: float, height: float) -> tuple[int, int]:
    max_width = 1800
    max_height = 1400
    scale = min(max_width / max(width, 1.0), max_height / max(height, 1.0))
    scale = max(scale, 1.0)
    return max(200, int(width * scale)), max(200, int(height * scale))


def file_url(path: pathlib.Path) -> str:
    return "file:///" + str(path).replace("\\", "/")


def render_svg(browser_path: pathlib.Path, svg_path: pathlib.Path) -> bool:
    png_path = svg_path.with_suffix(".png")

    if png_path.exists() and png_path.stat().st_mtime >= svg_path.stat().st_mtime:
        return False

    width, height = parse_svg_size(svg_path)
    tmp_output = pathlib.Path(tempfile.gettempdir()) / f"{svg_path.stem}-nexled-raster.png"

    if tmp_output.exists():
        tmp_output.unlink()

    command = [
        str(browser_path),
        "--headless=new",
        "--disable-gpu",
        "--hide-scrollbars",
        "--force-device-scale-factor=2",
        f"--window-size={width},{height}",
        f"--screenshot={tmp_output}",
        file_url(svg_path),
    ]

    completed = subprocess.run(command, capture_output=True, text=True)

    if completed.returncode != 0 or not tmp_output.exists():
        print(f"FAILED {svg_path}")
        if completed.stdout.strip():
            print(completed.stdout.strip())
        if completed.stderr.strip():
            print(completed.stderr.strip())
        return False

    png_path.write_bytes(tmp_output.read_bytes())
    tmp_output.unlink(missing_ok=True)
    print(f"OK {svg_path.relative_to(ROOT)} -> {png_path.relative_to(ROOT)}")
    return True


def iter_target_svgs() -> list[pathlib.Path]:
    svg_paths: set[pathlib.Path] = set()

    for pattern in TARGET_PATTERNS:
        svg_paths.update(IMAGE_ROOT.glob(pattern))

    return sorted(path for path in svg_paths if path.is_file())


def main() -> int:
    browser_path = find_browser()
    svg_files = iter_target_svgs()

    print(f"Browser: {browser_path}")
    print(f"SVG files: {len(svg_files)}")

    rendered = 0

    for svg_path in svg_files:
        if render_svg(browser_path, svg_path):
            rendered += 1

    print(f"Rendered: {rendered}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
