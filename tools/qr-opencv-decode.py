#!/usr/bin/env python3
"""Private local QR extraction — raw data stays in a pipe to the PHP parent.

No networking, telemetry, persistent output, file writing or secret logging.
Only invoke this helper from the private-file-checking PHP CLI.
"""
import os
import sys


def main():
    if len(sys.argv) != 2 or not sys.argv[1].startswith("/"):
        return 1

    path = sys.argv[1]
    try:
        st = os.lstat(path)
        if (
            os.path.islink(path)
            or not os.path.isfile(path)
            or st.st_nlink != 1
            or not (0 < st.st_size <= 2097152)
        ):
            return 1

        import cv2

        cv2.setNumThreads(1)
        image = cv2.imread(path, cv2.IMREAD_COLOR)
        if image is None or image.shape[0] * image.shape[1] > 12000000:
            return 1

        value, points, _ = cv2.QRCodeDetector().detectAndDecode(image)
        if (
            not value
            or points is None
            or len(value.encode("utf-8")) > 2048
            or "\n" in value
            or "\r" in value
        ):
            return 1

        os.write(sys.stdout.fileno(), value.encode("utf-8"))
        return 0
    except Exception:
        return 1


if __name__ == "__main__":
    sys.exit(main())

