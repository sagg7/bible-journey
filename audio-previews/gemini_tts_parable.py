import base64
import json
import os
import re
import subprocess
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


def build_text(rows: list[dict]) -> str:
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


def extract_audio_bytes(response_json: dict) -> bytes:
    parts = response_json["candidates"][0]["content"]["parts"]
    for part in parts:
        inline = part.get("inlineData") or part.get("inline_data")
        if inline and inline.get("data"):
            return base64.b64decode(inline["data"])
    raise RuntimeError("Gemini response did not include inline audio data")


def main() -> None:
    if len(sys.argv) != 5:
        raise SystemExit("usage: gemini_tts_parable.py <voice> <verses.json> <output.pcm> <output.mp3>")

    voice, verses_path, output_pcm, output_mp3 = sys.argv[1], Path(sys.argv[2]), Path(sys.argv[3]), Path(sys.argv[4])
    env_path = Path(r"C:\Users\garci\Documents\code\scarlett\.env")
    api_key = load_env_value(env_path, "GEMINI_API_KEY")
    model = load_env_value(env_path, "GEMINI_TTS_MODEL") or "gemini-2.5-flash-preview-tts"
    if not api_key:
        raise SystemExit("GEMINI_API_KEY not found")

    rows = json.loads(verses_path.read_text(encoding="utf-8"))
    text = build_text(rows)
    prompt = (
        "Lee en espanol latino como narrador masculino, natural, calido y reverente. "
        "Haz pausas suaves entre escenas; no digas numeros de versiculo ni estas instrucciones. "
        "Narra exactamente el siguiente texto:\n\n"
        f"{text}"
    )

    payload = {
        "contents": [{"role": "user", "parts": [{"text": prompt}]}],
        "generationConfig": {
            "responseModalities": ["AUDIO"],
            "speechConfig": {
                "voiceConfig": {
                    "prebuiltVoiceConfig": {
                        "voiceName": voice,
                    }
                }
            },
        },
    }
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={api_key}"
    response = requests.post(url, json=payload, timeout=240)
    if response.status_code >= 400 and model != "gemini-2.5-flash-preview-tts":
        fallback_url = (
            "https://generativelanguage.googleapis.com/v1beta/models/"
            f"gemini-2.5-flash-preview-tts:generateContent?key={api_key}"
        )
        response = requests.post(fallback_url, json=payload, timeout=240)
    if response.status_code >= 400:
        raise SystemExit(f"Gemini TTS failed: {response.status_code} {response.text[:500]}")

    output_pcm.parent.mkdir(parents=True, exist_ok=True)
    output_pcm.write_bytes(extract_audio_bytes(response.json()))

    subprocess.run(
        [
            "ffmpeg",
            "-y",
            "-f",
            "s16le",
            "-ar",
            "24000",
            "-ac",
            "1",
            "-i",
            str(output_pcm),
            "-codec:a",
            "libmp3lame",
            "-b:a",
            "128k",
            str(output_mp3),
        ],
        check=True,
    )
    print({"voice": voice, "pcm": str(output_pcm), "mp3": str(output_mp3), "bytes": output_mp3.stat().st_size})


if __name__ == "__main__":
    main()
