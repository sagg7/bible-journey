import json
import sys
from pathlib import Path

import torch
import torchaudio as ta
from chatterbox.mtl_tts import ChatterboxMultilingualTTS


CKPT = Path(
    r"C:\ai-models\scarlett-voice\hf-cache\hub\models--ResembleAI--chatterbox\snapshots\ef85ce7bef2f3f1a74d0d837d379d2fcb68203cd"
)
SILENCE_SECONDS = 0.45


def group_text(rows: list[dict]) -> list[tuple[str, float, float]]:
    by_verse = {int(row["verse_number"]): row["text"].strip() for row in rows}

    return [
        ("Lucas, capitulo quince. El hijo perdido.", 0.35, 0.40),
        (" ".join(by_verse[v] for v in range(11, 14)), 0.45, 0.32),
        (" ".join(by_verse[v] for v in range(14, 17)), 0.48, 0.28),
        (" ".join(by_verse[v] for v in range(17, 20)), 0.50, 0.28),
        (" ".join(by_verse[v] for v in range(20, 22)), 0.58, 0.25),
        (" ".join(by_verse[v] for v in range(22, 25)), 0.52, 0.30),
    ]


def main() -> None:
    if len(sys.argv) != 3:
        raise SystemExit("usage: chatterbox_local_parable.py <verses.json> <output.wav>")

    verses_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])
    rows = json.loads(verses_path.read_text(encoding="utf-8"))

    device = "cuda" if torch.cuda.is_available() else "cpu"
    model = ChatterboxMultilingualTTS.from_local(CKPT, device, t3_model="v2")

    pieces = []
    silence = torch.zeros(1, int(model.sr * SILENCE_SECONDS), device="cpu")

    for index, (text, exaggeration, cfg_weight) in enumerate(group_text(rows), start=1):
        print(f"segment {index}: {len(text)} chars")
        wav = model.generate(
            text,
            language_id="es",
            exaggeration=exaggeration,
            cfg_weight=cfg_weight,
        ).cpu()
        pieces.append(wav)
        pieces.append(silence)

    full_wav = torch.cat(pieces[:-1], dim=1)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    ta.save(str(output_path), full_wav, model.sr, encoding="PCM_S", bits_per_sample=16)
    print({"device": device, "sr": model.sr, "segments": len(pieces) // 2, "out": str(output_path)})


if __name__ == "__main__":
    main()
