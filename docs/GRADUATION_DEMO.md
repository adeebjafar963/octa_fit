# Graduation demo script

Ideal defense walkthrough (~15–25 minutes). Keep MariaDB + Laravel + AI services running before starting.

---

## Prep (2 min)

1. Start XAMPP MySQL  
2. Start Laravel `:8000`  
3. Start Coach / Physio / Motion  
4. Launch Flutter app (device or emulator)  
5. Confirm language switch works once  

---

## Demo sequence

| # | Step | What to show |
|---|------|----------------|
| 1 | Login | Demo user |
| 2 | Home | Clean welcome + AI shortcuts + featured sections |
| 3 | Bodybuilding | Categories → exercise list |
| 4 | Exercise video | Play demo URL + bilingual description |
| 5 | MMA | Technique list |
| 6 | MMA video | Play demo technique |
| 7 | Store | Product list |
| 8 | Cart | Add product, change qty |
| 9 | Checkout | COD / demo order success |
| 10 | AI Coach | Ask for beginner hypertrophy week |
| 11 | AI Coach | Ask nutrition/protein question |
| 12 | Switch language | Repeat one Coach answer in Arabic |
| 13 | AI Physiotherapist | Mild educational assessment |
| 14 | Red flags | Trigger urgent recommendation (disclaimer) |
| 15 | Motion Analyzer | Supported exercise (squat/push-up/curl) |
| 16 | Motion feedback | Show simple form hints + uncertainty honesty |
| 17 | Community | Create post |
| 18 | Social | Like + comment |
| 19 | Profile | Fitness fields + logout |
| 20 | Admin dashboard | Users, products, training videos, orders, content |
| 21 | Admin edit | Change a video URL / product stock live |

---

## Talking points (architecture)

- Clean Architecture + Cubit on Flutter  
- Laravel Sanctum API + admin  
- AI keys never in the app — Laravel gateway  
- Controlled knowledge base for Coach  
- Physio is educational triage, not diagnosis  
- Motion is CV demonstration, not medical biomechanics  
- No real payment gateway (intentional graduation scope)  

---

## Fail-safes if a service is down

- Coach/Physio/Motion: show prepared screenshots + explain gateway returns friendly 503  
- Video URL offline: admin replace URL story  
- Emulator networking: use `10.0.2.2`  

---

## Success criteria

Committee understands the full loop: **train → shop → community → AI assist → admin control**, with clear simplicity and academic honesty about AI limits.
