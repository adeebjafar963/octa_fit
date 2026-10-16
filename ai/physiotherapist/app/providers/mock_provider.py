from __future__ import annotations

import re
from typing import Literal

from app.providers.base import AssessRequest, AssessResponse, FollowUpRequest, PhysioProvider

URGENT_PATTERNS = re.compile(
    r"("
    r"cannot\s+(move|walk|bear)|unable\s+to\s+(move|walk|bear)|"
    r"severe\s+pain|worst\s+pain|10/10|pain\s+level\s*[:\-]?\s*10|"
    r"deformity|unconscious|chest\s+pain|can'?t\s+breathe|shortness\s+of\s+breath|"
    r"loss\s+of\s+consciousness|bladder|bowel|paralysis|major\s+trauma|"
    r"bleeding\s+(uncontrolled|severe)|numbness\s+and\s+weakness|"
    r"لا\s+أستطيع\s+(المشي|الحركة)|ألم\s+شديد|تشوه|فقدان\s+الوعي|"
    r"ألم\s+صدر|ضيق\s+تنفس|نزيف|شلل"
    r")",
    re.IGNORECASE,
)

HIGH_PATTERNS = re.compile(
    r"("
    r"numbness|tingling|weakness|swelling\s+severe|night\s+pain|"
    r"getting\s+worse|worsening|radiating|"
    r"تنميل|وخز|ضعف|تورم\s+شديد|يزداد|إشعاع"
    r")",
    re.IGNORECASE,
)


