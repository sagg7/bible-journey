import json
import os
import re
import sys
from pathlib import Path

import requests


def load_env_value(env_path: Path, key: str) -> str | None:
    if os.getenv(key):
        return os.getenv(key)

    if not env_path.exists():
        return None

    pattern = re.compile(rf"^{re.escape(key)}=(.*)$")
    for line in env_path.read_text(encoding="utf-8", errors="ignore").splitlines():
        match = pattern.match(line.strip())
        if match:
            return match.group(1).strip().strip('"').strip("'")
    return None


def build_input(rows: list[dict]) -> str:
    by_verse = {int(row["verse_number"]): row["text"].strip() for row in rows}
    segments = [
        "Lucas, capitulo quince. El hijo perdido.",
        " ".join(by_verse[v] for v in range(11, 14)),
        " ".join(by_verse[v] for v in range(14, 17)),
        " ".join(by_verse[v] for v in range(17, 20)),
        " ".join(by_verse[v] for v in range(20, 22)),
        " ".join(by_verse[v] for v in range(22, 25)),
    ]
    return "\n\n".join(segments)


def main() -> None:
    if len(sys.argv) != 3:
        raise SystemExit("usage: openai_nova_parable.py <verses.json> <output.mp3>")

    verses_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])
    rows = json.loads(verses_path.read_text(encoding="utf-8"))
    api_key = load_env_value(Path(r"C:\Users\garci\Documents\code\scarlett\.env"), "OPENAI_API_KEY")
    if not api_key:
        raise SystemExit("OPENAI_API_KEY not found")

    payload = {
        "model": "gpt-4o-mini-tts",
        "voice": "nova",
        "format": "mp3",
        "input": build_input(rows),
        "instructions": (
            "Lee en espanol latino con voz femenina natural, calida y contemplativa. "
            "Haz pausas suaves entre escenas. Mantén reverencia, ternura y una emocion "
            "creciente cuando el padre recibe al hijo. No anuncies numeros de versiculo."
        ),
    }
    response = requests.post(
        "https://api.openai.com/v1/audio/speech",
        headers={"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"},
        json=payload,
        timeout=180,
    )
    if response.status_code >= 400:
        raise SystemExit(f"OpenAI TTS failed: {response.status_code} {response.text[:500]}")

    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_bytes(response.content)
    print({"output": str(output_path), "bytes": len(response.content)})


if __name__ == "__main__":
    main()
