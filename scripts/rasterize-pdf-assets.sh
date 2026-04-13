#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE_ROOT="${ROOT_DIR}/appdatasheets/img"

if ! command -v rsvg-convert >/dev/null 2>&1; then
  echo "rsvg-convert not found; skipping SVG rasterization"
  exit 0
fi

convert_svg() {
  local svg_path="$1"
  local png_path="${svg_path%.svg}.png"

  if [[ -f "${png_path}" && "${png_path}" -nt "${svg_path}" ]]; then
    return 0
  fi

  echo "Rasterizing ${svg_path#${ROOT_DIR}/}"
  rsvg-convert \
    --background-color=white \
    --keep-aspect-ratio \
    --width=1800 \
    --output "${png_path}" \
    "${svg_path}"
}

while IFS= read -r -d '' svg_file; do
  convert_svg "${svg_file}"
done < <(
  find \
    "${IMAGE_ROOT}" \
    -type f \
    \( \
      -path "${IMAGE_ROOT}/temperaturas/*.svg" -o \
      -path "${IMAGE_ROOT}/*/desenhos/*.svg" -o \
      -path "${IMAGE_ROOT}/*/*/desenhos/*.svg" -o \
      -path "${IMAGE_ROOT}/*/diagramas/*.svg" -o \
      -path "${IMAGE_ROOT}/*/*/diagramas/*.svg" -o \
      -path "${IMAGE_ROOT}/*/diagramas/i/*.svg" -o \
      -path "${IMAGE_ROOT}/*/*/diagramas/i/*.svg" \
    \) \
    -print0
)

echo "SVG rasterization done"
