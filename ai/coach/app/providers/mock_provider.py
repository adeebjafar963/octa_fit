from __future__ import annotations

import re
from typing import Literal

from app.providers.base import ChatRequest, ChatResponse, CoachProvider

MEDICAL_PATTERNS = re.compile(
    r"("
    r"diagnos|injury|injur|fracture|dislocat|steroid|anabolic|prescription|"
    r"medication|pain\s+killer|opioid|chest\s+pain|can'?t\s+breathe|"
    r"numbness|paralysis|swelling\s+severe|what\s+injury|"
    r"تشخيص|إصابة|كسر|خلع|ستيرويد|دواء|وصفة|ألم\s+صدر|ضيق\s+تنفس|تخدير"
    r")",
    re.IGNORECASE,
)

EMERGENCY_PATTERNS = re.compile(
    r"("
    r"chest\s+pain|can'?t\s+breathe|loss\s+of\s+consciousness|unconscious|"
    r"severe\s+bleeding|stroke|heart\s+attack|"
    r"ألم\s+صدر|لا\s+أستطيع\s+التنفس|فقدان\s+الوعي|نزيف\s+شديد"
    r")",
    re.IGNORECASE,
)


def detect_language(text: str, explicit: Literal["en", "ar"] | None = None) -> Literal["en", "ar"]:
    if explicit in ("en", "ar"):
        return explicit
    arabic_chars = len(re.findall(r"[\u0600-\u06FF]", text))
    return "ar" if arabic_chars >= max(3, len(text) // 4) else "en"


def is_emergency(text: str) -> bool:
    return bool(EMERGENCY_PATTERNS.search(text))


def is_medical(text: str) -> bool:
    return bool(MEDICAL_PATTERNS.search(text))


class MockCoachProvider(CoachProvider):
    name = "mock"

    async def generate(self, request: ChatRequest, language: Literal["en", "ar"]) -> ChatResponse:
        text = request.message.strip()
        lower = text.lower()

        if is_emergency(text):
            return ChatResponse(
                message=_emergency(language),
                provider=self.name,
                language=language,
            )

        if is_medical(text):
            return ChatResponse(
                message=_medical_redirect(language),
                provider=self.name,
                language=language,
            )

        candidates = request.parsed_exercise_candidates()
        routing = request.routing if isinstance(request.routing, dict) else {}
        topic = _topic(lower, text, routing)
        answer = _answer(topic, language, routing)

        if request.wants_personalization():
            prefix = _profile_prefix(request.user_context, language)
            if prefix:
                answer = f"{prefix}\n\n{answer}"

        # Phase 11F — keep natural answer; do not dump retrieval chunks into chat prose.
        # Laravel/Flutter may still use knowledge_context for the evidence UI.
        knowledge = request.knowledge_context if isinstance(request.knowledge_context, dict) else None
        routing_mode = str(routing.get("response_mode") or "")
        if (
            knowledge
            and (knowledge.get("item_count") or 0) > 0
            and routing_mode == "detailed"
            and not routing.get("is_general_sports")
        ):
            answer = _knowledge_aware_answer(knowledge, language, answer)
        elif knowledge and knowledge.get("personalized_targets") and request.wants_personalization():
            answer = _targets_prefix(knowledge.get("personalized_targets"), language) + "\n\n" + answer

        grounded_payload: list[dict] = []
        if candidates and _wants_exercise_grounding(lower, topic, routing):
            # Recommend only DB-backed candidates — never invent IDs.
            picked = candidates[:5]
            grounded_payload = [e.model_dump() for e in picked]
            answer = f"{answer}\n\n{_format_grounded_exercises(picked, language)}"

        return ChatResponse(
            message=answer,
            provider=self.name,
            language=language,
            grounded_exercises=grounded_payload,
        )


def _targets_prefix(targets: dict, language: Literal["en", "ar"]) -> str:
    if not isinstance(targets, dict):
        return ""
    cal = targets.get("calorie_target")
    protein = (targets.get("macro_targets") or {}).get("protein_g")
    if language == "ar":
        return f"تقدير حتمي: {cal} سعرة/يوم، بروتين ≈ {protein} غ/يوم (ليست أرقامًا مخترعة)."
    return (
        f"Deterministic estimate: {cal} kcal/day, protein ≈ {protein} g/day "
        "(from Laravel engines — not invented by mock RAG)."
    )


def _knowledge_aware_answer(knowledge: dict, language: Literal["en", "ar"], fallback: str) -> str:
    items = knowledge.get("items") or []
    lines: list[str] = []
    targets = knowledge.get("personalized_targets")
    if isinstance(targets, dict) and targets.get("authoritative"):
        lines.append(_targets_prefix(targets, language))
        lines.append("")

    if language == "ar":
        lines.append("ملخص قائم على مصادر مسترجعة (المحاكي لا يخترع دراسات):")
    else:
        lines.append(
            "Evidence-oriented summary using Laravel-retrieved sources "
            "(mock provider does not invent papers):"
        )

    for item in items[:3]:
        if not isinstance(item, dict):
            continue
        src = item.get("source") or item.get("title") or "Source"
        level = item.get("evidence_level") or "unknown"
        snippet = str(item.get("content") or "")[:280].replace("\n", " ")
        lines.append(f"• [{level}] {src}: {snippet}")

    if not items:
        return fallback

    lines.append("")
    lines.append(
        "Mock note: this is not a live LLM synthesis — production OpenAI path uses the same knowledge_context."
        if language == "en"
        else "ملاحظة: هذا ملخص محاكٍ — مسار OpenAI يستخدم نفس سياق المعرفة."
    )
    return "\n".join(lines)


def _wants_exercise_grounding(lower: str, topic: str, routing: dict | None = None) -> bool:
    routing = routing or {}
    if routing.get("is_food_lookup") or routing.get("nutrition_fallthrough"):
        return False
    if routing.get("is_general_sports") and topic not in {"chest", "shoulder", "mma"}:
        return False
    if topic in {"chest", "shoulder", "muscle", "beginner", "mma"}:
        return True
    keys = (
        "exercise", "workout", "program", "chest", "back", "leg", "shoulder",
        "bench", "squat", "تمرين", "تدريب", "خطة", "برنامج",
    )
    # Avoid treating Arabic "صدر دجاج" as chest training.
    if "صدر دجاج" in lower or "دجاج" in lower:
        return False
    return any(k in lower for k in keys)


def _topic(lower: str, original: str, routing: dict | None = None) -> str:
    routing = routing or {}
    if routing.get("is_food_lookup") or routing.get("nutrition_fallthrough") or routing.get("domain") == "nutrition":
        return "nutrition"
    if routing.get("is_general_sports"):
        if any(k in lower for k in ("progressive overload", "overload", "تقدم تدريجي")):
            return "progressive_overload"
        if any(k in lower for k in ("rest between", "بين المجموعات", "بين الجولات", "راحة")):
            return "recovery"
        return "general"
    if any(k in lower for k in ("muscle", "hypertrophy", "gain", "bulk", "بناء عضل", "تضخيم", "زيادة وزن")):
        return "muscle"
    if any(k in lower for k in ("fat", "lose", "cut", "weight loss", "حرق", "تنشيف", "خسارة وزن")):
        return "fat"
    if any(k in lower for k in ("beginner", "مبتدئ", "start")):
        return "beginner"
    if any(k in lower for k in ("protein", "meal", "nutrition", "eat", "food", "بروتين", "تغذية", "وجبة", "أكل", "سعرة", "سعرات", "تفاح", "دجاج", "كالوري")):
        return "nutrition"
    # Chest training only when not a food question about chicken breast.
    if any(k in lower for k in ("chest",)) or ("صدر" in lower and "دجاج" not in lower):
        return "chest"
    if any(k in lower for k in ("shoulder", "كتف")):
        return "shoulder"
    if any(k in lower for k in ("mma", "condition", "فنون قتال", "تحمل")):
        return "mma"
    if any(k in lower for k in ("recover", "sleep", "rest", "تعافي", "نوم", "راحة")):
        return "recovery"
    if any(k in lower for k in ("day", "week", "frequency", "أيام", "أسبوع")):
        return "frequency"
    return "general"


def _format_grounded_exercises(exercises: list, language: Literal["en", "ar"]) -> str:
    lines: list[str] = []
    if language == "ar":
        lines.append("تمارين من مكتبة أوكتا فيت (معرّفات حقيقية):")
        for ex in exercises:
            lines.append(f"• {ex.name} (#{ex.exercise_id})")
    else:
        lines.append("OCTA FIT library exercises (real IDs):")
        for ex in exercises:
            lines.append(f"• {ex.name} (#{ex.exercise_id})")
    return "\n".join(lines)


def _profile_prefix(ctx: dict | None, lang: Literal["en", "ar"]) -> str:
    from app.context.user_context import parse_user_context

    parsed = parse_user_context(ctx if isinstance(ctx, dict) else None)
    if not parsed.available:
        return ""

    goal = parsed.goals.primary_goal or "general_fitness"
    level = parsed.training.fitness_level or "unspecified"
    weight = parsed.body.weight_kg
    freq = parsed.training.training_frequency_per_week
    injury = parsed.recovery.injury_status or "none"
    equipment = parsed.training.equipment or []
    location = parsed.training.training_location
    meals = parsed.nutrition.meals_per_day
    sleep = parsed.recovery.sleep_hours
    recent_types = []
    if isinstance(parsed.workout, dict):
        recent_types = parsed.workout.get("recent_training_types") or []

    if lang == "ar":
        parts = [f"بناءً على ملفك: المستوى={level}، الهدف={goal}"]
        if weight is not None:
            parts.append(f"الوزن={weight} كغ")
        if freq is not None:
            parts.append(f"التكرار={freq} أيام/أسبوع")
        if location:
            parts.append("المكان=" + str(location))
        if equipment:
            parts.append("المعدات=" + ", ".join(str(e) for e in equipment[:6]))
        if meals is not None:
            parts.append(f"وجبات/يوم={meals}")
        if sleep is not None:
            parts.append(f"نوم={sleep} س")
        if recent_types:
            parts.append("تدريب أخير=" + ", ".join(str(t) for t in recent_types[:4]))
        if injury != "none":
            parts.append(f"حالة الإصابة={injury} — سأراعي السلامة أولاً")
        mem = _memory_hint(parsed, "ar")
        if mem:
            parts.append(mem)
        return " · ".join(parts)

    parts = [f"Based on your profile: level={level}, goal={goal}"]
    if weight is not None:
        parts.append(f"weight={weight}kg")
    if freq is not None:
        parts.append(f"frequency={freq} days/week")
    if location:
        parts.append(f"location={location}")
    if equipment:
        parts.append("equipment=" + ", ".join(str(e) for e in equipment[:6]))
    if meals is not None:
        parts.append(f"meals/day={meals}")
    if sleep is not None:
        parts.append(f"sleep={sleep}h")
    if recent_types:
        parts.append("recent training=" + ", ".join(str(t) for t in recent_types[:4]))
    if injury != "none":
        parts.append(f"injury_status={injury} — safety first")
    mem = _memory_hint(parsed, "en")
    if mem:
        parts.append(mem)
    return " · ".join(parts)


def _emergency(lang: Literal["en", "ar"]) -> str:
    if lang == "ar":
        return (
            "هذه الأعراض قد تتطلب رعاية طبية عاجلة.\n\n"
            "• توقف عن الاعتماد على نصيحة لياقة عامة\n"
            "• اطلب المساعدة الطبية الطارئة فورًا إذا كانت الأعراض شديدة\n\n"
            "أنا مدرب رياضي داخل أوكتا فيت ولست طبيبًا."
        )
    return (
        "These symptoms may need urgent medical care.\n\n"
        "• Do not rely on general fitness advice for this\n"
        "• Seek emergency medical help immediately if symptoms are severe\n\n"
        "I am an OCTA FIT sports coach, not a doctor."
    )


def _memory_hint(parsed, lang: Literal["en", "ar"]) -> str:
    """Test-only compact memory echo — not a substitute for real extraction."""
    items = parsed.memory_context.memories if parsed.memory_context else []
    if not items:
        return ""
    keys = []
    for item in items[:4]:
        if item.key:
            keys.append(str(item.key))
    if not keys:
        return ""
    if lang == "ar":
        return "ذاكرة مدرب=" + ", ".join(keys)
    return "coach_memory=" + ", ".join(keys)


def _medical_redirect(lang: Literal["en", "ar"]) -> str:
    if lang == "ar":
        return (
            "لا أستطيع تشخيص الإصابات أو الحالات الطبية.\n\n"
            "• توقف أو عدّل الحركة إذا كانت تسبب ألمًا\n"
            "• راجع أخصائي رعاية صحية مؤهل عند الحاجة\n"
            "• يمكن لاحقًا استخدام مساعد العلاج الطبيعي في أوكتا فيت للأسئلة التعليمية العامة\n\n"
            "يمكنني مساعدتك بأسئلة التدريب والتغذية الرياضية العامة."
        )
    return (
        "I can't diagnose injuries or medical conditions.\n\n"
        "• Stop or modify the movement if it causes pain\n"
        "• Consider assessment by a qualified healthcare professional\n"
        "• OCTA FIT's future physiotherapist assistant can help with general educational guidance later\n\n"
        "I can still help with training and general sports nutrition questions."
    )


def _answer(topic: str, lang: Literal["en", "ar"], routing: dict | None = None) -> str:
    answers_en = {
        "muscle": (
            "Building muscle needs progressive training and recovery.\n\n"
            "• Lift with progressive overload\n"
            "• Prioritize compound lifts plus targeted accessories\n"
            "• Eat enough protein and total calories\n"
            "• Sleep and recover between hard sessions\n\n"
            "Suggested approach:\n"
            "1. Train each major muscle group 2–3 times per week\n"
            "2. Aim for ~1.6–2.2 g protein per kg of body weight (general fitness guidance)\n"
            "3. Track weekly progress and increase load when form stays solid"
        ),
        "fat": (
            "Fat loss is mainly about a sustainable calorie deficit plus training.\n\n"
            "• Keep protein high to protect muscle\n"
            "• Strength train 3–5 days per week\n"
            "• Add walking or light cardio for daily activity\n"
            "• Avoid extreme crash diets\n\n"
            "Suggested approach:\n"
            "1. Slight calorie deficit you can maintain\n"
            "2. Full-body or upper/lower training\n"
            "3. Sleep well and stay consistent for weeks, not days"
        ),
        "beginner": (
            "A simple beginner plan beats a complicated one.\n\n"
            "• Learn basic movement patterns first\n"
            "• Train 3 days per week to start\n"
            "• Focus on squats, hinges, pushes, pulls, and core\n"
            "• Progress gradually\n\n"
            "Suggested approach:\n"
            "1. Full-body sessions A/B\n"
            "2. 2–3 sets of 8–12 reps\n"
            "3. Rest 48 hours between hard sessions"
        ),
        "nutrition": (
            "Keep nutrition practical.\n\n"
            "• Protein: eggs, chicken, yogurt, fish, legumes\n"
            "• Carbs around training for energy\n"
            "• Healthy fats for hormones and satiety\n"
            "• Hydrate through the day\n\n"
            "Suggested approach:\n"
            "1. Build each meal around a protein source\n"
            "2. Add vegetables and a smart carb\n"
            "3. Adjust portions based on your goal (gain / maintain / lose)"
        ),
        "chest": (
            "Chest training works best with a push emphasis.\n\n"
            "• Horizontal press (bench / push-up)\n"
            "• Incline press for upper chest\n"
            "• Fly or cable work for stretch and control\n"
            "• Keep shoulders stable and elbows under control\n\n"
            "Suggested approach:\n"
            "1. 1–2 pressing movements\n"
            "2. 1 isolation movement\n"
            "3. 8–15 controlled reps with good form"
        ),
        "shoulder": (
            "Healthy shoulders need balance, not just presses.\n\n"
            "• Overhead press\n"
            "• Lateral raises\n"
            "• Rear-delt work\n"
            "• Face pulls / external rotation for support\n\n"
            "Suggested approach:\n"
            "1. One pressing pattern\n"
            "2. Side and rear delts\n"
            "3. Keep loads moderate and technique strict"
        ),
        "mma": (
            "MMA conditioning should support skill work, not replace it.\n\n"
            "• Mix strength, intervals, and recovery\n"
            "• Keep sessions short and purposeful\n"
            "• Protect joints with gradual volume\n\n"
            "Suggested approach:\n"
            "1. 2 strength sessions\n"
            "2. 2 conditioning sessions\n"
            "3. Technical practice with enough rest"
        ),
        "recovery": (
            "Recovery is part of training.\n\n"
            "• Sleep 7–9 hours when possible\n"
            "• Eat enough to support training\n"
            "• Use easy movement on rest days\n"
            "• Deload when progress stalls or fatigue stays high\n\n"
            "Suggested approach:\n"
            "1. Schedule rest days\n"
            "2. Keep one easier week every 4–8 weeks if needed\n"
            "3. Prioritize sleep before adding more volume"
        ),
        "frequency": (
            "Most people do well with 3–5 training days.\n\n"
            "• Beginners: 3 days\n"
            "• Intermediate: 4 days\n"
            "• Advanced: 5+ only if recovery is solid\n\n"
            "Suggested approach:\n"
            "1. Pick a schedule you can repeat\n"
            "2. Leave at least one full rest day\n"
            "3. Increase frequency only after consistency is easy"
        ),
        "general": (
            "I am the OCTA FIT Sports Coach.\n\n"
            "I can help with:\n"
            "• Training plans and exercise selection\n"
            "• Muscle gain, fat loss, bulking, and cutting basics\n"
            "• Beginner guidance and recovery\n"
            "• General sports nutrition\n\n"
            "Ask something specific like “beginner full-body plan” or “high-protein meal ideas after training.”"
        ),
    }

    answers_ar = {
        "muscle": (
            "بناء العضلات يحتاج تدريبًا تدريجيًا وتعافيًا جيدًا.\n\n"
            "• زد الأوزان تدريجيًا مع الحفاظ على التقنية\n"
            "• ركّز على التمارين المركبة مع تمارين مساعدة\n"
            "• تناول بروتينًا كافيًا وسعرات مناسبة\n"
            "• اهتم بالنوم والراحة بين الجلسات\n\n"
            "اقتراح عملي:\n"
            "1. درّب كل مجموعة عضلية 2–3 مرات أسبوعيًا\n"
            "2. استهدف حوالي 1.6–2.2 غ بروتين لكل كغ من وزن الجسم (إرشاد لياقة عام)\n"
            "3. تابع التقدم وزد الحمل عندما تكون التقنية ثابتة"
        ),
        "fat": (
            "خسارة الدهون تعتمد أساسًا على عجز سعرات مستدام مع التدريب.\n\n"
            "• حافظ على بروتين مرتفع لحماية العضلات\n"
            "• قوّة 3–5 أيام أسبوعيًا\n"
            "• أضف مشيًا أو كارديو خفيفًا\n"
            "• تجنب الحميات القاسية\n\n"
            "اقتراح عملي:\n"
            "1. عجز سعرات بسيط يمكنك الاستمرار عليه\n"
            "2. تدريب كامل الجسم أو علوي/سفلي\n"
            "3. نم جيدًا وكن منتظمًا لأسابيع"
        ),
        "beginner": (
            "خطة بسيطة للمبتدئ أفضل من خطة معقدة.\n\n"
            "• تعلّم الأنماط الأساسية أولًا\n"
            "• ابدأ بـ 3 أيام أسبوعيًا\n"
            "• ركّز على القرفصاء والدفع والسحب والجذع\n"
            "• تقدّم تدريجيًا\n\n"
            "اقتراح عملي:\n"
            "1. جلسات كامل الجسم A/B\n"
            "2. 2–3 مجموعات من 8–12 تكرارًا\n"
            "3. راحة 48 ساعة بين الجلسات القاسية"
        ),
        "nutrition": (
            "اجعل التغذية عملية وبسيطة.\n\n"
            "• بروتين: بيض، دجاج، لبن، سمك، بقوليات\n"
            "• كربوهيدرات حول التمرين للطاقة\n"
            "• دهون صحية للشبع والتوازن\n"
            "• اشرب ماءً كافيًا يوميًا\n\n"
            "اقتراح عملي:\n"
            "1. ابنِ كل وجبة حول مصدر بروتين\n"
            "2. أضف خضارًا وكربوهيدرات مناسبة\n"
            "3. عدّل الحصص حسب هدفك"
        ),
        "chest": (
            "تدريب الصدر يعتمد على حركات الدفع.\n\n"
            "• ضغط أفقي (بنش / ضغط أرضي)\n"
            "• ضغط مائل للصدر العلوي\n"
            "• تمارين فتح للتمدد والتحكم\n"
            "• حافظ على ثبات الكتفين\n\n"
            "اقتراح عملي:\n"
            "1. حركة ضغط أساسية أو اثنتان\n"
            "2. حركة عزل واحدة\n"
            "3. 8–15 تكرارًا بتقنية جيدة"
        ),
        "shoulder": (
            "الكتف الصحي يحتاج توازنًا وليس ضغطًا فقط.\n\n"
            "• ضغط فوق الرأس\n"
            "• رفع جانبي\n"
            "• عمل للخلف\n"
            "• سحب وجه / تدوير خارجي للدعم\n\n"
            "اقتراح عملي:\n"
            "1. نمط ضغط واحد\n"
            "2. عمل جانبي وخلفي\n"
            "3. أوزان معتدلة وتقنية صارمة"
        ),
        "mma": (
            "تحمل فنون القتال يجب أن يدعم المهارة لا أن يحل محلها.\n\n"
            "• امزج القوة والفترات والراحة\n"
            "• اجعل الجلسات قصيرة وهادفة\n"
            "• زد الحجم تدريجيًا لحماية المفاصل\n\n"
            "اقتراح عملي:\n"
            "1. جلستا قوة\n"
            "2. جلستا تحمل\n"
            "3. تدريب مهاري مع راحة كافية"
        ),
        "recovery": (
            "التعافي جزء من التدريب.\n\n"
            "• نم 7–9 ساعات إن أمكن\n"
            "• كل بما يكفي لدعم التمرين\n"
            "• حركة خفيفة في أيام الراحة\n"
            "• خفّف الحمل عند الإرهاق المستمر\n\n"
            "اقتراح عملي:\n"
            "1. حدّد أيام راحة\n"
            "2. أسبوع أخف كل 4–8 أسابيع عند الحاجة\n"
            "3. قدّم النوم على زيادة الحجم"
        ),
        "frequency": (
            "معظم الأشخاص ينجحون مع 3–5 أيام تدريب.\n\n"
            "• مبتدئ: 3 أيام\n"
            "• متوسط: 4 أيام\n"
            "• متقدم: 5+ فقط مع تعافٍ جيد\n\n"
            "اقتراح عملي:\n"
            "1. اختر جدولًا يمكنك الالتزام به\n"
            "2. اترك يوم راحة كاملًا على الأقل\n"
            "3. زد التكرار بعد ثبات الالتزام"
        ),
        "general": (
            "أنا مدرب أوكتا فيت الرياضي.\n\n"
            "يمكنني المساعدة في:\n"
            "• خطط التدريب واختيار التمارين\n"
            "• بناء العضلات وخسارة الدهون والتضخيم والتنشيف\n"
            "• إرشاد المبتدئين والتعافي\n"
            "• تغذية رياضية عامة\n\n"
            "اسأل بشكل محدد مثل: «خطة كامل الجسم للمبتدئ» أو «أفكار وجبات عالية البروتين بعد التمرين»."
        ),
        "progressive_overload": (
            "التقدم التدريجي (Progressive Overload) يعني زيادة صعوبة التمرين مع الوقت "
            "عبر الوزن أو التكرارات أو المجموعات أو التحكم — مع الحفاظ على تقنية جيدة."
        ),
    }

    routing = routing or {}
    # Food lookup fallthrough: short direct answer, no profile/training dump.
    if routing.get("is_food_lookup") or routing.get("nutrition_fallthrough"):
        if lang == "ar":
            return (
                "هذا سؤال تغذية مباشر. القيم تختلف حسب الحجم والتحضير "
                "(نيء/مطبوخ). إن توفر رقم دقيق من قاعدة بيانات أوكتا فيت يُعرض مباشرة؛ "
                "وإلا أعطِ تقديرًا عامًا فقط دون اختراع أرقام دقيقة."
            )
        return (
            "This is a direct nutrition question. Values vary by size and preparation "
            "(raw/cooked). Use grounded OCTA FIT values when available; otherwise give a "
            "general estimate without inventing precise numbers."
        )

    table = answers_ar if lang == "ar" else answers_en
    if topic == "progressive_overload" and lang == "en":
        return (
            "Progressive overload means gradually increasing training demand over time "
            "via load, reps, sets, or control — while keeping good form."
        )
    return table.get(topic, table["general"])
