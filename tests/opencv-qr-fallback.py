#!/usr/bin/env python3
"""Pure synthetic QR tests: private decoded data is never published."""
import os
import subprocess
import sys
import tempfile
from pathlib import Path

import qrcode
from PIL import Image, ImageDraw


def run(script, image_path, expected, python_bin):
    outcome = subprocess.run(
        [python_bin, str(script), str(image_path)],
        capture_output=True, timeout=45, check=False,
    )
    if outcome.returncode != 0 or outcome.stdout != expected or outcome.stderr:
        raise AssertionError("Private OpenCV QR scan did not decode fixture safely")


def main():
    script = Path(__file__).resolve().parent.parent / "tools" / "qr-opencv-decode.py"
    decoder_python = os.environ.get("KHQR_TEST_DECODE_PYTHON", sys.executable)
    payload = "SYNTHETIC_TEST_ONLY_" + "A1B2C3D4" * 22
    qr = qrcode.QRCode(
        error_correction=qrcode.constants.ERROR_CORRECT_H, box_size=7, border=4,
    )
    qr.add_data(payload)
    qr.make(fit=True)
    qr_image = qr.make_image(fill_color="black", back_color="white").convert("RGB")

    with tempfile.TemporaryDirectory(prefix="khqr-synthetic-qr-") as directory:
        path = Path(directory) / "synthetic.png"
        qr_image.save(path)
        os.chmod(path, 0o600)
        run(script, path, payload.encode(), decoder_python)

        # High-density, center-logo QR embedded in a taller mobile screenshot.
        # This is a fictional image and contains NO real bank data, logos or IDs.
        page = Image.new("RGB", (1000, 1450), "#f8f8f8")
        page.paste(qr_image.resize((560, 560)), (220, 540))
        ImageDraw.Draw(page).ellipse(
            (470, 790, 530, 850), fill="#fdfdfd", outline="#ef1616", width=6,
        )
        page.save(path)
        run(script, path, payload.encode(), decoder_python)

        path.write_bytes(b"not an image")
        result = subprocess.run(
            [decoder_python, str(script), str(path)],
            capture_output=True, timeout=45, check=False,
        )
        if result.returncode == 0 or result.stdout or result.stderr:
            raise AssertionError("Invalid file must fail without leaking anything")

    print("Offline OpenCV QR: synthetic original, logo screenshot and denial PASS")


if __name__ == "__main__":
    main()
