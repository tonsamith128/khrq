#!/usr/bin/env python3
"""Private offline QR extraction with safe image-region and contrast fallbacks.

Only emit decoded text to the private PHP subprocess pipe after success.
Never print bank QR data to stderr, write derived files, or use networking.
"""
import os
import signal
import stat
import sys


def candidates(image, cv2):
    height, width = image.shape[:2]
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    yield image
    yield gray

    detector = cv2.QRCodeDetector()
    try:
        found, points = detector.detect(gray)
        if found and points is not None:
            corners = points.reshape(-1, 2)
            x0, y0 = corners.min(axis=0)
            x1, y1 = corners.max(axis=0)
            margin = max(12, int(max(x1 - x0, y1 - y0) * 0.08))
            left = max(0, int(x0) - margin)
            top = max(0, int(y0) - margin)
            right = min(width, int(x1) + margin)
            bottom = min(height, int(y1) + margin)
            if right - left >= 100 and bottom - top >= 100:
                region = image[top:bottom, left:right]
                yield region
                yield cv2.cvtColor(region, cv2.COLOR_BGR2GRAY)
    except Exception:
        pass

    # Allow a phone screenshot to contain the centered payment card, text,
    # bank logo and footer. These are framing guesses, not account semantics.
    for cy in (0.50, 0.56):
        for fraction in (0.52, 0.68):
            side = min(int(width * fraction), int(height * 0.70))
            x0 = max(0, int(width * 0.50 - side / 2))
            y0 = max(0, int(height * cy - side / 2))
            region = image[y0:min(height, y0 + side), x0:min(width, x0 + side)]
            if min(region.shape[:2]) < 100:
                continue
            gray_region = cv2.cvtColor(region, cv2.COLOR_BGR2GRAY)
            yield region
            yield gray_region
            if region.shape[0] < 1000:
                yield cv2.resize(gray_region, None, fx=1.5, fy=1.5,
                                 interpolation=cv2.INTER_NEAREST)

    yield cv2.resize(gray, None, fx=0.65, fy=0.65,
                     interpolation=cv2.INTER_AREA)
    yield cv2.threshold(gray, 0, 255,
                        cv2.THRESH_BINARY + cv2.THRESH_OTSU)[1]


def main():
    if len(sys.argv) != 2 or not sys.argv[1].startswith("/"):
        return 1
    path = sys.argv[1]
    try:
        info = os.lstat(path)
        if (not stat.S_ISREG(info.st_mode) or info.st_nlink != 1 or
                info.st_mode & 0o077 or not (0 < info.st_size <= 2097152)):
            return 1

        import cv2

        cv2.setNumThreads(1)
        image = cv2.imread(path, cv2.IMREAD_COLOR)
        if image is None or image.shape[0] * image.shape[1] > 12000000:
            return 1

        if hasattr(signal, "SIGALRM"):
            def timeout(_signum, _frame):
                raise TimeoutError()
            signal.signal(signal.SIGALRM, timeout)
            signal.alarm(35)

        detector = cv2.QRCodeDetector()
        for variant in candidates(image, cv2):
            try:
                decoded, corners, _ = detector.detectAndDecode(variant)
                if (corners is not None and decoded and
                        len(decoded.encode("utf-8")) <= 2048 and
                        "\n" not in decoded and "\r" not in decoded):
                    os.write(sys.stdout.fileno(), decoded.encode("utf-8"))
                    return 0
            except Exception:
                continue
        return 1
    except Exception:
        # Avoid leaking paths, QR contents, account data or traceback.
        return 1
    finally:
        if hasattr(signal, "SIGALRM"):
            signal.alarm(0)


if __name__ == "__main__":
    sys.exit(main())
