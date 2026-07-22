from pathlib import Path

import torch
import torchaudio as ta
from chatterbox.mtl_tts import ChatterboxMultilingualTTS


device = "cuda" if torch.cuda.is_available() else "cpu"
ckpt = Path(
    r"C:\ai-models\scarlett-voice\hf-cache\hub\models--ResembleAI--chatterbox\snapshots\ef85ce7bef2f3f1a74d0d837d379d2fcb68203cd"
)
out = Path(
    r"C:\Users\garci\Documents\Codeshore\Bible Journey\audio-previews\nvi-chatterbox-local-smoke.wav"
)

model = ChatterboxMultilingualTTS.from_local(ckpt, device, t3_model="v2")
wav = model.generate(
    "Lucas, capitulo quince. Un padre corre a abrazar a su hijo que vuelve a casa.",
    language_id="es",
    exaggeration=0.45,
    cfg_weight=0.30,
)
ta.save(str(out), wav.cpu(), model.sr, encoding="PCM_S", bits_per_sample=16)
print({"device": device, "sr": model.sr, "shape": tuple(wav.shape), "out": str(out)})
