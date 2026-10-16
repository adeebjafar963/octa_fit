from __future__ import annotations

import re
import unicodedata


_ARABIC_DIACRITICS = re.compile(r"[\u064B-\u065F\u0670\u0640]")
_ALEF_VARIANTS = str.maketrans({"أ": "ا", "إ": "ا", "آ": "ا", "ٱ": "ا"})
_TAA_VARIANTS = str.maketrans({"ة": "ه", "ى": "ي"})


def normalize_text(text: str) -> str:
    """Normalize Arabic/English for semantic token matching."""
    lowered = text.lower().strip()
    lowered = unicodedata.normalize("NFKC", lowered)
    lowered = _ARABIC_DIACRITICS.sub("", lowered)
    lowered = lowered.translate(_ALEF_VARIANTS).translate(_TAA_VARIANTS)
    lowered = re.sub(r"[^\w\s\u0600-\u06FF]", " ", lowered)
    lowered = re.sub(r"\s+", " ", lowered).strip()
    return lowered


def tokenize(text: str) -> list[str]:
    normalized = normalize_text(text)
    if not normalized:
        return []
    return [t for t in normalized.split() if len(t) >= 1]


def has_any_token(tokens: list[str], candidates: set[str]) -> bool:
    token_set = set(tokens)
    for c in candidates:
        if c in token_set:
            return True
        for t in tokens:
            if t.startswith(c) or c.startswith(t):
                return True
    return False


def has_substring(text: str, needles: set[str]) -> bool:
    norm_text = normalize_text(text) if text != normalize_text(text) else text
    for n in needles:
        needle = normalize_text(n)
        if needle and needle in norm_text:
            return True
        if n in text:
            return True
    return False