def detect_language(text: str, explicit: Literal["en", "ar"] | None = None) -> Literal["en", "ar"]:
    if explicit in ("en", "ar"):
        return explicit
    arabic = len(re.findall(r"[\u0600-\u06FF]", text))
    return "ar" if arabic >= max(3, len(text) // 4) else "en"


def _blob(request: AssessRequest) -> str:
    parts = [
        request.body_area,
        request.symptoms,
        request.duration or "",
        request.onset or "",
        request.trigger or "",
        request.activity or "",
        request.additional_information or "",
        str(request.pain_level if request.pain_level is not None else ""),
    ]
    return " ".join(parts)


class MockPhysioProvider(PhysioProvider):
    name = "mock"

    async def assess(self, request: AssessRequest, language: Literal["en", "ar"]) -> AssessResponse:
        return _build_response(request, language, self.name)

    async def follow_up(
        self,
        request: FollowUpRequest,
        language: Literal["en", "ar"],
    ) -> AssessResponse:
        # Re-evaluate with follow-up text included for safety.
        merged = request.assessment.model_copy(
            update={
                "additional_information": " ".join(
                    filter(
                        None,
                        [
                            request.assessment.additional_information or "",
                            request.message,
                        ],
                    )
                )
            }
        )
        base = _build_response(merged, language, self.name)
        follow = _follow_up_note(request.message, language, base.risk_level)
        return base.model_copy(update={"summary": f"{base.summary}\n\n{follow}"})


def _build_response(
    request: AssessRequest,
    language: Literal["en", "ar"],
    provider: str,
) -> AssessResponse:
    text = _blob(request)
    pain = request.pain_level if request.pain_level is not None else 0

    if URGENT_PATTERNS.search(text) or pain >= 9:
        return _urgent(language, provider)
    if HIGH_PATTERNS.search(text) or pain >= 7:
        return _high(request, language, provider)

    lower = text.lower()
    if any(k in lower for k in ("soreness", "doms", "sore after", "تأخر", "إجهاد عضلي", "وجع عضلات")):
        return _soreness(request, language, provider)
    if any(k in lower for k in ("tight", "stiff", "mobility", "stretch", "تيبس", "شد", "مرونة")):
        return _tightness(request, language, provider)
    if pain >= 4 or any(k in lower for k in ("hurt", "pain", "ألم", "يؤلم")):
        return _moderate_discomfort(request, language, provider)
    return _low(request, language, provider)


def _urgent(lang: Literal["en", "ar"], provider: str) -> AssessResponse:
    if lang == "ar":
        return AssessResponse(
            risk_level="urgent",
            requires_urgent_care=True,
            summary=(
                "بناءً على وصفك، قد تكون هناك حاجة لتقييم طبي عاجل. "
                "هذا النظام لا يشخص الحالات ولا يحل محل الرعاية الطارئة."
            ),
            possible_explanations=[
                "قد تكون الأعراض مرتبطة بإصابة أو حالة تحتاج تقييمًا طبيًا سريعًا.",
                "لا يمكن تحديد السبب بدقة عبر تقييم رقمي تثقيفي.",
            ],
            self_care=[
                "توقف عن النشاط الذي يزيد الأعراض.",
                "اطلب رعاية طبية عاجلة الآن.",
            ],
            avoid=[
                "لا تعتمد على هذا التقييم بدلًا من الطوارئ.",
                "لا تحاول اختبار الحركة بقوة إذا كان الألم شديدًا أو الحركة مستحيلة.",
            ],
            professional_care="اطلب رعاية طبية عاجلة/طارئة فورًا.",
            red_flags=[
                "ألم شديد أو متزايد بسرعة",
                "عدم القدرة على تحميل الوزن أو تحريك الطرف",
                "أعراض عصبية أو تنفسية أو صدرية مقلقة",
            ],
            provider=provider,
            language=lang,
        )
    return AssessResponse(
        risk_level="urgent",
        requires_urgent_care=True,
        summary=(
            "Based on your description, urgent medical evaluation may be needed. "
            "This tool cannot diagnose conditions and does not replace emergency care."
        ),
        possible_explanations=[
            "Symptoms may relate to an injury or condition that needs prompt medical assessment.",
            "A digital educational tool cannot determine the exact cause.",
        ],
        self_care=[
            "Stop activities that worsen symptoms.",
            "Seek urgent medical care now.",
        ],
        avoid=[
            "Do not rely on this assessment instead of emergency care.",
            "Do not force movement if pain is severe or you cannot move the area.",
        ],
        professional_care="Seek urgent/emergency medical care immediately.",
        red_flags=[
            "Severe or rapidly worsening pain",
            "Inability to bear weight or move the limb",
            "Concerning neurological, chest, or breathing symptoms",
        ],
        provider=provider,
        language=lang,
    )


def _high(request: AssessRequest, lang: Literal["en", "ar"], provider: str) -> AssessResponse:
    area = request.body_area
    if lang == "ar":
        return AssessResponse(
            risk_level="high",
            requires_urgent_care=False,
            summary=(
                f"أعراض منطقة «{area}» قد تحتاج تقييمًا مهنيًا قريبًا. "
                "قد تكون هناك عدة أسباب محتملة، ولا يمكن تأكيد التشخيص هنا."
            ),
            possible_explanations=[
                "تهيج أنسجة مرتبط بالتمرين",
                "تقييد حركة أو تحميل زائد",
                "أسباب أخرى تحتاج فحصًا مباشرًا",
            ],
            self_care=[
                "قلل التمارين التي تزيد الأعراض بوضوح.",
                "حافظ على حركة لطيفة ضمن حدود الراحة.",
                "راقب التغيرات خلال اليومين القادمين.",
            ],
            avoid=[
                "تجنب رفع الأحمال الثقيلة أو الحركات التي تسبب ألمًا حادًا.",
                "لا تتجاهل التنميل أو الضعف المتزايد.",
            ],
            professional_care="يُفضل مراجعة أخصائي علاج طبيعي أو طبيب مؤهل قريبًا.",
            red_flags=[
                "تفاقم سريع",
                "ضعف أو تنميل متزايد",
                "تورم ملحوظ أو عدم تحمل الوزن",
            ],
            provider=provider,
            language=lang,
        )
    return AssessResponse(
        risk_level="high",
        requires_urgent_care=False,
        summary=(
            f"Symptoms around your {area} may need prompt professional assessment. "
            "Several causes are possible, and this tool cannot confirm a diagnosis."
        ),
        possible_explanations=[
            "Exercise-related tissue irritation",
            "Movement limitation or overload",
            "Other causes that need in-person assessment",
        ],
        self_care=[
            "Reduce exercises that clearly worsen symptoms.",
            "Keep gentle comfortable movement.",
            "Monitor changes over the next 1–2 days.",
        ],
        avoid=[
            "Avoid heavy loading or sharp painful movements.",
            "Do not ignore increasing numbness or weakness.",
        ],
        professional_care="Consider seeing a qualified physiotherapist or clinician soon.",
        red_flags=[
            "Rapid worsening",
            "Increasing weakness or numbness",
            "Significant swelling or inability to bear weight",
        ],
        provider=provider,
        language=lang,
    )


def _moderate_discomfort(request: AssessRequest, lang: Literal["en", "ar"], provider: str) -> AssessResponse:
    area = request.body_area
    if lang == "ar":
        return AssessResponse(
            risk_level="moderate",
            requires_urgent_care=False,
            summary=(
                f"يبدو أن لديك انزعاجًا متوسطًا في منطقة «{area}» مرتبطًا بالنشاط. "
                "هذا قد يتوافق مع تهيج ناتج عن التمرين، لكن السبب الدقيق يحتاج تقييمًا مهنيًا عند استمرار الأعراض."
            ),
            possible_explanations=[
                "حمل تدريبي أعلى من المعتاد",
                "تقنية أو مدى حركة يحتاج تعديلًا",
                "تعب موضعي أو تيبس",
            ],
            self_care=[
                "خفف التمرين المسبب مؤقتًا.",
                "استخدم حركة لطيفة وإحماءً جيدًا.",
                "نم جيدًا واشرب ماءً كافيًا.",
            ],
            avoid=[
                "تجنب تكرار الحركة المؤلمة بنفس الشدة.",
                "لا تتقدم في الأوزان أثناء استمرار الألم الواضح.",
            ],
            professional_care="إذا استمر الألم أو ازداد خلال أيام، راجع مختصًا مؤهلًا.",
            red_flags=[
                "ألم شديد مفاجئ",
                "عدم القدرة على الاستخدام الطبيعي للطرف",
                "أعراض عصبية جديدة",
            ],
            provider=provider,
            language=lang,
        )
    return AssessResponse(
        risk_level="moderate",
        requires_urgent_care=False,
        summary=(
            f"You describe moderate discomfort around your {area} that may relate to training. "
            "This pattern can be consistent with exercise-related irritation, but the exact cause "
            "needs professional assessment if symptoms persist."
        ),
        possible_explanations=[
            "Training load higher than usual",
            "Technique or range-of-motion issue",
            "Local fatigue or stiffness",
        ],
        self_care=[
            "Temporarily reduce the aggravating exercise.",
            "Use gentle movement and a thorough warm-up.",
            "Prioritize sleep and hydration.",
        ],
        avoid=[
            "Avoid repeating the painful movement at the same intensity.",
            "Do not push heavier loads while clear pain continues.",
        ],
        professional_care="If symptoms persist or worsen over several days, consult a qualified professional.",
        red_flags=[
            "Sudden severe pain",
            "Inability to use the limb normally",
            "New neurological symptoms",
        ],
        provider=provider,
        language=lang,
    )


def _soreness(request: AssessRequest, lang: Literal["en", "ar"], provider: str) -> AssessResponse:
    if lang == "ar":
        return AssessResponse(
            risk_level="low",
            requires_urgent_care=False,
            summary=(
                "وصفك يتوافق غالبًا مع ألم عضلي متأخر شائع بعد التمرين. "
                "هذا تقييم تثقيفي عام وليس تشخيصًا."
            ),
            possible_explanations=[
                "ألم عضلي متأخر بعد جلسة جديدة أو أقوى",
                "تعب عضلي مؤقت",
            ],
            self_care=[
                "حركة خفيفة ومشي لطيف.",
                "نوم كافٍ وترطيب.",
                "عد تدريجيًا إلى التمرين خلال يوم أو يومين.",
            ],
            avoid=[
                "تجنب جلسة قاسية جدًا على نفس العضلات فورًا.",
                "لا تتجاهل ألمًا حادًا مختلفًا عن الوجع المعتاد.",
            ],
            professional_care="إذا استمر الوجع أكثر من عدة أيام أو أصبح حادًا، راجع مختصًا.",
            red_flags=[
                "تورم شديد",
                "ضعف مفاجئ",
                "ألم حاد يحد من الحركة الطبيعية",
            ],
            provider=provider,
            language=lang,
        )
    return AssessResponse(
        risk_level="low",
        requires_urgent_care=False,
        summary=(
            "Your description is often consistent with delayed muscle soreness after training. "
            "This is general educational guidance, not a diagnosis."
        ),
        possible_explanations=[
            "Delayed onset muscle soreness after a new or harder session",
            "Temporary muscle fatigue",
        ],
        self_care=[
            "Use light movement and easy walking.",
            "Sleep well and stay hydrated.",
            "Return gradually to training over 1–2 days.",
        ],
        avoid=[
            "Avoid another very hard session on the same muscles immediately.",
            "Do not ignore sharp pain that feels different from usual soreness.",
        ],
        professional_care="If soreness lasts many days or becomes sharp/limiting, seek professional advice.",
        red_flags=[
            "Severe swelling",
            "Sudden weakness",
            "Sharp pain that blocks normal movement",
        ],
        provider=provider,
        language=lang,
    )


def _tightness(request: AssessRequest, lang: Literal["en", "ar"], provider: str) -> AssessResponse:
    area = request.body_area
    if lang == "ar":
        return AssessResponse(
            risk_level="low",
            requires_urgent_care=False,
            summary=(
                f"التيبس في منطقة «{area}» شائع بعد التمرين أو الجلوس الطويل. "
                "يمكن تجربة حركة لطيفة، مع مراقبة أي أعراض مقلقة."
            ),
            possible_explanations=[
                "تيبس عضلي مؤقت",
                "نقص إحماء أو تمدد لطيف",
            ],
            self_care=[
                "إطالات لطيفة ضمن مدى مريح.",
                "إحماء أفضل قبل التمرين القادم.",
                "حركة متكررة خلال اليوم.",
            ],
            avoid=[
                "تجنب الإطالات القسرية المؤلمة.",
                "لا تستمر في التمرين إذا تحول التيبس إلى ألم حاد.",
            ],
            professional_care="إذا استمر التيبس مع ألم أو تنميل، راجع مختصًا مؤهلًا.",
            red_flags=[
                "ألم حاد",
                "تنميل أو ضعف",
                "تقييد شديد مفاجئ للحركة",
            ],
            provider=provider,
            language=lang,
        )
    return AssessResponse(
        risk_level="low",
        requires_urgent_care=False,
        summary=(
            f"Tightness around your {area} is common after training or long sitting. "
            "Gentle movement may help, while you monitor for concerning symptoms."
        ),
        possible_explanations=[
            "Temporary muscle stiffness",
            "Insufficient warm-up or gentle mobility work",
        ],
        self_care=[
            "Use gentle stretches in a comfortable range.",
            "Warm up more thoroughly before the next session.",
            "Move regularly through the day.",
        ],
        avoid=[
            "Avoid forcing painful stretches.",
            "Do not continue training if tightness becomes sharp pain.",
        ],
        professional_care="If tightness continues with pain or numbness, consult a qualified professional.",
        red_flags=[
            "Sharp pain",
            "Numbness or weakness",
            "Sudden severe movement restriction",
        ],
        provider=provider,
        language=lang,
    )


def _low(request: AssessRequest, lang: Literal["en", "ar"], provider: str) -> AssessResponse:
    if lang == "ar":
        return AssessResponse(
            risk_level="low",
            requires_urgent_care=False,
            summary=(
                "بناءً على المعلومات الحالية، يبدو أن المخاطر منخفضة نسبيًا. "
                "راقب الأعراض واستخدم تعديلات بسيطة على النشاط."
            ),
            possible_explanations=[
                "انزعاج خفيف مرتبط بالنشاط",
                "تعب مؤقت",
            ],
            self_care=[
                "تابع التدريب بتقدم تدريجي.",
                "اهتم بالنوم والتعافي.",
                "عدّل التمرين إذا عاد الانزعاج.",
            ],
            avoid=["تجنب القفز المفاجئ في حجم التدريب."],
            professional_care="راجع مختصًا إذا ظهرت أعراض جديدة أو استمرت.",
            red_flags=["ألم شديد", "ضعف", "تورم ملحوظ"],
            provider=provider,
            language=lang,
        )
    return AssessResponse(
        risk_level="low",
        requires_urgent_care=False,
        summary=(
            "Based on the current information, risk appears relatively low. "
            "Monitor symptoms and use simple activity adjustments."
        ),
        possible_explanations=[
            "Mild activity-related discomfort",
            "Temporary fatigue",
        ],
        self_care=[
            "Continue training with gradual progression.",
            "Prioritize sleep and recovery.",
            "Modify the exercise if discomfort returns.",
        ],
        avoid=["Avoid sudden large jumps in training volume."],
        professional_care="See a professional if new or persistent symptoms appear.",
        red_flags=["Severe pain", "Weakness", "Significant swelling"],
        provider=provider,
        language=lang,
    )


def _follow_up_note(message: str, lang: Literal["en", "ar"], risk: str) -> str:
    lower = message.lower()
    if lang == "ar":
        if any(k in lower for k in ("غدا", "تمرين", "أتدرب")):
            if risk in ("urgent", "high"):
                return "بخصوص التدريب: الأفضل تأجيل التمرين المؤلم حتى يتم تقييم مهني."
            return "بخصوص التدريب: يمكن العودة تدريجيًا إذا كان الألم خفيفًا ولا يزداد أثناء/بعد الجلسة."
        return "شكرًا للمتابعة. استمر في مراقبة الأعراض واتبع توصيات التقييم أعلاه."
    if any(k in lower for k in ("tomorrow", "train", "workout")):
        if risk in ("urgent", "high"):
            return "About training: it is better to pause painful exercise until you get professional assessment."
        return "About training: you may return gradually if discomfort stays mild and does not worsen during/after sessions."
    return "Thanks for the follow-up. Keep monitoring symptoms and follow the guidance above."
