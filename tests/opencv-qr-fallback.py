#!/usr/bin/env python3
"""Optional CI smoke of local QR decoding with entirely synthetic data."""
import os
import subprocess
import sys
import tempfile
from pathlib import Path

import qrcode


def main():
    root = Path(__file__).resolve().parent.parent
    script = root / "tools" / "qr-opencv-decode.py"
    payload = "SYNTHETIC_TEST_ONLY_" + ("A1B2C3D4" * 23)
    with tempfile.TemporaryDirectory(prefix="khqr-opencv-") as directory:
        path = Path(directory) / "generated.png"
        code = qrcode.QRCode(error_correction=qrcode.constants.ERROR_CORRECT_H,
                             box_size=8, border=4)
        code.add_data(payload)
        code.make(fit=True)
        code.make_image(fill_color="black", back_color="white").save(path)
        os.chmod(path, 0o600)

        result = subprocess.run(
            [sys.executable, str(script), str(path)],
            capture_output=True, timeout=20, check=False,
        )
        if result.returncode != 0 or result.stdout != payload.encode() or result.stderr:
            raise AssertionError("Local OpenCV decode failed for synthetic QR")

        path.write_bytes(b"this is not a QR")
        result = subprocess.run(
            [sys.executable, str(script), str(path)],
            capture_output=True, timeout=20, check=False,
        )
        if result.returncode == 0 or result.stdout or result.stderr:
            raise AssertionError("Bad image should fail without data disclosure")

    print("OpenCV local QR fallback: synthetic decode, invalid input, no stderr PASS")


if __name__ == "__main__":
    main()

