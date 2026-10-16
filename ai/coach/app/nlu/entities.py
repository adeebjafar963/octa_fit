from __future__ import annotations

import re

from app.nlu.normalize import normalize_text

# Arabic ordinals / session references (concept-level, not sentence rules).
_ORDINAL_MAP: dict[str, int] = {
    "الاول": 1,
    "اول": 1,
    "first": 1,
    "الثاني": 2,
    "ثاني": 2,
    "second": 2,
    "التالت": 3,
    "التالتة": 3,
    "التالته": 3,
    "الثالث": 3,
    "ثالث": 3,
    "third": 3,
    "الرابع": 4,
    "رابع": 4,
    "fourth": 4,
    "الخامس": 5,
    "خامس": 5,
    "fifth": 5,
    "السادس": 6,
    "سادس": 6,
    "sixth": 6,
    "السابع": 7,
    "سابع": 7,
    "seventh": 7,
}


def extract_day_index(text: str) -> int | None:
    """Deterministic day/session index from message."""
    norm = normalize_text(text)
    if not norm:
        return None

    m = re.search(r"\bday\s*(\d)\b", norm)
    if m:
        return _clamp_day(int(m.group(1)))

    m = re.search(r"(?:اليوم|يوم|day)\s*(\d)", norm)
    if m:
        return _clamp_day(int(m.group(1)))

    m = re.search(r"(?:حصه|حصة|جلسه|جلسة|session|workout)\s*(\d)", norm)
    if m:
        return _clamp_day(int(m.group(1)))

    m = re.search(r"(?:رقم|number)\s*(\d)", norm)
    if m:
        return _clamp_day(int(m.group(1)))

    for word, num in _ORDINAL_MAP.items():
        patterns = (
            rf"(?:اليوم|يوم|day)\s*{word}\b",
            rf"(?:حصه|حصة|جلسه|جلسة|session|workout)\s*{word}\b",
            rf"\b{word}\s*(?:يوم|اليوم|حصه|حصة|جلسه|جلسة|session|workout)\b",
            rf"باليوم\s*{word}\b",
            rf"on\s+day\s*{word}\b",
            rf"the\s+{word}\s+(?:day|session|workout)\b",
            rf"\b{word}\s+session\b",
        )
        for pat in patterns:
            if re.search(pat, norm):
                return num

    return None


def extract_duration_minutes(text: str) -> int | None:
    norm = normalize_text(text)
    m = re.search(r"(\d{1,3})\s*(?:min|mins|minutes|دقيقه|دقيقة|دقائق)\b", norm)
    if m:
        return min(180, max(15, int(m.group(1))))
    m = re.search(r"(\d+(?:\.\d+)?)\s*(?:hours?|hrs?|hr|ساعه|ساعة|ساعات)\b", norm)
    if m:
        return min(180, max(15, int(round(float(m.group(1)) * 60))))
    if re.search(r"\bساعتين\b|\btwo hours\b|\b2 hours\b", norm):
        return 120
    if re.search(r"\bساعه\b|\bone hour\b|\b1 hour\b", norm):
        return 60
    return None


def extract_days_per_week(text: str) -> int | None:
    norm = normalize_text(text)
    m = re.search(r"(\d)\s*(?:days?|ايام|أيام)\b", norm)
    if m:
        return min(7, max(1, int(m.group(1))))
    m = re.search(r"(?:من|for)\s*(\d)\s*(?:ايام|أيام|days?)", norm)
    if m:
        return min(7, max(1, int(m.group(1))))
    m = re.search(r"(\d)\s*(?:مرات|sessions?)\s*(?:بالاسبوع|بالأسبوع|per week|a week)?", norm)
    if m:
        return min(7, max(1, int(m.group(1))))
    if re.search(r"(اربعة|اربع)\s*(ايام|مرات|حصص)", norm):
        return 4
    if re.search(r"(خمسة|خمس)\s*(ايام|مرات|حصص)", norm):
        return 5
    if re.search(r"(ثلاثة|ثلاث)\s*(ايام|مرات|حصص)", norm):
        return 3
    if re.search(r"(ستة|ست)\s*(ايام|مرات|حصص)", norm):
        return 6
    if re.search(r"\bfour\s+days?\b", norm):
        return 4
    return None


def extract_quantity_grams(text: str) -> float | None:
    norm = normalize_text(text)
    m = re.search(r"(\d+(?:\.\d+)?)\s*(?:g|grams?|غرام|جرام|غم|غ)\b", norm)
    if m:
        return float(m.group(1))
    return None


def _clamp_day(n: int) -> int:
    return min(7, max(1, n))
