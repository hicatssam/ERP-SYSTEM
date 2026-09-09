#!/usr/bin/env python3
"""
Generic Dahab/TISH attendance bridge sender.

This file intentionally does NOT talk to a specific biometric device.
The device-specific adapter should read punches using the vendor SDK/API,
normalize them into the payload below, then call send_punches().

Environment:
  ATTENDANCE_ENDPOINT=http://server/attendance/integrations/devices/1/punches
  ATTENDANCE_DEVICE_TOKEN=<token shown once in admin>
"""

import json
import os
import sys
import urllib.request
import urllib.error


ENDPOINT = os.environ.get("ATTENDANCE_ENDPOINT", "").strip()
TOKEN = os.environ.get("ATTENDANCE_DEVICE_TOKEN", "").strip()


def post_json(url: str, payload: dict) -> dict:
    if not url:
        raise RuntimeError("ATTENDANCE_ENDPOINT is missing")
    if not TOKEN:
        raise RuntimeError("ATTENDANCE_DEVICE_TOKEN is missing")

    body = json.dumps(payload).encode("utf-8")

    request = urllib.request.Request(
        url,
        data=body,
        method="POST",
        headers={
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Authorization": f"Bearer {TOKEN}",
        },
    )

    with urllib.request.urlopen(request, timeout=30) as response:
        return json.loads(response.read().decode("utf-8"))


def send_punches(punches: list[dict]) -> dict:
    return post_json(
        ENDPOINT,
        {"punches": punches},
    )


def heartbeat() -> dict:
    heartbeat_url = ENDPOINT.rsplit("/punches", 1)[0] + "/heartbeat"
    return post_json(heartbeat_url, {})


def main() -> int:
    if len(sys.argv) >= 2 and sys.argv[1] == "--heartbeat":
        print(json.dumps(heartbeat(), ensure_ascii=False, indent=2))
        return 0

    if len(sys.argv) < 2:
        print("Usage: bridge_example.py punches.json | --heartbeat")
        return 2

    with open(sys.argv[1], "r", encoding="utf-8") as handle:
        data = json.load(handle)

    punches = data["punches"] if isinstance(data, dict) else data

    print(
        json.dumps(
            send_punches(punches),
            ensure_ascii=False,
            indent=2,
        )
    )

    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        print(f"HTTP {exc.code}: {body}", file=sys.stderr)
        raise SystemExit(1)
    except Exception as exc:
        print(str(exc), file=sys.stderr)
        raise SystemExit(1)
